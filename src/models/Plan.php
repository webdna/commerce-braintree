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
    public function canSwitchFrom(PlanInterface $currentPlan): bool
    {
		return $this->frequency === $currentPlan->frequency;
	}

	public function getDescription(): string
	{
		return $this->_getData()->description ?: '';
	}

	public function getPrice(): string
	{
		return $this->_getData()->price;
	}

	public function getCurrency(): string
	{
		return $this->_getData()->currencyIsoCode;
	}

	public function getFrequency(): mixed
	{
		return $this->_getData()->billingFrequency;
	}

	public function getDiscounts(): mixed
	{
		return $this->_getData()->discounts;
	}
	
	// trialing
	public function getCanTrial(): bool
	{
		return $this->_getData()->trialPeriod;
	}

	public function getTrialPeriod(): string
	{
		return $this->_getData()->trialDuration.' '.$this->_getData()->trialDurationUnit.' trial';
	}

	private function _getData(): mixed
	{
		return (object)Json::decode($this->planData);
	}
}
