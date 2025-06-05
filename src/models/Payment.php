<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace webdna\commerce\braintree\models;

use webdna\commerce\braintree\Braintree;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;

use Craft;
use craft\base\Model;

/**
 * @author    Kurious Agency
 * @package   CommerceBraintree
 * @since     1.0.0
 */
class Payment extends BasePaymentForm
{
    /**
     * @var string credit card reference
     */
    public $nonce;
    public $deviceData;
    public $token;
    public $type;
    public $firstName;
    public $lastName;
    public $number;
    public $expiry;
    public $cvv;
    public $paymentMethod;
    public $default;

    //public $amount;

    /**
     * @inheritdoc
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
        $this->token = $paymentSource->token;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        if (empty($this->nonce) || empty($this->token)) {
            return parent::rules();
        }

        return [];
    }
}
