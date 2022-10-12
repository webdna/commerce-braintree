<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace webdna\commerce\braintree\assetbundles\hostedfields;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Kurious Agency
 * @package   CommerceBraintree
 * @since     1.0.0
 */
class HostedFieldsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
		//$this->sourcePath = "@kuriousagency/commerce-braintree/assetbundles/dropinui/dist";
		$this->sourcePath = __DIR__.'/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/HostedFields.js',
        ];

        $this->css = [
            'css/HostedFields.css',
        ];

        parent::init();
    }
}
