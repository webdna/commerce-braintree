# Commerce Braintree Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.1.19 - unreleased

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
