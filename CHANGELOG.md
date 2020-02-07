# Commerce Braintree Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.3.2 - 2020-01-09

### Changed

-   Update requirements to allow for Craft Commerce 3


## 2.3.2 - 2020-01-09

### Fixed

-   switch subscription payment source removes old payment source if not in use by another subscription.

### Changed

-   DropinUI don't allow save card option if its a subscription payment

## 2.3.1 - 2019-12-18

### Fixed

-   subscription detail page throwing error

### Changed

-   Cancelling a subscription now deletes the payment source if not being used by another active subscription.

### Added

-   deletePaymentMethod(\$token)

## 2.3.0 - 2019-12-13

### Changed

-   Update Braintree PHP SDK to 4.5.0
-   Updated dropinUi js to 1.21.0
-   dropinUi form submission to be \$form.trigger('submit')
-   removed country name from billing and shipping addresses, now just uses the ISO2 code

### Added

-   DropinUi translations.

## 2.2.7 - 2019-11-19

### Fixed

-   A bug where subscriptions were not being created when the currency plan was different to the default currency in Braintree

## 2.2.6 - 2019-11-15

### Fixed

-   Fixed a bug that could occur if a susbcrption was cancelled directly in Braintree.

## 2.2.5 - 2019-10-26

### Changed

-   Craft Commerce 2.2 compatability update

## 2.2.4 - 2019-10-02

### Fixed

-   Fixed date bug in \_handleSubscriptionCharged method
-   Fixed deprecation warning for locale in DropinUi template

### Changed

-   Updated DropinUi button reset

## 2.2.3 - 2019-09-12

### Changed

-   Updated Braintree JS versions

## 2.2.2 - 2019-09-05

### Fixed

-   gateway settings twig mistake

## 2.2.1 - 2019-09-05

### Fixed

-   address formatting to cater for no country

### Added

-   autosuggestField support for environment settings
-   customer info sent
-   shortNumber used for orderId

## 2.2.0 - 2019-09-03

### Added

-   3DS2 support

### Changed

-   DropinUi Javascript jQuery object is now passed into init function.

## 2.1.18 - 2019-08-29

### Fixed

-   Don't pass address if no address on order.

## 2.1.17 - 2019-08-19

### Fixed

-   Addresses now passing US state abbreviation for paypal requirement.

## 2.1.16 - 1019-08-15

### Fixed

-   Paypal when using getPaymentFormHtml() (issue #6)

## 2.1.15 - 2019-07-29

### Fixed

-   Fixed a bug in an address with the business name/company

## 2.1.14 - 2019-07-25

### Added

-   Billing and Shipping addresses now passed to Braintree

### Fixed

## 2.1.13 - 2019-07-17

-   Fixed a bug where subscription data was being returned as an object

## 2.1.12 - 2019-07-15

### Changed

-   Updated error message for better error logging

## 2.1.11 - 2019-07-03

### Changed

-   User friendly error message

## 2.1.10 - 2019-06-27

### Added

-   Added support for Craft Commerce 2.1.4 and `craft\commerce\base\SubscriptionResponseInterface::isInactive()`.

## 2.1.9 - 2019-06-18

### Added

-   Subscription updates
-   Subscription webhooks support

## 2.1.8 - 2019-05-29

### Fixed

-   DropIn UI jQuery reference, thanks @davecosec

## 2.1.7 - 2019-05-21

### Fixed

-   multi-currency support

## 2.1.6 - 2019-03-12

### Changed

-   gateway.getPaymentFormHtml() returns raw by default

### Fixed

-   rendering issue when using getPaymentFormHtml()

## 2.1.5 - 2019-02-25

### Added
