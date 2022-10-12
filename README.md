<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Braintree for Craft Commerce icon"></p>

<h1 align="center">Braintree for Craft Commerce</h1>

This plugin provides a [Braintree](https://www.braintreegateway.com) integration for [Craft Commerce](https://craftcms.com/commerce).

## Features

-   Merchant Accounts: supports Braintree's merchant accounts for multiple payment currencies.
-   Subscriptions: support subscription integration.
-   DropinUi: Braintree's dropinUi is the default for `getPaymentFromHTML()` method.
-   Supports 3DSecure 2.
-   Supports Paypal, Apple Pay & Google Pay.
-   Vault: Supports Braintree's vault for securely storing payment details.

## Requirements

This plugin requires Craft Commerce 4.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for Braintree for Craft Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require webdna/commerce-braintree

# tell Craft to install the plugin
./craft plugin/install commerce-braintree
```

## Setup

To add the Braintree payment gateway, go to Commerce → Settings → Gateways, create a new gateway, and set the gateway type to “Braintree”.

## Payment security enforcement

This plugin does not allow submitting credit card details directly to Braintree gateway. Instead, you must tokenize the card before submitting the payment form. Calling the default `getPaymentFormHtml()` method on the gateway will automatically use Braintree's DropinUI interface and tokenize the payment details. If you wish to have control over the dropinUi options or would prefer to use Braintree's HostedFields instead, you will need to manually add the fields and javascript instead of using the default method.

## 3D secure payments

To allow 3D Secure payments, you must enable it in your Braintree account, then pass in the optional parameter into the `getPaymentFormHtml()` method. Default: false

```
gateway.getPaymentFormHtml({threeDSecure:true})
```

## Options

These are options that can be passed into the default `getPaymentFormHtml()` method.

### Store Name

This will set the store name for paypal, google pay & apple pay. Default: siteName

```
gateway.getPaymentFormHtml({storeName:'My WebSite'})
```

### Translations

This will allow the setting of the dropinUi translations: [Examples](https://github.com/braintree/braintree-web-drop-in/blob/master/src/translations/en_US.js)

```
gateway.getPaymentFormHtml({translations:{chooseAWayToPay:'Choose a way to pay'}})
```

### Vault

This allows the payment details to be store in Braintree's Vault, not the website. The DropinUi will display all saved payment methods. Default: false

```
gateway.getPaymentFormHtml({vault:true})
```

If you would like to allow the management of vaulted payment methods, then pass in the `manage` option. Default: false

```
gateway.getPaymentFormHtml({manage:true})
```

## Subscriptions

### Creating a subscription plan

1. To create a subscription plan, it must first be created within your Braintree account.
2. Go to Commerce → Settings → Subscription plans and create a new subscription plan.

### Options when subscribing

#### Trial Days

Trial days are setup as part of the plan within Braintree.

### Options when switching between different subscription plans

#### The `prorate` parameter

If this parameter is set to true, the subscription switch will be prorated.


