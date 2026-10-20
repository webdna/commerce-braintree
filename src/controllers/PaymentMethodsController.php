<?php
/**
 * Affiliate plugin for Craft CMS 3.x
 *
 * Plugin to add affiliates to Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace webdna\commerce\braintree\controllers;

use Craft;
use craft\web\Controller;
use webdna\commerce\braintree\gateways\Gateway;

use craft\commerce\Plugin as Commerce;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * @author    Kurious Agency
 * @package   Affiliate
 * @since     1.0.0
 */
class PaymentMethodsController extends Controller
{

	public function actionDelete(): ?Response
	{
		$this->requirePostRequest();
		$this->requireLogin();

		$request = Craft::$app->getRequest();

		$handle = (string) $request->getBodyParam('gateway');
		$token = (string) $request->getBodyParam('token');

		$gateway = Commerce::getInstance()->getGateways()->getGatewayByHandle($handle);

		// Nothing is deleted in Braintree until the method is known to be this user's
		if (!$gateway instanceof Gateway || $token === '' || !$this->isUsersPaymentMethod($gateway, $token)) {
			return $this->refuse();
		}

		if (!$gateway->deletePaymentMethod($token)) {
			return $this->asFailure(Craft::t('commerce-braintree', 'Could not delete the payment method.'));
		}

		$paymentSources = Commerce::getInstance()->getPaymentSources();
		$paymentSource = $paymentSources->getPaymentSourceByTokenAndGatewayId($token, $gateway->id);
		if ($paymentSource) {
			$paymentSources->deletePaymentSourceById($paymentSource->id);
		}

		return $this->asSuccess(Craft::t('commerce-braintree', 'Payment method deleted.'));
	}

	private function isUsersPaymentMethod(Gateway $gateway, string $token): bool
	{
		try {
			$method = $gateway->getPaymentMethod($token);
		} catch (\Braintree\Exception\NotFound $e) {
			return false;
		}

		// Braintree customers are keyed by the Craft user's uid (Gateway::getToken())
		return ($method->customerId ?? null) === Craft::$app->getUser()->getIdentity()->uid;
	}

	private function refuse(): ?Response
	{
		$message = Craft::t('commerce-braintree', 'You are not allowed to change this payment method.');

		if (!Craft::$app->getRequest()->getAcceptsJson()) {
			throw new ForbiddenHttpException($message);
		}

		$response = $this->asFailure($message);
		$this->response->setStatusCode(403);

		return $response;
	}

}
