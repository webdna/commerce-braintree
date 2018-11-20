<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\braintree;

use kuriousagency\commerce\braintree\models\Settings;
use kuriousagency\commerce\braintree\assetbundles\dropinui\DropinUiAsset;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;

use kuriousagency\commerce\braintree\gateways\Gateway;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;

use yii\base\Event;

require_once(__DIR__.'/assetbundles/dropinui/DropinUiAsset.php');
require_once(__DIR__.'/assetbundles/hostedfields/HostedFieldsAsset.php');

/**
 * Class Braintree
 *
 * @author    Kurious Agency
 * @package   Braintree
 * @since     1.0.0
 *
 */
class Braintree extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Braintree
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
		self::$plugin = $this;
		
		Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES,  function(RegisterComponentTypesEvent $event) {
            $event->types[] = Gateway::class;
        });

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'commerce-braintree',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'commerce-braintree/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
