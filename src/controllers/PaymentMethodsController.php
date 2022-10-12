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
use yii\web\Response;

/**
 * @author    Kurious Agency
 * @package   Affiliate
 * @since     1.0.0
 */
class PaymentMethodsController extends Controller
{

	public function actionDelete(): Response
	{
		
		$request = Craft::$app->getRequest();
		
		$handle = $request->getBodyParam('gateway');
		$token = $request->getBodyParam('token');
		
		$gateway = Commerce::getInstance()->getGateways()->getGatewayByHandle($handle);

		$gateway->deletePaymentMethod($token);

		return $this->redirectToPostedUrl();

	}

}