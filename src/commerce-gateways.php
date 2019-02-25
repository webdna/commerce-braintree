<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

/**
 * commerce-gateways.php
 *
 * This file exists only as a template for the Commerce gateways settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'commerce-gateways.php', if the file already exists then add these setting to it.
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [

	'braintree' => [ // the gateway handle
		'merchantId' => '',
		'publicKey' => '',
		'privateKey' => '',
		'testMode' => false,

		// if you have multiple merchant accounts setup in your braintree account,
		// then here is where you can map payment currencies to those accounts.
		// paymentCurrency ISO => merchantAccountId
		'merchantAccountId' => [
			'USD' => '',
			'EUR' => '',
			'GBP' => '',
		],
	],

];
