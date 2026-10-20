<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Braintree for Craft Commerce icon"></p>

<h1 align="center">Braintree for Craft Commerce</h1>

This plugin provides a [Braintree](https://www.braintreegateway.com) integration for [Craft Commerce](https://craftcms.com/commerce).

## Features

-   Merchant Accounts: supports Braintree's merchant accounts for multiple payment currencies.
-   Subscriptions: support subscription integration.
-   Payment sources: customers can save a card or PayPal account, subscribe with it and pay orders with it.
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

### Drop-in options

Any of Braintree's [drop-in `create()` options](https://braintree.github.io/braintree-web-drop-in/docs/current/module-braintree-web-drop-in.html#.create) can be passed as `dropinOptions`. They are merged over the options the plugin sets, so you only need to give the parts you want to change:

- objects are merged key by key, so the plugin's other settings at the same level are kept;
- a string, number, boolean or array replaces the plugin's value;
- `null` removes the option, e.g. `paypal: null` hides PayPal.

`authorization` and `container` are always set by the plugin. Options are passed as JSON, so they cannot contain JavaScript functions.

```twig
{{ gateway.getPaymentFormHtml({
    dropinOptions: {
        card: {
            overrides: {
                fields: {
                    cvv: { placeholder: '' },
                },
            },
        },
        paypal: null,
        paymentOptionPriority: ['card', 'googlePay', 'applePay'],
    },
})|raw }}
```

This example removes the CVV placeholder dots (so an autofilled card without a CVV no longer looks complete), hides PayPal and orders the remaining payment methods. [Field overrides](https://developer.paypal.com/braintree/docs/guides/drop-in/customization/javascript/v3/#field-overrides) lists what each card field accepts.

## Payment sources

A payment source is a card or PayPal account saved in Braintree's vault against the customer, and listed in Commerce as, for example, "Visa ending in ••••1111" or "PayPal (buyer@example.com)". The Braintree customer's id is the Craft user's uid; it is created the first time it is needed. Deleting a payment source in Commerce deletes it from Braintree too.

Pass `paymentSource: true` to `getPaymentFormHtml()` for a form that saves a payment source. The drop-in then offers a card or PayPal (in PayPal's vault flow) and no Apple Pay or Google Pay, which need an amount and cannot be saved from the drop-in. `subscription: true` does the same.

Output the drop-in with `|raw`, namespace its fields with Commerce's `commercePaymentFormNamespace` filter, and give the form a `<button type="submit">`, which the drop-in enables once a payment method is entered.

### Saving a payment source

The customer must be signed in.

```twig
{% requireLogin %}
{% set gateway = craft.commerce.gateways.getGatewayByHandle('braintree') %}

<form method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/payment-sources/add') }}
    {{ hiddenInput('gatewayId', gateway.id) }}
    {{ redirectInput('account/payment-methods') }}

    {% namespace gateway.handle|commercePaymentFormNamespace %}
        {{ gateway.getPaymentFormHtml({paymentSource: true})|raw }}
    {% endnamespace %}

    <label>
        <input type="checkbox" name="isPrimaryPaymentSource" value="1">
        Use this for my subscriptions
    </label>

    <button type="submit">Save</button>
</form>
```

A refusal from Braintree (a declined card, for example) comes back as "Could not create the payment source." with Braintree's message.

Listing and deleting the customer's saved sources uses Commerce's own actions:

```twig
{% for source in craft.commerce.paymentSources.getAllGatewayPaymentSourcesByCustomerId(gateway.id, currentUser.id) %}
    <form method="post">
        {{ csrfInput() }}
        {{ actionInput('commerce/payment-sources/delete') }}
        {{ hiddenInput('id', source.id) }}
        {{ source.description }}
        <button type="submit">Delete</button>
    </form>
{% endfor %}
```

`commerce/payment-sources/set-primary-payment-source`, posted with the source's `id`, makes it the one subscriptions use.

### Paying with a saved payment source

Post the source's id as `paymentSourceId` to `commerce/payments/pay`. The order is charged to the saved card or PayPal account; no drop-in is needed.

```twig
<form method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/payments/pay') }}
    {{ redirectInput('shop/customer/order?number={number}') }}

    {% for source in craft.commerce.paymentSources.getAllPaymentSourcesByCustomerId(currentUser.id) %}
        <label>
            <input type="radio" name="paymentSourceId" value="{{ source.id }}" {{ loop.first ? 'checked' }}>
            {{ source.description }}
        </label>
    {% endfor %}

    <button type="submit">Pay</button>
</form>
```

A checkout that posts the drop-in's fields, as before, is charged exactly as it always was, and saves no payment source.

## Subscriptions

### Creating a subscription plan

1. To create a subscription plan, it must first be created within your Braintree account.
2. Go to Commerce → Settings → Subscription plans and create a new subscription plan.

### Subscribing

A subscription is charged to a saved payment source: the customer's primary one if it is on this gateway, otherwise the one they saved most recently. A customer with no saved source enters one in the drop-in on the subscribe form, and Commerce saves it before the subscription starts. Show the drop-in only to customers with nothing saved, so that the others subscribe with their primary source without re-entering it.

```twig
{% requireLogin %}
{% set plan = craft.commerce.plans.getPlanByHandle('monthly') %}
{% set gateway = plan.getGateway() %}
{% set sources = craft.commerce.paymentSources.getAllGatewayPaymentSourcesByCustomerId(gateway.id, currentUser.id) %}

<form method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/subscriptions/subscribe') }}
    {{ hiddenInput('planUid', plan.uid|hash) }}
    {{ redirectInput('account/subscriptions') }}

    {% if sources is empty %}
        {% namespace gateway.handle|commercePaymentFormNamespace %}
            {{ gateway.getPaymentFormHtml({subscription: true})|raw }}
        {% endnamespace %}
    {% endif %}

    <button type="submit">Subscribe</button>
</form>
```

With no saved source and no drop-in fields posted, subscribing fails with "No payment sources are saved to use for subscriptions."

### Changing a subscription's payment method

`commerce-braintree/subscriptions/update-payment` takes `subscriptionUid` and `planUid` (both hashed) with the drop-in's fields, not namespaced (`{{ gateway.getPaymentFormHtml({subscription: true})|raw }}` straight into the form), and must be posted by the subscription's own customer. `commerce-braintree/payment-methods/delete` takes the gateway's `gateway` handle and a vaulted method's `token`, and deletes it only when it belongs to the signed-in customer. Both accept POST requests only.

### Options when subscribing

#### Trial Days

Trial days are setup as part of the plan within Braintree.

### Options when switching between different subscription plans

#### The `prorate` parameter

If this parameter is set to true, the subscription switch will be prorated.


