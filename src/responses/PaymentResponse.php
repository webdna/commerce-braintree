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
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;

class PaymentResponse implements RequestResponseInterface
{
    /**
     * @var
     */
    protected object $data;
    /**
     * @var string
     */
    private string $_redirect = '';
    /**
     * @var bool
     */
    private bool $_processing = false;
    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct(object $data)
    {
        $this->data = $data;
    }
    public function setRedirectUrl(string $url): void
    {
        $this->_redirect = $url;
    }
    public function setProcessing(bool $status): void
    {
        $this->_processing = $status;
    }
    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
		if (isset($this->data->success)) {
			return $this->data->success;
		}

        return false;
    }
    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return $this->_processing;
    }
    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return !empty($this->_redirect);
    }
    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }
    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }
    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return $this->_redirect;
    }
    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return $this->transactionValue('id');
    }
    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->transactionValue('status');
    }
    /**
     * @inheritdoc
     */
    public function getData(): mixed
    {
        return $this->data;
    }
    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
		if (isset($this->data->message) && $this->data->message) {
			return $this->data->message;
		}

		return '';
    }
    /**
     * @inheritdoc
     */
    public function redirect(): void
    {
        throw new NotImplementedException('Redirecting directly is not implemented for this gateway.');
	}
	

	private function transactionValue($key): string
	{
		if (isset($this->data->transaction) && $this->data->transaction && isset($this->data->transaction->$key)) {
			return $this->data->transaction->$key;
		}

		return '';
	}
}