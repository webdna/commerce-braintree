<?php

namespace webdna\commerce\braintree\assetbundles\expresscheckout;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class ApplePayAsset extends AssetBundle
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{

		$this->sourcePath = __DIR__.'/dist';

		$this->depends = [
			//CpAsset::class,
		];

		$this->js = [
			'js/ApplePay.js',
		];

		$this->css = [
			'css/ApplePay.css',
		];

		parent::init();
	}
}
