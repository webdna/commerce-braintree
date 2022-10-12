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
use yii\web\Response;

/**
 * @author    Kurious Agency
 * @package   Affiliate
 * @since     1.0.0
 */
class SubscriptionsController extends Controller
{

	public function actionUpdatePayment(): Response
	{
		
		$request = Craft::$app->getRequest();
		
		$subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');
		$planUid = $request->getValidatedBodyParam('planUid');

		$subscription = Subscription::find()->uid($subscriptionUid)->one();
		$plan = Commerce::getInstance()->getPlans()->getPlanByUid($planUid);

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

}