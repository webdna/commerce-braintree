<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\braintree\models;

use kuriousagency\commerce\braintree\Braintree;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;

use Craft;
use craft\base\Model;

/**
 * @author    Kurious Agency
 * @package   CommerceBraintree
 * @since     1.0.0
 */
class BraintreePaymentForm extends BasePaymentForm
{
    /**
     * @var string credit card reference
     */
	public $nonce;
	public $firstName;
	public $lastName;
	public $number;
	public $expiry;
	public $cvv;

	//public $amount;

    /**
     * @inheritdoc
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource)
    {
		$this->nonce = $paymentSource->nonce;
		//$this->amount = $paymentSource->amount;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        if (empty($this->nonce)) {
            return parent::rules();
        }

        return [];
    }
}
