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

use craft\commerce\base\Plan as BasePlan;
use craft\commerce\base\PlanInterface;
use craft\helpers\Json;

/**
 * Stripe Payment form model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Plan extends BasePlan
{
    /**
     * @inheritdoc
     */
    public function canSwitchFrom(PlanInterface $currentPlant): bool
    {
        return false;
	}

	public function description(): string
	{
		return $this->_getData()->description;
	}

	public function price(): string
	{
		return $this->_getData()->price;
	}

	public function frequency(): number
	{
		return $this->_getData()->billingFrequency;
	}

	public function discounts()
	{
		return $this->_getData()->discounts;
	}
	
	// trialing
	public function canTrial(): bool
	{
		return $this->_getData()->trialPeriod;
	}
	public function trialPeriod(): string
	{
		return $this->_getData()->trialDuration.' '.$this->_getData()->trialDurationUnit.' trial';
	}


	private function _getData()
	{
		return (object)Json::decode($this->planData);
	}
}
