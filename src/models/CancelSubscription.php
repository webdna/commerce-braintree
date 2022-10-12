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

use craft\commerce\models\subscriptions\CancelSubscriptionForm as BaseCancelSubscriptionForm;

/**
 * Stripe cancel subscription form model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class CancelSubscription extends BaseCancelSubscriptionForm
{
    /**
     * @var bool whether the subscription should be canceled immediately
     */
    public $cancelImmediately = false;
}
