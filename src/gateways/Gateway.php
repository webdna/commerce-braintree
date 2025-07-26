<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace webdna\commerce\braintree\gateways;

use webdna\commerce\braintree\Braintree as BT;

use webdna\commerce\braintree\assetbundles\dropinui\DropinUiAsset;
use webdna\commerce\braintree\assetbundles\hostedfields\HostedFieldsAsset;
use webdna\commerce\braintree\assetbundles\expresscheckout\GooglePayAsset;
use webdna\commerce\braintree\assetbundles\expresscheckout\ApplePayAsset;
use webdna\commerce\braintree\models\Payment;
use webdna\commerce\braintree\models\CancelSubscription;
use webdna\commerce\braintree\models\Plan;
use webdna\commerce\braintree\models\SwitchPlans;
use webdna\commerce\braintree\responses\PaymentResponse;
use webdna\commerce\braintree\responses\SubscriptionResponse;

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
use craft\helpers\App;
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

	public string $apiUrl = '';

	private ?string $_merchantId = null;

	private ?string $_publicKey = null;

	private ?string $_privateKey = null;

	private bool|string $_testMode = false;

	private array $_merchantAccountIds = [];
	
	private array $_siteOverrides = [];

	private bool $_sendCartInfo = false;

	private ?string $_googlePayMerchantId = null;

	private ?Braintree\Gateway $gateway = null;

	private ?Braintree\Customer $customer = null;
	
	private string $_dropinUiSDKVersion = '1.44.1';
	
	private string $_clientSDKVersion = '3.111.0';

	// Public Methods
	// =========================================================================

	public function init(): void
	{
		parent::init();

		//BT::log('Braintree init');

		$this->gateway = new Braintree\Gateway([
			'environment' => $this->getTestMode() ? 'sandbox' : 'production',
			'merchantId' => $this->getMerchantId(),
			'publicKey' => $this->getPublicKey(),
			'privateKey' => $this->getPrivateKey(),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		return Craft::t('commerce', 'Braintree');
	}
	
	
	public function getSettings(): array
	{
		$settings = parent::getSettings();
		$settings['merchantId'] = $this->getMerchantId(false);
		$settings['publicKey'] = $this->getPublicKey(false);
		$settings['privateKey'] = $this->getPrivateKey(false);
		$settings['testMode'] = $this->getTestMode(false);
		$settings['merchantAccountIds'] = $this->_merchantAccountIds;
		$settings['siteOverrides'] = $this->_siteOverrides;
		$settings['googlePayMerchantId'] = $this->getGooglePayMerchantId(false);
		$settings['dropinUiSDKVersion'] = $this->getDropinUiSDKVersion(false);
		$settings['clientSDKVersion'] = $this->getClientSDKVersion(false);
		
		return $settings;
	}
	
	
	
	
	public function getTestMode(bool $parse = true): bool|string
	{
		return $parse ? App::parseBooleanEnv($this->_testMode) : $this->_testMode;
	}
	
	public function setTestMode(bool|string $testMode): void
	{
		$this->_testMode = $testMode;
	}
	
	
	public function getMerchantId(bool $parse = true): ?string
	{
		return $parse ? App::parseEnv($this->_merchantId) : $this->_merchantId;
	}
	
	public function setMerchantId(?string $merchantId): void
	{
		$this->_merchantId = $merchantId;
	}
	
	
	public function getPublicKey(bool $parse = true): ?string
	{
		return $parse ? App::parseEnv($this->_publicKey) : $this->_publicKey;
	}
	
	public function setPublicKey(?string $publicKey): void
	{
		$this->_publicKey = $publicKey;
	}
	
	
	public function getPrivateKey(bool $parse = true): ?string
	{
		return $parse ? App::parseEnv($this->_privateKey) : $this->_privateKey;
	}
	
	public function setPrivateKey(?string $privateKey): void
	{
		$this->_privateKey = $privateKey;
	}
	
	
	public function getMerchantAccountId(string $currency, $site=null, bool $parse = true): ?string
	{
		if ($site) {
			$override = $this->getSiteOverrides($currency, $site->uid)['merchantAccountId'] ?? '';
			if ($override != '') {
				return $parse ? App::parseEnv($override) : $override;
			}
		}
		
		if (empty($this->_merchantAccountIds[$currency])) {
			return null;
		}
		return $parse ? App::parseEnv($this->_merchantAccountIds[$currency]) : $this->_merchantAccountIds[$currency];
	}
	
	public function setMerchantAccountId(string $currency, ?string $merchantAccountId): void
	{
		$this->_merchantAccountIds[$currency] = $merchantAccountId;
	}
	
	public function setMerchantAccountIds(array $merchantAccountIds): void
	{
		$this->_merchantAccountIds = $merchantAccountIds;
	}
	
	public function getSiteOverrides(string $currency, string $site, bool $parse = true): ?array
	{
		if (empty($this->_siteOverrides[$currency][$site])) {
			return [];
		}
		return $parse ? collect($this->_siteOverrides[$currency][$site])->map(function($o){ return App::parseEnv($o); })->toArray() : $this->_siteOverrides[$currency][$site];
	}
	
	public function setSiteOverrides(array $siteOverrides): void
	{
		$this->_siteOverrides = $siteOverrides;
	}
	
	
	public function getGooglePayMerchantId(bool $parse = true): ?string
	{
		return $parse ? App::parseEnv($this->_googlePayMerchantId) : $this->_googlePayMerchantId;
	}
	
	public function setGooglePayMerchantId(?string $googlePayMerchantId): void
	{
		$this->_googlePayMerchantId = $googlePayMerchantId;
	}
	
	
	public function getDropinUiSDKVersion(bool $parse = true): ?string
	{
		return $parse ? App::parseEnv($this->_dropinUiSDKVersion) : $this->_dropinUiSDKVersion;
	}
	
	public function setDropinUiSDKVersion(?string $version): void
	{
		$this->_dropinUiSDKVersion = $version;
	}
	
	public function getClientSDKVersion(bool $parse = true): ?string
	{
		return $parse ? App::parseEnv($this->_clientSDKVersion) : $this->_clientSDKVersion;
	}
	
	public function setClientSDKVersion(?string $version): void
	{
		$this->_clientSDKVersion = $version;
	}
	
	
	
	

	/**
	 * @inheritdoc
	 */
	public function getPaymentFormHtml(array $params = []): string
	{
		$request = Craft::$app->getRequest();

		if ($request->isCpRequest) {
			return $this->getCpPaymentFormHtml($params);
		} else {
			return $this->getSitePaymentFormHtml($params);
		}
	}

	public function getToken($user = null, $currency = null, $site=null): string
	{
		//$omnipayGateway = $this->createGateway();
		$params = [];
		
		if ($currency) {
			$params['merchantAccountId'] = $this->getMerchantAccountId($currency, $site);
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
	public function getSettingsHtml(): string
	{
		return Craft::$app->getView()->renderTemplate('commerce-braintree/gatewaySettings', [
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

	public function getCustomer($user): ?Braintree\Customer
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

	public function getPaymentMethod($token): mixed
	{
		return $this->gateway->paymentMethod()->find($token);
	}

	public function createPaymentMethod(BasePaymentForm $sourceData,int $userId): mixed
	{
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

	public function deletePaymentMethod($token): bool
	{
		$result = $this->gateway->paymentMethod()->delete($token);

		return $result->success;
	}

	public function authorize(Transaction $transaction,BasePaymentForm $form): RequestResponseInterface
	{
		//Craft::dd($transaction);
		try {
			$order = $transaction->getOrder();
			$data = [
				'amount' => $transaction->paymentAmount,
				'orderId' => $order->shortNumber,
				'options' => ['submitForSettlement' => false],
			];

			if ($order->customer) {
				if ($this->getCustomer($order->customer)) {
					$data['customerId'] = $order->customer->uid;
				} else {
					$data['customer'] = [
						'firstName' => $order->customer->firstName,
						'lastName' => $order->customer->lastName,
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
			if ($form->deviceData) {
				$data['deviceData'] = $form->deviceData;
			}
			if ($merchantAccountId = $this->getMerchantAccountId($transaction->currency, $order->orderSite)) {
				$data['merchantAccountId'] = $merchantAccountId;
			} else {
				$data['merchantAccountId'] = "";
				$data['amount'] = $transaction->amount;
			}
			if ($form->type != "PayPalAccount") {
				if ($order->billingAddress || $order->shippingAddress) {
					$data['billing'] = $this->_formatAddress($order->billingAddress ?: $order->shippingAddress);
				}
				if ($order->shippingAddress) {
					$data['shipping'] = $this->_formatAddress($order->shippingAddress);
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
			BT::error('The payment could not be processed (' . get_class($exception) . ')');
			throw new PaymentException('The payment could not be processed (' . get_class($exception) . ')');
		}
	}

	public function capture(Transaction $transaction,string $reference): RequestResponseInterface
	{
		//Craft::dd($transaction);
		try {
			$result = $this->gateway->transaction()->submitForSettlement($reference);
			return new PaymentResponse($result);
		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	public function completeAuthorize(Transaction $transaction): RequestResponseInterface
	{
	}

	public function createPaymentSource(BasePaymentForm $sourceData,int $userId): PaymentSource
	{
		//Craft::dd($sourceData);
		try {
			$response = $this->createPaymentMethod($sourceData, $userId);
			//Craft::dd($response);
			if (!$response->success) {
				throw new PaymentSourceException($response->message);
			}

			//check for existing paymentSource
			$sources = Commerce::getInstance()->getPaymentSources()->getAllGatewayPaymentSourcesByUserId($this->id, $userId);

			foreach ($sources as $source) {
				if ($source->token == $response->paymentMethod->token) {
					Commerce::getInstance()->getPaymentSources()->deletePaymentSourceById($source->id);
				}
			}

			$description = Craft::t('commerce-braintree','{cardType} ending in ••••{last4}',
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

	public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
		//Craft::dd($transaction);
		try {
			$order = $transaction->getOrder();
			$data = [
				'amount' => $transaction->paymentAmount,
				'orderId' => $order->shortNumber,
				'options' => ['submitForSettlement' => true],
			];

			if ($order->customer) {
				if ($this->getCustomer($order->customer)) {
					$data['customerId'] = $order->customer->uid;
				} else {
					$data['customer'] = [
						'firstName' => $order->customer->firstName,
						'lastName' => $order->customer->lastName,
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
			if ($form->deviceData) {
				$data['deviceData'] = $form->deviceData;
			}
			if ($merchantAccountId = $this->getMerchantAccountId($transaction->currency, $order->orderSite)) {
				$data['merchantAccountId'] = $merchantAccountId;
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
			BT::error('The payment could not be processed (' .get_class($exception) .')');
			throw new PaymentException('The payment could not be processed (' .get_class($exception) .')');
		}
	}

	public function completePurchase(Transaction $transaction): RequestResponseInterface
	{
	}

	public function createSale($data)
	{
		return (object) $this->gateway->transaction()->sale($data);
	}

	public function refund(Transaction $transaction): RequestResponseInterface
	{
		//Craft::dd($transaction);
		try {
			$result = $this->gateway->transaction()->refund($transaction->reference, $transaction->amount);
			return new PaymentResponse($result);
		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	// Subscriptions
	public function cancelSubscription(Subscription $subscription, BaseCancelSubscriptionForm $parameters): SubscriptionResponseInterface
	{
		$response = $this->gateway
			->subscription()
			->cancel($subscription->reference);

		if ($response->success) {
			// remove paymentsource
			$source = $this->getPaymentSource($subscription->userId, $subscription->subscriptionData['paymentMethodToken']);
			if ($source) {
				// check if any other subscriptions are using this payment source
				$canDelete = true;
				foreach (Subscription::find()->gatewayId($this->id)->userId($subscription->userId)->isCanceled(0)->reference(['not', $subscription->reference])->all() as $sub) {
					if ($sub->subscriptionData['paymentMethodToken'] ==	$source->token) {
						$canDelete = false;
					}
				}
				if ($canDelete) {
					Commerce::getInstance()->getPaymentSources()->deletePaymentSourceById($source->id);
				}
			}

			return new SubscriptionResponse($response->subscription);
		} else {
			foreach ($response->errors->forKey('subscription')->shallowAll() as $error) {
				// subscription has already been cancelled on Braintree but not in the site. So let's cancel on site as well
				if ($error->code == "81905") {
					try {
						$response = $this->gateway->subscription()->find($subscription->reference);
					} catch (Braintree_Exception_NotFound $e) {
						throw new SubscriptionException('Failed to cancel subscription: ' .$subscription->reference .". Error:" .$e->getMessage());
					}

					return new SubscriptionResponse($response);
				}
			}

			throw new SubscriptionException(
				'Failed to cancel subscription: ' . $subscription->reference
			);
		}
	}

	public function getCancelSubscriptionFormHtml(Subscription $subscription): string
	{
		$view = Craft::$app->getView();

		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$html = $view->renderTemplate('commerce-braintree/cancelSubscriptionForm');
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
		$currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso($subscription->plan->currency);
		return Craft::$app->getFormatter()->asCurrency($data['nextBillingPeriodAmount'], $currency->alphabeticCode);
		//return $data->nextBillingPeriodAmount;
	}

	public function getPlanModel(): BasePlan
	{
		return new Plan();
	}

	public function getPlanSettingsHtml(array $params = []): String
	{
		$params['plans'] = [];
		foreach ($this->getSubscriptionPlans() as $plan) {
			$params['plans'][] = [
				'label' => $plan->name,
				'value' => $plan->id,
			];
		}
		return Craft::$app->getView()->renderTemplate('commerce-braintree/planSettings', $params);
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

	public function refreshPaymentHistory(Subscription $subscription): void
	{
		$response = null;

		try {
			$response = $this->gateway->subscription()->find($subscription->reference);
		} catch (\Braintree_Exception_NotFound $e) {
			throw new SubscriptionException('Failed to refresh payment history for subscription: ' .$subscription->reference);
		}

		if ($response) {
			$subscription->setSubscriptionData(Json::encode($response));
			$subscription->nextPaymentDate = $response->nextBillingDate;

			Craft::$app->getElements()->saveElement($subscription);
		}

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

	public function createSubscription($data): mixed
	{
		return (object) $this->gateway->subscription()->create($data);
	}

	public function subscribe(User $user, BasePlan $plan, SubscriptionForm $parameters): SubscriptionResponseInterface
	{
		$source = $this->getPaymentSource($user->id);
		if (!$source) {
			throw new PaymentSourceException(Craft::t('commerce-braintree','No payment sources are saved to use for subscriptions.'));
		}
		$plan = new Plan($plan);

		$data = [
			'paymentMethodToken' => $source->token,
			'planId' => $plan->reference,
			'price' => $plan->price,
			'merchantAccountId' => $this->getMerchantAccountId($plan->getCurrency()),
		];

		if ($parameters->trialDays > 0) {
			$data['trialPeriod'] = 1;
			$data['trialDurationUnit'] = 'day';
			$data['trialDuration'] = $parameters->trialDays;
		}

		$response = $this->createSubscription($data);

		if (!$response->success) {
			//Craft::dd($response);
			throw new SubscriptionException(
				Craft::t('commerce-braintree', 'Unable to subscribe at this time.')
			);
		}

		return new SubscriptionResponse($response->subscription);
	}

	public function getSwitchPlansFormHtml(PlanInterface $originalPlan, PlanInterface $targetPlan): string 
	{
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

	public function switchSubscriptionPlan(Subscription $subscription, BasePlan $plan, SwitchPlansForm $parameters): SubscriptionResponseInterface
	{
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
		$response = $this->gateway->subscription()->update($subscription->reference, $params);

		if (!$response->success) {
			throw new SubscriptionException(
				Craft::t('commerce-braintree', 'Unable to subscribe at this time.')
			);
		}

		return new SubscriptionResponse($response->subscription);
	}

	public function updateSubscriptionPayment(Subscription $subscription, BasePlan $plan, $gateway, $paymentForm): SubscriptionResponseInterface
	{
		$userId = Craft::$app->getUser()->getId();
		$description = "";

		$oldSource = $this->getPaymentSource($subscription->userId, $subscription->subscriptionData['paymentMethodToken']);
		$source = Commerce::getInstance()->getPaymentSources()->createPaymentSource($userId, $gateway, $paymentForm, $description);

		$params = [
			'paymentMethodToken' => $source->token,
			'price' => $plan->price,
			'planId' => $plan->reference,
		];

		$response = $this->gateway->subscription()->update($subscription->reference, $params);

		if (!$response->success) {
			throw new SubscriptionException(
				Craft::t('commerce-braintree', 'Unable to unpdate subscription at this time.')
			);
		} else {
			// remove paymentsource

			if ($oldSource) {
				// check ifany other subscriptions are using this payment source
				$canDelete = true;
				foreach (Subscription::find()->gatewayId($this->id)->userId($subscription->userId)->isCanceled(0)->reference(['not', $subscription->reference])->all() as $sub) {
					if ($sub->subscriptionData['paymentMethodToken'] ==	$oldSource->token) {
						$canDelete = false;
					}
				}
				if ($canDelete) {
					Commerce::getInstance()->getPaymentSources()->deletePaymentSourceById($oldSource->id);
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
	public function getBillingIssueDescription(Subscription $subscription): string 
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getBillingIssueResolveFormHtml(Subscription $subscription): string 
	{
		throw new NotSupportedException();
	}

	public function processWebHook(): WebResponse
	{
		$signature = Craft::$app->getRequest()->getRequiredParam('bt_signature');
		$payload = Craft::$app->getRequest()->getRequiredParam('bt_payload');

		$webhookNotification = $this->gateway->webhookNotification()->parse($signature, $payload);

		switch ($webhookNotification->kind) {
			case 'subscription_canceled':
			case 'subscription_expired':
			case 'subscription_went_past_due':
				$this->_handleSubscriptionExpired($webhookNotification->subscription);
				break;
			case 'subscription_charged_successfully':
				$this->_handleSubscriptionCharged($webhookNotification->subscription);
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

	private function getPaymentSource($userId, $token = null): mixed
	{
		$sources = Commerce::getInstance()->getPaymentSources()->getAllGatewayPaymentSourcesByUserId($this->id, $userId);

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

	private function getCpPaymentFormHtml(array $params = []): string
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
			$order = Commerce::getInstance()->getOrders()->getOrderById($orderId);
		} else {
			$order = Commerce::getInstance()->getCarts()->getCart();
		}
		$params['order'] = $order;

		$view = Craft::$app->getView();
		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$view->registerJsFile("https://js.braintreegateway.com/web/{$this->getClientSDKVersion()}/js/client.min.js");
		$view->registerJsFile("https://js.braintreegateway.com/web/{$this->getClientSDKVersion()}/js/hosted-fields.min.js");
		$view->registerAssetBundle(HostedFieldsAsset::class);
		$html = $view->renderTemplate('commerce-braintree/paymentForms/hosted-fields', $params);

		$view->setTemplateMode($previousMode);

		return $html;
	}

	private function getSitePaymentFormHtml(array $params = []): string
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
				'googlePayMerchantId' => $this->getGooglePayMerchantId(),
			],
			$params
		);

		$orderId = $request->getParam('number');
		if ($orderId) {
			$order = Commerce::getInstance()->getOrders()->getOrderByNumber($orderId);
		} else {
			$order = Commerce::getInstance()->getCarts()->getCart();
		}
		$params['order'] = $order;

		$view = Craft::$app->getView();
		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		$view->registerJsFile("https://js.braintreegateway.com/web/dropin/{$this->getDropinUiSDKVersion()}/js/dropin.min.js");
		$view->registerAssetBundle(DropinUiAsset::class);
		$html = $view->renderTemplate('commerce-braintree/paymentForms/dropin-ui', $params);

		$view->setTemplateMode($previousMode);

		return TemplateHelper::raw($html);
	}
	
	public function getExpressCheckoutHtml(array $params = []): string
	{
		$request = Craft::$app->getRequest();
	
		$params = array_merge(
			[
				'gateway' => $this,
				'paymentForm' => $this->getPaymentFormModel(),
				'googlePayMerchantId' => $this->getGooglePayMerchantId(),
				'testMode' => $this->getTestMode(),
			],
			$params
		);
	
		$orderId = $request->getParam('number');
		if ($orderId) {
			$order = Commerce::getInstance()->getOrders()->getOrderByNumber($orderId);
		} else {
			$order = Commerce::getInstance()->getCarts()->getCart();
		}
		$params['order'] = $order;
	
		$view = Craft::$app->getView();
		$previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);
		
		$view->registerJsFile("https://js.braintreegateway.com/web/{$this->getClientSDKVersion()}/js/client.min.js");
		
		if ($params['googlePay'] ?? null) {
			$view->registerJsFile("https://pay.google.com/gp/p/js/pay.js");
			$view->registerJsFile("https://js.braintreegateway.com/web/{$this->getClientSDKVersion()}/js/google-payment.min.js");
			$view->registerAssetBundle(GooglePayAsset::class);
		}
		if ($params['applePay'] ?? null) {
			$view->registerJsFile("https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js");
			$view->registerJsFile("https://js.braintreegateway.com/web/{$this->getClientSDKVersion()}/js/apple-pay.min.js");
			$view->registerAssetBundle(ApplePayAsset::class);
		}
		
		$html = $view->renderTemplate('commerce-braintree/paymentForms/express-checkout', $params);
	
		$view->setTemplateMode($previousMode);
	
		return TemplateHelper::raw($html);
	}
	

	private function isPaid($status): bool
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
	private function _createSubscriptionPayment($data, Currency $currency): SubscriptionPayment
	{
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
	private function _handleSubscriptionExpired($data): void
	{
		$subscription = Subscription::find()->reference($data->id)->one();

		if (!$subscription) {
			Craft::warning('Subscription with the reference “' . $data->id . '” not found when processing Braintree webhook');

			return;
		}

		Commerce::getInstance()->getSubscriptions()->expireSubscription($subscription);
	}

	private function _handleSubscriptionCharged($data): void
	{
		$counter = 0;
		$limit = 5;

		do {
			// Handle cases when Braintree sends us a webhook so soon that we haven't processed the subscription that triggered the webhook
			sleep(1);
			$subscription = Subscription::find()->reference($data->id)->one();
			$counter++;
		} while (!$subscription && $counter < $limit);

		if (!$subscription) {
			throw new SubscriptionException('Subscription with the reference “' . $data->id . '” not found when processing braintree webhook');
		}

		$defaultPaymentCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
		$currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso($defaultPaymentCurrency->iso);
		$payment = $this->_createSubscriptionPayment($data, $currency);

		Commerce::getInstance()->getSubscriptions()->receivePayment($subscription, $payment, $data->nextBillingDate);
	}

	private function _formatAddress($data): array
	{
		if (!$data) {
			return [];
		}

		return [
			'firstName' => $data->firstName,
			'lastName' => $data->lastName,
			'company' => StringHelper::safeTruncate(($data->organization ?? ''), 50),
			'streetAddress' => StringHelper::safeTruncate($data->addressLine1, 50),
			'extendedAddress' => StringHelper::safeTruncate(($data->addressLine2 ?? ''), 50),
			'locality' => $data->locality,
			'region' => $data->administrativeArea,
			'postalCode' => $data->postalCode,
			//'countryName' => $data->country ? $data->country->name : '',
			'countryCodeAlpha2' => $data->countryCode ?? '',
		];
	}

	public function format3DSAddress($order): array
	{
		if (get_class($order) == 'craft\\elements\\Address') {
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
			'phoneNumber' => preg_replace('/[()\s-]/', '', ($address->phone ?? '')),
			'streetAddress' => StringHelper::safeTruncate($address->addressLine1, 50),
			'extendedAddress' => StringHelper::safeTruncate(($address->addressLine2 ?? ''),50),
			'locality' => StringHelper::safeTruncate($address->locality, 50),
			'region' => $address->administrativeArea,
			'postalCode' => $address->postalCode,
			'countryCodeAlpha2' => $address->countryCode ?? '',
		];
	}
}
