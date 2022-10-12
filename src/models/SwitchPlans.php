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

use craft\commerce\models\subscriptions\SwitchPlansForm;


class SwitchPlans extends SwitchPlansForm
{
    /**
     * Whether plan change should be prorated
     *
     * @var int
     */
    public $prorate = false;
    /**
     * @var bool whether the plan change should be billed immediately.
     */
    public $billImmediately = false;
}
