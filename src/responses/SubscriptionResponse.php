<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace webdna\commerce\braintree\responses;

use Craft;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\errors\NotImplementedException;
use craft\helpers\DateTimeHelper;
use yii\base\InvalidConfigException;

class SubscriptionResponse implements SubscriptionResponseInterface
{
    /**
     * @var
     */
    protected object $data;
    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct(object $data)
    {
        $this->data = $data;
    }
    /**
     * @inheritdoc
     */
    public function getReference(): string
    {
        if (empty($this->data)) {
            return '';
        }
        return (string)$this->data->id;
    }
    /**
     * @inheritdoc
     */
    public function getData(): mixed
    {
		return json_encode($this->data);
    }
    /**
     * @inheritdoc
     */
    public function getTrialDays(): int
    {
        if (empty($this->data)) {
            return 0;
		}
		
		return (int)$this->data->trialDuration;
    }
    /**
     * @inheritdoc
     * @throws InvalidConfigException if no data
     */
    public function getNextPaymentDate(): \DateTime
    {
        if (empty($this->data)) {
            throw new InvalidConfigException();
		}
		return $this->data->nextBillingDate;
        //$timestamp = $this->data['current_period_end'];
        //return DateTimeHelper::toDateTime($timestamp);
    }
    /**
     * @inheritdoc
     */
    public function isCanceled(): bool
    {
        return $this->data->status === 'Canceled';
    }
    /**
     * @inheritdoc
     */
    public function isScheduledForCancellation(): bool
    {
		if ($this->data->paidThroughDate) {
			$now = new \DateTime();
			if ($now->getTimestamp() < $this->data->paidThroughDate->getTimestamp() && $this->data->status === 'Canceled') {
				return true;
			}
		}
		return false;
        //return (bool)$this->data['cancel_at_period_end'];
	}
	
	 /**
     * @inheritdoc
     */
    public function isInactive(): bool
    {
        return false;
	}
	
}