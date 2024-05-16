# Commerce Braintree Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.0.3 - 2024-05-16
### Added
- Craft 5 compatibility

### Updated
- DropInUiSDK version 1.42.0
- ClientSDK version 3.102.0

## 3.0.2 - 2024-03-13
### Fixed
- Braintree\Customer type

## 3.0.1 - 2024-02-06
### Changed
- manual state control option for buttons
- Released - finally!

## 3.0.0-beta.11 - 2023-05-18
### Changed
- changed callback param to object with callbacks for "onPaymentMethodSelect" and "onPaymentMethodReady"

## 3.0.0-beta.10 - 2023-03-31
### Changed
- express checkout params

### Added
- DropinUi Callback param.

## 3.0.0-beta.9 - 2023-03-24
### Changed
- quick checkout => express checkout

### Added
- params for express checkout buttons
- express checkout buttons wrapping div with class "express-checkout-buttons" for layout purposes.

## 3.0.0-beta.8 - 2023-03-21
### Changed
- removed testing values

## 3.0.0-beta.7 - 2023-03-21
### Changed
- updated dropinUI sdk to 1.34.0
- updated client sdk to 3.91.0

### Added
- NEW quick checkout ability for applePay and googlePay

## 3.0.0-beta.6 - 2023-03-08
### Changed
- order.user => order.customer

### Added
- Ability to specify SDK versions

## 3.0.0-beta.5 - 2023-03-08
### Fixed
- js form submission

## 3.0.0-beta.4 - 2023-03-03
### Fixed
- data type issues
- multiple form issues
- fixed no jquery changes

### Changed
- Updated submit button dataset to allow html not just text

## 3.0.0-beta.3 - 2023-01-30
### Changed
- Updated data type

## 3.0.0-beta.2 - 2023-01-19
### Changed
- Removed jQuery dependency for DropInUI

## 3.0.0-beta.1 - 2022-10-12
### Added
- Craft / Commerce 4 support.

## 2.4.3 - 2022-06-10
### Changed
- Change of ownership

## 2.4.2 - 2021-12-16

### Fixed

-   Fixed an issue with the currency being passed to the currency formatter.

### Added

-   Added support for subscription trial days - thanks @digason

## 2.4.1 - 2021-09-24

### Fixed

-   Fixed an issue where the Drop-in UI wasn't using the correct Google Pay object - thanks @jmauzyk.

### Added

-   Added a new setting for including a Google Pay Mechant ID.

## 2.4.0 - 2021-07-20

### Added

-   support for authorize and capture functions thanks @jmauzyk 

## 2.3.9 - 2020-10-28

### Added

-   logging
-   address field truncating to 50 characters due to braintree api restrictions

## 2.3.8 - 2020-09-16

### Fixed

-   Fixed a bug where purchases weren't using the createSale method

## 2.3.7 - 2020-09-16

### Added

-   Created a refreshPaymentHistory method so the full subscription payment history can be view in the CP using the "Refresh payment history" button.

### Fixed

-   Fixed a bug where a subscription payment used the original subscription date instead of the latest transaction date.

### Changed

-   Moved `gateway->transction()->sale` to it's own createSale method.

## 2.3.6 - 2020-06-23

### Fixed

-   Allow setting of merchantAccountId for single currency setup (#29)
-   MerchantAccountId wrapped in Craft::parseEnv (#30)
-   Throwing braintree exceptions using PaymentExceptions (#31)

## 2.3.5 - 2020-03-26

### Fixed

-   Fixed project config bug

## 2.3.4 - 2020-03-12

### Changed

-   Updated gateway property visability

## 2.3.3 - 2020-01-09

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
