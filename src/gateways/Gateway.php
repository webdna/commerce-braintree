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

use Craft;
use craft\commerce\errors\PaymentException;
use kuriousagency\commerce\braintree\assetbundles\dropinui\DropinUiAsset;
use kuriousagency\commerce\braintree\assetbundles\hostedfields\HostedFieldsAsset;
use kuriousagency\commerce\braintree\models\BraintreePaymentForm;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Braintree\Message\Response;
use Omnipay\Omnipay;
use Omnipay\Braintree\Gateway as OmnipayGateway;
use craft\commerce\Plugin as Commerce;


/**
 * Gateway represents Braintree gateway
 *
 * @author    Kurious Agency
 * @package   Braintree
 * @since     1.0.0
 *
 */
class Gateway extends CreditCardGateway
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $merchantId;

    /**
     * @var string
     */
    public $publicKey;

    /**
     * @var boolean
     */
    public $testMode;

    /**
     * @var string
     */
	public $privateKey;
	
	public $merchantAccountId;

    /**
     * @var bool Whether cart information should be sent to the payment gateway
     */
	public $sendCartInfo = false;
	
	private $_gateway;

    // Public Methods
    // =========================================================================

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
    /*public function getPaymentConfirmationFormHtml(array $params): string
    {
        return $this->_displayFormHtml($params, 'commerce-eway/confirmationForm');
    }*/


    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
			'gateway' => $this,
			'paymentForm' => $this->getPaymentFormModel(),
		];
		//Craft::dd($this->getPaymentFormModel());

		$params = array_merge($defaults, $params);

		if(Craft::$app->getRequest()->getParam('orderId')) {
			$order = Commerce::getInstance()->getOrders()->getOrderById(Craft::$app->getRequest()->getParam('orderId'));
			$params['order'] = $order;
		}
		//Craft::dd($params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

		//$view->registerJsFile('https://js.braintreegateway.com/web/dropin/1.13.0/js/dropin.min.js');
		$view->registerJsFile('https://js.braintreegateway.com/web/3.39.0/js/client.min.js');
		$view->registerJsFile('https://js.braintreegateway.com/web/3.39.0/js/hosted-fields.min.js');
		$view->registerAssetBundle(HostedFieldsAsset::class);
		/*$script = '
			alert("bob");
		';
		$view->registerScript($script, 1);*/

        $html = $view->renderTemplate('commerce-braintree/paymentForm', $params);
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
				$customer = $this->createGateway()->findCustomer($user->uid)->send();
			} catch (\Braintree_Exception_NotFound $e) {
				$customer = null;
			}
			
			if (!$customer) {
				$customer = $this->createGateway()->createCustomer()->sendData([
					'id' => $user->uid,
					'firstName' => $user->firstName,
					'lastName' => $user->lastName,
					'email' => $user->email,
				]);
			}
			$params['customerId'] = $user->uid;

		}
		$token = $this->createGateway()->clientToken($params)->send()->getToken();
		
		return $token;
	}

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new BraintreePaymentForm();
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
    public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
    {
        if ($paymentForm && $paymentForm->hasProperty('nonce') && $paymentForm->nonce) {
            $request['token'] = $paymentForm->nonce;
		}
		$request['merchantAccountId'] = $this->merchantAccountId[$request['currency']];
		//Craft::dd($request);
	}

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
		/** @var OmnipayGateway $gateway */
		if ($this->_gateway == null) {
			$this->_gateway = Omnipay::create($this->getGatewayClassName());

			$this->_gateway->setMerchantId($this->merchantId);
			$this->_gateway->setPublicKey($this->publicKey);
			$this->_gateway->setPrivateKey($this->privateKey);
			$this->_gateway->setTestMode($this->testMode);
		}

        return $this->_gateway;
    }


    /**
     * @inheritdoc
     */
    // protected function extractPaymentSourceDescription(ResponseInterface $response): string
    // {
    //     $data = $response->getData();

    //     return Craft::t('commerce-eway', 'Payment card {masked}', ['masked' => $data['Customer']['CardDetails']['Number']]);
    // }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        return '\\'.OmnipayGateway::class;
    }

}