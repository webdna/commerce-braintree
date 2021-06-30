<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\braintree\gateways;

use kuriousagency\commerce\braintree\Braintree as BT;

use kuriousagency\commerce\braintree\assetbundles\dropinui\DropinUiAsset;
use kuriousagency\commerce\braintree\assetbundles\hostedfields\HostedFieldsAsset;
use kuriousagency\commerce\braintree\models\Payment;
use kuriousagency\commerce\braintree\models\CancelSubscription;
use kuriousagency\commerce\braintree\models\Plan;
use kuriousagency\commerce\braintree\models\SwitchPlans;
use kuriousagency\commerce\braintree\responses\PaymentResponse;
use kuriousagency\commerce\braintree\responses\SubscriptionResponse;

use Braintree;

use Craft;
//use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\Plan as BasePlan;
use craft\commerce\base\PlanInterface;
use craft\commerce\base\SubscriptionGateway as BaseGateway;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\TransactionException;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\models\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\models\subscriptions\CancelSubscriptionForm as BaseCancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template as TemplateHelper;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use craft\db\Query;
use craft\db\Command;
use yii\base\Exception;
use yii\base\NotSupportedException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Gateway represents Braintree gateway
 *
 * @author    Kurious Agency
 * @package   Braintree
 * @since     1.0.0
 *
 */
class Gateway extends BaseGateway
{
	// Properties
	// =========================================================================

	public $apiUrl = '';

	public $merchantId;

	public $publicKey;

	public $privateKey;

	public $testMode;

	public $merchantAccountId;

	public $sendCartInfo;

	private $gateway;

	private $customer;

	// Public Methods
	// =========================================================================

	public function init()
	{
		parent::init();

		//BT::log('Braintree init');

		$this->gateway = new Braintree\Gateway([
			'environment' => $this->testMode ? 'sandbox' : 'production',
			'merchantId' => Craft::parseEnv($this->merchantId),
			'publicKey' => Craft::parseEnv($this->publicKey),
			'privateKey' => Craft::parseEnv($this->privateKey),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		return Craft::t('commerce', 'Braintree');
	}

	/**
	 * @inheritdoc
	 */
	public function getPaymentFormHtml(array $params = [])
	{
		$request = Craft::$app->getRequest();

		if ($request->isCpRequest) {
			return $this->getCpPaymentFormHtml($params);
		} else {
			return $this->getSitePaymentFormHtml($params);
		}
	}

	public function getToken($user = null, $currency = null)
	{
		//$omnipayGateway = $this->createGateway();
		$params = [];
		if ($currency) {
			$params['merchantAccountId'] = Craft::parseEnv(
				$this->merchantAccountId[$currency]
			);
		}
		if ($user) {
			try {
				$customer = $this->getCustomer($user);
			} catch (\Braintree_Exception_NotFound $e) {
				$customer = null;
			}

			if (!$customer) {
				$customer = $this->gateway->customer()->create([
					'id' => $user->uid,
					'firstName' => $user->firstName,
					'lastName' => $user->lastName,
					'email' => $user->email,
				]);
			}
			$params['customerId'] = $user->uid;
		}
		$token = $this->gateway->clientToken($params)->generate($params);

		return $token;
	}

	/**
	 * @inheritdoc
	 */
	public function getSettingsHtml()
	{
		return Craft::$app
			->getView()
			->renderTemplate('commerce-braintree/gatewaySettings', [
				'gateway' => $this,
			]);
	}

	/**
	 * @inheritdoc
	 */
	/*public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
    {
        if ($paymentForm && $paymentForm->hasProperty('nonce') && $paymentForm->nonce) {
            $request['token'] = $paymentForm->nonce;
        }
        $request['merchantAccountId'] = Craft::parseEnv($this->merchantAccountId[$request['currency']]);
        //Craft::dd($request);
    }*/

	public function getCustomer($user)
	{
		if (!$this->customer) {
			try {
				$this->customer = $this->gateway->customer()->find($user->uid);
			} catch (\Throwable $exception) {
				return null;
			}
		}
		return $this->customer;
	}

	public function getPaymentMethod($token)
	{
		return $this->gateway->paymentMethod()->find($token);
	}

	public function createPaymentMethod(
		BasePaymentForm $sourceData,
		int $userId
	) {
		if (!$userId) {
			$user = Craft::$app->getUser()->getIdentity();
		} else {
			$user = Craft::$app->getUsers()->getUserById($userId);
		}
		//Craft::dd($sourceData->nonce);
		//BT::log('Create payment method: ' . $order->id);

		return (object) $this->gateway->paymentMethod()->create([
			'customerId' => $user->uid,
			'paymentMethodNonce' => $sourceData->nonce,
			'options' => [
				'makeDefault' => (bool) $sourceData->default,
			],
		]);
	}

	public function deletePaymentMethod($token)
	{
		$result = $this->gateway->paymentMethod()->delete($token);

		return $result->success;
	}

	public function authorize(
		Transaction $transaction,
		BasePaymentForm $form
	): RequestResponseInterface {
        //Craft::dd($transaction);
		try {
			$order = $transaction->getOrder();
			$data = [
				'amount' => $transaction->paymentAmount,
				'orderId' => $order->shortNumber,
				'options' => ['submitForSettlement' => false],
			];

			if ($order->user) {
				if ($this->getCustomer($order->user)) {
					$data['customerId'] = $order->user->uid;
				} else {
					$data['customer'] = [
						'firstName' => $order->user->firstName,
						'lastName' => $order->user->lastName,
						'email' => $order->email,
					];
				}
			} else {
				$data['customer'] = [
					'email' => $order->email,
				];
			}

			// deviceData

			if ($form->nonce) {
				$data['paymentMethodNonce'] = $form->nonce;
			} elseif ($form->token) {
				$data['paymentMethodToken'] = $form->token;
			}
			if (
				isset($this->merchantAccountId[$transaction->currency]) &&
				!empty($this->merchantAccountId[$transaction->currency])
			) {
				$data['merchantAccountId'] = Craft::parseEnv(
					$this->merchantAccountId[$transaction->currency]
				);
			} else {
				$data['merchantAccountId'] = "";
				$data['amount'] = $transaction->amount;
			}
			if ($form->type != "PayPalAccount") {
				if ($order->billingAddress || $order->shippingAddress) {
					$data['billing'] = $this->_formatAddress(
						$order->billingAddress ?: $order->shippingAddress
					);
				}
				if ($order->shippingAddress) {
					$data['shipping'] = $this->_formatAddress(
						$order->shippingAddress
					);
				}
			}

			$result = $this->createSale($data);

			BT::log('Create Sale: ' . $order->id);

			//Craft::dd($result);

			return new PaymentResponse($result);
		} catch (\Exception $exception) {
			$message = $exception->getMessage();
			if ($message) {
				BT::error($message);
				throw new PaymentException($message);
			}
			BT::error(
				'The payment could not be processed (' .
					get_class($exception) .
					')'
			);
			throw new PaymentException(
				'The payment could not be processed (' .
					get_class($exception) .
					')'
			);
		}
	}

	public function capture(
		Transaction $transaction,
		string $reference
	): RequestResponseInterface {
        //Craft::dd($transaction);
		try {
			$result = $this->gateway
				->transaction()
				->submitForSettlement($reference);
			return new PaymentResponse($result);
		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	public function completeAuthorize(
		Transaction $transaction
	): RequestResponseInterface {
	}

	public function createPaymentSource(
		BasePaymentForm $sourceData,
		int $userId
	): PaymentSource {
		//Craft::dd($sourceData);
		try {
			$response = $this->createPaymentMethod($sourceData, $userId);
			//Craft::dd($response);
			if (!$response->success) {
				throw new PaymentSourceException($response->message);
			}

			//check for existing paymentSource
			$sources = Commerce::getInstance()
				->getPaymentSources()
				->getAllGatewayPaymentSourcesByUserId($this->id, $userId);

			foreach ($sources as $source) {
				if ($source->token == $response->paymentMethod->token) {
					Commerce::getInstance()
						->getPaymentSources()
						->deletePaymentSourceById($source->id);
				}
			}

			$description = Craft::t(
				'commerce-braintree',
				'{cardType} ending in ••••{last4}',
				[
					'cardType' => $response->paymentMethod->cardType,
					'last4' => $response->paymentMethod->last4,
				]
			);

			$paymentSource = new PaymentSource([
				'userId' => $userId,
				'gatewayId' => $this->id,
				'token' => $response->paymentMethod->token,
				'response' => $response->paymentMethod,
				'description' => $description,
			]);

			return $paymentSource;
		} catch (\Throwable $exception) {
			throw new PaymentSourceException($exception->getMessage());
		}
	}

	public function deletePaymentSource($token): bool
	{
		return true;
	}

	public function getPaymentFormModel(): BasePaymentForm
	{
		return new Payment();
	}

	public function purchase(
		Transaction $transaction,
		BasePaymentForm $form
	): RequestResponseInterface {
		//Craft::dd($transaction);
		try {
			$order = $transaction->getOrder();
			$data = [
				'amount' => $transaction->paymentAmount,
				'orderId' => $order->shortNumber,
				'options' => ['submitForSettlement' => true],
			];

			if ($order->user) {
				if ($this->getCustomer($order->user)) {
					$data['customerId'] = $order->user->uid;
				} else {
					$data['customer'] = [
						'firstName' => $order->user->firstName,
						'lastName' => $order->user->lastName,
						'email' => $order->email,
					];
				}
			} else {
				$data['customer'] = [
					'email' => $order->email,
				];
			}

			// deviceData

			if ($form->nonce) {
				$data['paymentMethodNonce'] = $form->nonce;
			} elseif ($form->token) {
				$data['paymentMethodToken'] = $form->token;
			}
			if (
				isset($this->merchantAccountId[$transaction->currency]) &&
				!empty($this->merchantAccountId[$transaction->currency])
			) {
				$data['merchantAccountId'] = Craft::parseEnv(
					$this->merchantAccountId[$transaction->currency]
				);
			} else {
				$data['merchantAccountId'] = "";
				$data['amount'] = $transaction->amount;
			}
			if ($form->type != "PayPalAccount") {
				if ($order->billingAddress || $order->shippingAddress) {
					$data['billing'] = $this->_formatAddress(
						$order->billingAddress ?: $order->shippingAddress
					);
				}
				if ($order->shippingAddress) {
					$data['shipping'] = $this->_formatAddress(
						$order->shippingAddress
					);
				}
			}

			$result = $this->createSale($data);

			BT::log('Create Sale: ' . $order->id);

			//Craft::dd($result);

			return new PaymentResponse($result);
		} catch (\Exception $exception) {
			$message = $exception->getMessage();
			if ($message) {
				BT::error($message);
				throw new PaymentException($message);
			}
			BT::error(
				'The payment could not be processed (' .
					get_class($exception) .
					')'
			);
			throw new PaymentException(
				'The payment could not be processed (' .
					get_class($exception) .
					')'
			);
		}
	}

	public function completePurchase(
		Transaction $transaction
	): RequestResponseInterface {
	}

	public function createSale($data)
	{
		return (object) $this->gateway->transaction()->sale($data);
	}

	public function refund(Transaction $transaction): RequestResponseInterface
	{
		//Craft::dd($transaction);
		try {
			$result = $this->gateway
				->transaction()
				->refund($transaction->reference, $transaction->amount);
			return new PaymentResponse($result);
		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	// Subscriptions
	public function cancelSubscription(
		Subscription $subscription,
		BaseCancelSubscriptionForm $parameters
	): SubscriptionResponseInterface {
		$response = $this->gateway
			->subscription()
			->cancel($subscription->reference);

		if ($response->success) {
			// remove paymentsource
			$source = $this->getPaymentSource(
				$subscription->userId,
				$subscription->subscriptionData['paymentMethodToken']
			);
			if ($source) {
				// check if any other subscriptions are using this payment source
				$canDelete = true;
				foreach (
					Subscription::find()
						->gatewayId($this->id)
						->userId($subscription->userId)
						->isCanceled(0)
						->reference(['not', $subscription->reference])
						->all()
					as $sub
				) {
					if (
						$sub->subscriptionData['paymentMethodToken'] ==
						$source->token
					) {
						$canDelete = false;
					}
				}
				if ($canDelete) {
					Commerce::getInstance()
						->getPaymentSources()
						->deletePaymentSourceById($source->id);
				}
			}

			return new SubscriptionResponse($response->subscription);
		} else {
			foreach (
				$response->errors->forKey('subscription')->shallowAll()
				as $error
			) {
				// subscription has already been cancelled on Braintree but not in the site. So let's cancel on site as well
				if ($error->code == "81905") {
					try {
						$response = $this->gateway
							->subscription()
							->find($subscription->reference);
					} catch (Braintree_Exception_NotFound $e) {
						throw new SubscriptionException(
							'Failed to cancel subscription: ' .
								$subscription->reference .
								". Error:" .
								$e->getMessage()
						);
					}

					return new SubscriptionResponse($response);
				}
			}

			throw new SubscriptionException(
				'Failed to cancel subscription: ' . $subscription->reference
			);
		}
	}

	public function getCancelSubscriptionFormHtml(
		Subscription $subscription
	): string {
		$view = Craft::$app->getView();

		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$html = $view->renderTemplate(
			'commerce-braintree/cancelSubscriptionForm'
		);
		$view->setTemplateMode($previousMode);

		return $html;
	}

	public function getCancelSubscriptionFormModel(): BaseCancelSubscriptionForm
	{
		return new CancelSubscription();
	}

	public function getNextPaymentAmount(Subscription $subscription): string
	{
		$data = $subscription['subscriptionData'];
		$currency = Commerce::getInstance()
			->getCurrencies()
			->getCurrencyByIso($subscription->plan->currency);
		return Craft::$app
			->getFormatter()
			->asCurrency($data['nextBillingPeriodAmount'], $currency);
		//return $data->nextBillingPeriodAmount;
	}

	public function getPlanModel(): BasePlan
	{
		return new Plan();
	}

	public function getPlanSettingsHtml(array $params = [])
	{
		$params['plans'] = [];
		foreach ($this->getSubscriptionPlans() as $plan) {
			$params['plans'][] = [
				'label' => $plan->name,
				'value' => $plan->id,
			];
		}
		return Craft::$app
			->getView()
			->renderTemplate('commerce-braintree/planSettings', $params);
	}

	public function getSubscriptionFormHtml(): string
	{
		$view = Craft::$app->getView();

		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$html = $view->renderTemplate('commerce-braintree/subscriptionForm');
		$view->setTemplateMode($previousMode);

		return $html;
	}

	public function getSubscriptionFormModel(): SubscriptionForm
	{
		return new SubscriptionForm();
	}

	public function getSubscriptionPayments(Subscription $subscription): array
	{
		$data = $subscription['subscriptionData'];
		//Craft::dd($data->transactions);
		$payments = [];
		foreach ($data['transactions'] as $transaction) {
			$payments[] = new SubscriptionPayment([
				'paymentAmount' => $transaction['amount'],
				'paymentCurrency' => $transaction['currencyIsoCode'],
				'paymentDate' => new \DateTime(
					$transaction['createdAt']['date'],
					new \DateTimeZone($transaction['createdAt']['timezone'])
				),
				'paymentReference' => $transaction['id'],
				'paid' => $this->isPaid($transaction['status']),
				'response' => Json::encode($transaction),
			]);
		}

		return $payments;
	}

	public function refreshPaymentHistory(Subscription $subscription)
	{
		$response = null;

		try {
			$response = $this->gateway
				->subscription()
				->find($subscription->reference);
		} catch (\Braintree_Exception_NotFound $e) {
			throw new SubscriptionException(
				'Failed to refresh payment history for subscription: ' .
					$subscription->reference
			);
		}

		if ($response) {
			$subscription->setSubscriptionData(Json::encode($response));
			$subscription->nextPaymentDate = $response->nextBillingDate;

			Craft::$app->getElements()->saveElement($subscription);
		}

		return true;
	}

	public function getSubscriptionPlanByReference(string $reference): string
	{
		if (empty($reference)) {
			return '';
		}

		foreach ($this->getSubscriptionPlans() as $plan) {
			if ($plan->id == $reference) {
				return Json::encode($plan);
			}
		}

		return '';
	}

	public function getSubscriptionPlans(): array
	{
		$plans = $this->gateway->plan()->all();
		return $plans;

		$output = [];
		foreach ($plans as $plan) {
			$output[] = [
				'label' => $plan->name,
				'value' => $plan->id,
			];
		}

		return $output;
	}

	public function createSubscription($data)
	{
		return (object) $this->gateway->subscription()->create($data);
	}

	public function subscribe(
		User $user,
		BasePlan $plan,
		SubscriptionForm $parameters
	): SubscriptionResponseInterface {
		$source = $this->getPaymentSource($user->id);
		if (!$source) {
			throw new PaymentSourceException(
				Craft::t(
					'commerce-braintree',
					'No payment sources are saved to use for subscriptions.'
				)
			);
		}
		$plan = new Plan($plan);

		$data = [
			'paymentMethodToken' => $source->token,
			'planId' => $plan->reference,
			'price' => $plan->price,
			'merchantAccountId' => Craft::parseEnv(
				$this->merchantAccountId[$plan->getCurrency()]
			),
		];

		$response = $this->createSubscription($data);

		if (!$response->success) {
			//Craft::dd($response);
			throw new SubscriptionException(
				Craft::t(
					'commerce-braintree',
					'Unable to subscribe at this time.'
				)
			);
		}

		return new SubscriptionResponse($response->subscription);
	}

	public function getSwitchPlansFormHtml(
		PlanInterface $originalPlan,
		PlanInterface $targetPlan
	): string {
		$view = Craft::$app->getView();
		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);
		/** @var Plan $originalPlan */
		/** @var Plan $targetPlan */
		$html = $view->renderTemplate('commerce-braintree/switchPlansForm', [
			'targetPlan' => $targetPlan,
		]);
		$view->setTemplateMode($previousMode);
		return $html;
	}

	public function getSwitchPlansFormModel(): SwitchPlansForm
	{
		return new SwitchPlans();
	}

	public function switchSubscriptionPlan(
		Subscription $subscription,
		BasePlan $plan,
		SwitchPlansForm $parameters
	): SubscriptionResponseInterface {
		$source = $this->getPaymentSource($subscription->userId);
		$params = [
			'paymentMethodToken' => $source->token,
			'price' => $plan->price,
			'planId' => $plan->reference,
		];
		if (!(bool) $parameters->prorate) {
			$params['options'] = ['prorateCharges' => false];
		}
		//Craft::dd($params);
		$response = $this->gateway
			->subscription()
			->update($subscription->reference, $params);

		if (!$response->success) {
			throw new SubscriptionException(
				Craft::t(
					'commerce-braintree',
					'Unable to subscribe at this time.'
				)
			);
		}

		return new SubscriptionResponse($response->subscription);
	}

	public function updateSubscriptionPayment(
		Subscription $subscription,
		BasePlan $plan,
		$gateway,
		$paymentForm
	) {
		$userId = Craft::$app->getUser()->getId();
		$description = "";

		$oldSource = $this->getPaymentSource(
			$subscription->userId,
			$subscription->subscriptionData['paymentMethodToken']
		);
		$source = Commerce::getInstance()
			->getPaymentSources()
			->createPaymentSource(
				$userId,
				$gateway,
				$paymentForm,
				$description
			);

		$params = [
			'paymentMethodToken' => $source->token,
			'price' => $plan->price,
			'planId' => $plan->reference,
		];

		$response = $this->gateway
			->subscription()
			->update($subscription->reference, $params);

		if (!$response->success) {
			throw new SubscriptionException(
				Craft::t(
					'commerce-braintree',
					'Unable to unpdate subscription at this time.'
				)
			);
		} else {
			// remove paymentsource

			if ($oldSource) {
				// check if any other subscriptions are using this payment source
				$canDelete = true;
				foreach (
					Subscription::find()
						->gatewayId($this->id)
						->userId($subscription->userId)
						->isCanceled(0)
						->reference(['not', $subscription->reference])
						->all()
					as $sub
				) {
					if (
						$sub->subscriptionData['paymentMethodToken'] ==
						$oldSource->token
					) {
						$canDelete = false;
					}
				}
				if ($canDelete) {
					Commerce::getInstance()
						->getPaymentSources()
						->deletePaymentSourceById($oldSource->id);
				}
			}
		}

		return new SubscriptionResponse($response->subscription);
	}

	/**
	 * @inheritdoc
	 */
	public function getHasBillingIssues(Subscription $subscription): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function getBillingIssueDescription(
		Subscription $subscription
	): string {
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getBillingIssueResolveFormHtml(
		Subscription $subscription
	): string {
		throw new NotSupportedException();
	}

	public function processWebHook(): WebResponse
	{
		$signature = Craft::$app
			->getRequest()
			->getRequiredParam('bt_signature');
		$payload = Craft::$app->getRequest()->getRequiredParam('bt_payload');

		$webhookNotification = $this->gateway
			->webhookNotification()
			->parse($signature, $payload);

		switch ($webhookNotification->kind) {
			case 'subscription_canceled':
			case 'subscription_expired':
			case 'subscription_went_past_due':
				$this->_handleSubscriptionExpired(
					$webhookNotification->subscription
				);
				break;
			case 'subscription_charged_successfully':
				$this->_handleSubscriptionCharged(
					$webhookNotification->subscription
				);
				break;
			// subscription_charged_unsuccessfully
		}

		return Craft::$app->end();
	}

	public function supportsAuthorize(): bool
	{
		return true;
	}

	public function supportsCapture(): bool
	{
		return true;
	}

	public function supportsCompleteAuthorize(): bool
	{
		return false;
	}

	public function supportsCompletePurchase(): bool
	{
		return false;
	}

	public function supportsPaymentSources(): bool
	{
		return false;
	}

	public function supportsPurchase(): bool
	{
		return true;
	}

	public function supportsRefund(): bool
	{
		return true;
	}

	public function supportsPartialRefund(): bool
	{
		return true;
	}

	public function supportsWebhooks(): bool
	{
		return true;
	}

	public function supportsPlanSwitch(): bool
	{
		return true;
	}

	public function supportsReactivation(): bool
	{
		return false;
	}

	private function getPaymentSource($userId, $token = null)
	{
		$sources = Commerce::getInstance()
			->getPaymentSources()
			->getAllGatewayPaymentSourcesByUserId($this->id, $userId);

		if (\count($sources) === 0) {
			return null;
		}

		if ($token) {
			foreach ($sources as $source) {
				if ($source->token == $token) {
					return $source;
				}
			}
		}

		// get first payment source
		return $sources[0];
	}

	private function getCpPaymentFormHtml(array $params = [])
	{
		$request = Craft::$app->getRequest();

		$params = array_merge(
			[
				'gateway' => $this,
				'paymentForm' => $this->getPaymentFormModel(),
			],
			$params
		);

		$orderId = $request->getParam('orderId');
		if ($orderId) {
			$order = Commerce::getInstance()
				->getOrders()
				->getOrderById($orderId);
		} else {
			$order = Commerce::getInstance()
				->getCarts()
				->getCart();
		}
		$params['order'] = $order;

		$view = Craft::$app->getView();
		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$view->registerJsFile(
			'https://js.braintreegateway.com/web/3.52.0/js/client.min.js'
		);
		$view->registerJsFile(
			'https://js.braintreegateway.com/web/3.52.0/js/hosted-fields.min.js'
		);
		$view->registerAssetBundle(HostedFieldsAsset::class);
		$html = $view->renderTemplate(
			'commerce-braintree/paymentForms/hosted-fields',
			$params
		);

		$view->setTemplateMode($previousMode);

		return $html;
	}

	private function getSitePaymentFormHtml(array $params = [])
	{
		$request = Craft::$app->getRequest();

		$params = array_merge(
			[
				'gateway' => $this,
				'paymentForm' => $this->getPaymentFormModel(),
				'threeDSecure' => false,
				'vault' => false,
				'manage' => false,
				'subscription' => false,
			],
			$params
		);

		$orderId = $request->getParam('number');
		if ($orderId) {
			$order = Commerce::getInstance()
				->getOrders()
				->getOrderByNumber($orderId);
		} else {
			$order = Commerce::getInstance()
				->getCarts()
				->getCart();
		}
		$params['order'] = $order;

		$view = Craft::$app->getView();
		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$view->registerJsFile(
			'https://js.braintreegateway.com/web/dropin/1.21.0/js/dropin.min.js'
		);
		$view->registerAssetBundle(DropinUiAsset::class);
		$html = $view->renderTemplate(
			'commerce-braintree/paymentForms/dropin-ui',
			$params
		);

		$view->setTemplateMode($previousMode);

		return TemplateHelper::raw($html);
	}

	private function isPaid($status)
	{
		switch ($status) {
			case 'submitted_for_settlement':
			case 'settling':
			case 'settled':
				return true;

			default:
				return false;
		}
	}

	/**
	 * Create a subscription payment model.
	 *
	 * @param $data
	 * @param Currency $currency the currency used for payment
	 *
	 * @return SubscriptionPayment
	 */
	private function _createSubscriptionPayment(
		$data,
		Currency $currency
	): SubscriptionPayment {
		$payment = new SubscriptionPayment([
			'paymentAmount' => $data->transactions[0]->amount,
			'paymentCurrency' => $currency,
			'paymentDate' => $data->transactions[0]->createdAt,
			'paymentReference' => $data->id,
			'paid' => true,
			'response' => Json::encode($data),
		]);

		return $payment;
	}

	/**
	 * Handle an expired subscription.
	 *
	 * @param $data
	 *
	 * @throws \Throwable
	 */
	private function _handleSubscriptionExpired($data)
	{
		$subscription = Subscription::find()
			->reference($data->id)
			->one();

		if (!$subscription) {
			Craft::warning(
				'Subscription with the reference “' .
					$subscription->id .
					'” not found when processing Braintree webhook'
			);

			return;
		}

		Commerce::getInstance()
			->getSubscriptions()
			->expireSubscription($subscription);
	}

	private function _handleSubscriptionCharged($data)
	{
		$counter = 0;
		$limit = 5;

		do {
			// Handle cases when Braintree sends us a webhook so soon that we haven't processed the subscription that triggered the webhook
			sleep(1);
			$subscription = Subscription::find()
				->reference($data->id)
				->one();
			$counter++;
		} while (!$subscription && $counter < $limit);

		if (!$subscription) {
			throw new SubscriptionException(
				'Subscription with the reference “' .
					$data->id .
					'” not found when processing braintree webhook'
			);
		}

		$defaultPaymentCurrency = Commerce::getInstance()
			->getPaymentCurrencies()
			->getPrimaryPaymentCurrency();
		$currency = Commerce::getInstance()
			->getCurrencies()
			->getCurrencyByIso($defaultPaymentCurrency->iso);
		$payment = $this->_createSubscriptionPayment($data, $currency);

		Commerce::getInstance()
			->getSubscriptions()
			->receivePayment($subscription, $payment, $data->nextBillingDate);
	}

	private function _formatAddress($data)
	{
		if (!$data) {
			return [];
		}

		return [
			'firstName' => $data->firstName,
			'lastName' => $data->lastName,
			'company' => StringHelper::safeTruncate($data->businessName, 50),
			'streetAddress' => StringHelper::safeTruncate($data->address1, 50),
			'extendedAddress' => StringHelper::safeTruncate(
				$data->address2,
				50
			),
			'locality' => $data->city,
			'region' =>
				$data->state && $data->country && $data->country->iso == 'US'
					? $data->state->abbreviation
					: $data->stateName,
			'postalCode' => $data->zipCode,
			//'countryName' => $data->country ? $data->country->name : '',
			'countryCodeAlpha2' => $data->country ? $data->country->iso : '',
		];
	}

	public function format3DSAddress($order)
	{
		if (get_class($order) == 'craft\\commerce\\models\\Address') {
			$address = $order;
		} else {
			$address = $order->billingAddress ?: $order->shippingAddress;
		}

		if (!$address) {
			return [];
		}

		return [
			'givenName' => $address->firstName,
			'surname' => $address->lastName,
			'phoneNumber' => preg_replace('/[()\s-]/', '', $address->phone),
			'streetAddress' => StringHelper::safeTruncate(
				$address->address1,
				50
			),
			'extendedAddress' => StringHelper::safeTruncate(
				$address->address2,
				50
			),
			'locality' => StringHelper::safeTruncate($address->city, 50),
			'region' => $address->state
				? $address->state->abbreviation
				: $address->stateName,
			'postalCode' => $address->zipCode,
			'countryCodeAlpha2' => $address->country
				? $address->country->iso
				: '',
		];
	}
}
