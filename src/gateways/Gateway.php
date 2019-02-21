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

use kuriousagency\commerce\braintree\assetbundles\dropinui\DropinUiAsset;
use kuriousagency\commerce\braintree\assetbundles\hostedfields\HostedFieldsAsset;
use kuriousagency\commerce\braintree\models\BraintreePaymentForm;
use kuriousagency\commerce\braintree\responses\PaymentResponse;

use Braintree;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\TransactionException;
use craft\commerce\models\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
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

		$this->gateway = new Braintree\Gateway([
			'environment' => $this->testMode ? 'sandbox' : 'production',
			'merchantId' => $this->merchantId,
			'publicKey' => $this->publicKey,
			'privateKey' => $this->privateKey
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
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
			'gateway' => $this,
			'paymentForm' => $this->getPaymentFormModel(),
			'threeDSecure' => false
		];
		//Craft::dd($this->getPaymentFormModel());
		$cpRequest = Craft::$app->getRequest()->isCpRequest;

		$params = array_merge($defaults, $params);

		if(Craft::$app->getRequest()->getParam('orderId')) {
			$order = Commerce::getInstance()->getOrders()->getOrderById(Craft::$app->getRequest()->getParam('orderId'));
			$params['order'] = $order;
		}
		//Craft::dd($params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);
		
		if ($cpRequest) {
			$view->registerJsFile('https://js.braintreegateway.com/web/3.39.0/js/client.min.js');
			$view->registerJsFile('https://js.braintreegateway.com/web/3.39.0/js/hosted-fields.min.js');
			$view->registerAssetBundle(HostedFieldsAsset::class);
			$html = $view->renderTemplate('commerce-braintree/paymentForms/hosted-fields', $params);
		} else {
			$params['order'] = Commerce::getInstance()->getCarts()->getCart();
			$view->registerJsFile('https://js.braintreegateway.com/web/dropin/1.13.0/js/dropin.min.js');
			$view->registerAssetBundle(DropinUiAsset::class);
			$html = $view->renderTemplate('commerce-braintree/paymentForms/dropin-ui', $params);
		}

		//$view->registerJsFile('https://js.braintreegateway.com/web/dropin/1.13.0/js/dropin.min.js');
		
		/*$script = '
			alert("bob");
		';
		$view->registerScript($script, 1);*/

        
        $view->setTemplateMode($previousMode);

		return $html;
	}
	
	public function getToken($user = null, $currency=null)
	{
		//$omnipayGateway = $this->createGateway();
		$params = [];
		if ($currency) {
			$params['merchantAccountId'] = $this->merchantAccountId[$currency];
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
        return Craft::$app->getView()->renderTemplate('commerce-braintree/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    /*public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
    {
        if ($paymentForm && $paymentForm->hasProperty('nonce') && $paymentForm->nonce) {
            $request['token'] = $paymentForm->nonce;
		}
		$request['merchantAccountId'] = $this->merchantAccountId[$request['currency']];
		//Craft::dd($request);
	}*/

	public function getCustomer($user)
	{
		if (!$this->customer) {
			$this->customer = $this->gateway->customer()->find($user->uid);
		}
		return $this->customer;
	}

	public function getPaymentMethod($token)
	{
		return $this->gateway->paymentMethod()->find($token);
	}

	public function setDefaultPaymentMethod($nonce, $user)
	{
		if (!$user) {
			$user = Craft::$app->getUser()->getIdentity();
		}
		
		return $this->gateway->paymentMethod()->create([
			'customerId' => $user->uid,
			'paymentMethodNonce' => $nonce,
			'options' => [
				'makeDefault' => true,
			],
		]);
	}

	/*public function getNonce($token)
	{
		$result = $this->gateway->paymentMethodNonce()->create($token);
		return $result->paymentMethodNonce->nonce;
	}*/


	
	public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{

	}

	public function capture(Transaction $transaction, string $reference): RequestResponseInterface
	{

	}

	public function completeAuthorize(Transaction $transaction): RequestResponseInterface
	{

	}

	public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
	{
		try {
			//$response = $this->getPaymentMethod($sourceData->paymentMethod->token);
			$paymentMethod = $sourceData->paymentMethod;
			//Craft::dd($response);

			$description = Craft::t('commerce-braintree', '{cardType} ending in ••••{last4}', ['cardType' => $paymentMethod->cardType, 'last4' => $paymentMethod->last4]);

			$paymentSource = new PaymentSource([
				'userId' => Craft::$app->getUser()->getId(),
				'gatewayId' => $this->id,
				'token' => $paymentMethod->token,
				'response' => $paymentMethod,
				'description' => $description,
			]);

			return $paymentSource;
		} catch (\Throwable $exception) {
			throw new CommercePaymentSourceException($exception->getMessage());
		}
	}

	public function deletePaymentSource($token): bool
	{
		return true;
	}

	public function getPaymentFormModel(): BasePaymentForm
	{
		return new BraintreePaymentForm();
	}

	public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
		//Craft::dd($transaction);
		try {
			$order = $transaction->getOrder();
			$data = [
				'amount' => $transaction->paymentAmount,
				'orderId' => $order->reference,
				'options' => [ 'submitForSettlement' => true ]
			];
			if ($form->nonce) {
				$data['paymentMethodNonce'] = $form->nonce;
			} elseif ($form->token) {
				$data['paymentMethodToken'] = $form->token;
			}
			if (isset($this->merchantAccountId[$transaction->currency])) {
				$params['merchantAccountId'] = $this->merchantAccountId[$transaction->currency];
			}

			$result = $this->gateway->transaction()->sale($data);

			//Craft::dd($result);

			return new PaymentResponse($result);
			
		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	public function completePurchase(Transaction $transaction): RequestResponseInterface
	{

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

	public function processWebHook(): WebResponse
	{

	}


	public function supportsAuthorize(): bool
	{
		return false;
	}

	public function supportsCapture(): bool
	{
		return false;
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
		return false;
	}


}