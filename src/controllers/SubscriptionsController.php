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

use craft\commerce\elements\Subscription;
use craft\commerce\Plugin as Commerce;
use webdna\commerce\braintree\gateways\Gateway;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * @author    Kurious Agency
 * @package   Affiliate
 * @since     1.0.0
 */
class SubscriptionsController extends Controller
{

	public function actionUpdatePayment(): ?Response
	{
		$this->requirePostRequest();
		$this->requireLogin();

		$request = Craft::$app->getRequest();

		$subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');
		$planUid = $request->getValidatedBodyParam('planUid');

		$subscription = $subscriptionUid ? Subscription::find()->uid($subscriptionUid)->one() : null;
		$plan = $planUid ? Commerce::getInstance()->getPlans()->getPlanByUid($planUid) : null;

		// Nothing is sent to Braintree until the subscription is this user's and the plan is its gateway's
		if (
			!$subscription
			|| (int) $subscription->userId !== (int) Craft::$app->getUser()->getId()
			|| !$subscription->getGateway() instanceof Gateway
			|| !$plan
			|| (int) $plan->gatewayId !== (int) $subscription->gatewayId
		) {
			return $this->refuse();
		}

		$gateway = $subscription->getGateway();
		$paymentForm = $gateway->getPaymentFormModel();
        $paymentForm->setAttributes($request->getBodyParams(), false);

		$response = $gateway->updateSubscriptionPayment($subscription,$plan,$gateway,$paymentForm);

		$subscription->planId = $plan->id;
        $subscription->nextPaymentDate = $response->getNextPaymentDate();
        $subscription->subscriptionData = $response->getData();
        $subscription->isCanceled = false;
        $subscription->isExpired = false;

		Craft::$app->getElements()->saveElement($subscription);

		Craft::$app->getSession()->setNotice('Your card details have been updated');
		// Craft::$app->getSession()->setError($response['message']);

		return $this->redirectToPostedUrl();

	}

	private function refuse(): ?Response
	{
		$message = Craft::t('commerce-braintree', 'You are not allowed to change this subscription.');

		if (!Craft::$app->getRequest()->getAcceptsJson()) {
			throw new ForbiddenHttpException($message);
		}

		$response = $this->asFailure($message);
		$this->response->setStatusCode(403);

		return $response;
	}

}
