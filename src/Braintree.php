<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace webdna\commerce\braintree;

use webdna\commerce\braintree\models\Settings;
use webdna\commerce\braintree\assetbundles\dropinui\DropinUiAsset;
use webdna\commerce\braintree\gateways\Gateway;
use webdna\commerce\braintree\services\BraintreeService;

use Braintree as BT;

use Craft;
use craft\base\Plugin;
use craft\base\Model;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;

use yii\log\Logger;
use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;

use yii\base\Event;

require_once __DIR__ . '/assetbundles/dropinui/DropinUiAsset.php';
require_once __DIR__ . '/assetbundles/hostedfields/HostedFieldsAsset.php';

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
	public string $schemaVersion = '1.0.0';
	
	/**
	 * @var bool
	 */
	public bool $hasCpSettings = false;
	
	/**
	 * @var bool
	 */
	public bool $hasCpSection = false;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		self::$plugin = $this;
			
		$this->setComponents([
			'braintreeService' => BraintreeService::class,
		]);

		Event::on(
			Gateways::class,
			Gateways::EVENT_REGISTER_GATEWAY_TYPES,
			function (RegisterComponentTypesEvent $event) {
				$event->types[] = Gateway::class;
			}
		);

		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_INSTALL_PLUGIN,
			function (PluginEvent $event) {
				if ($event->plugin === $this) {
				}
			}
		);

		Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
			'name' => 'braintree',
			'categories' => ['braintree'],
			'logContext' => false,
			'allowLineBreaks' => true,
			'formatter' => new LineFormatter(
				format: "%datetime% %message%\n",
				dateFormat: 'Y-m-d H:i:s',
				allowInlineLineBreaks: true,
			),
		]);

		Craft::info(
			Craft::t('commerce-braintree', '{name} plugin loaded', [
				'name' => $this->name,
			]),
			__METHOD__
		);
	}

	public static function log($message)
	{
		Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'braintree');
	}

	public static function error($message)
	{
		Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'braintree');
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	protected function createSettingsModel(): ?Model
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
				'settings' => $this->getSettings(),
			]
		);
	}
}
