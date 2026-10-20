---
spec: Braintree for Craft Commerce 3.2.0 — payment sources
slug: braintree-3-2-payment-sources
status: draft
version: 0.1
date: 2026-10-20
author: Claude (with Sam Birch)
client: webdna (Craft Plugin Store product)
approver: Sam Birch
related: [webdna/commerce-braintree#53]
---

# Braintree for Craft Commerce 3.2.0 — payment sources

> **Status:** draft - **Version:** 0.1
> A customer can save a card or PayPal account as a Commerce payment source, subscribe to a plan
> with it, and pay an order with it. Until now no subscription could be started: `subscribe()`
> needs a saved payment source and the gateway told Commerce it could not hold one.

---

## 1. How it works

The gateway supports payment sources. Commerce's own `commerce/payment-sources/add` action takes
the Braintree drop-in's nonce, the plugin vaults it against the user's Braintree customer
(created if missing), and Commerce stores the returned token as a payment source described as
"Visa ending in ••••1111" or "PayPal (name@example.com)". Deleting the source in Commerce deletes
the vaulted method in Braintree.

A subscription starts with Commerce's `commerce/subscriptions/subscribe` action. If the form posts
the drop-in's fields, Commerce first saves them as a payment source (Commerce's own behaviour, in
4 and 5); the gateway then subscribes with the user's primary payment source if it belongs to this
gateway, otherwise their most recently saved one.

An order is paid with a saved source by posting `paymentSourceId` to `commerce/payments/pay`, as
Commerce documents. The payment form carries the source's token and the sale is made against it.

The drop-in renders in **payment-source mode** for both forms (`paymentSource: true`, or the
existing `subscription: true`): card and PayPal only, PayPal in its vault flow, no Apple Pay or
Google Pay (both need an amount, and Braintree does not vault them from the drop-in).

The plugin's two own actions, `commerce-braintree/payment-methods/delete` and
`commerce-braintree/subscriptions/update-payment`, act only on the signed-in user's own vaulted
methods and subscriptions.

## 2. Scope

**In:** #53. `supportsPaymentSources()`; `createPaymentSource()`, `deletePaymentSource()`,
`subscribe()` source choice; payment-source mode of the drop-in; ownership checks on the plugin's
two controllers; README "Payment sources" and "Subscribing" sections; CHANGELOG 3.2.0.

**Out:** 3-D Secure or card verification on vaulting (Braintree's account-level verification
settings still apply). Apple Pay / Google Pay sources. Payment sources in the CP payment modal
(it stays hosted fields, card entry only). Migrating vaulted methods that exist in Braintree but
not in Commerce. Any change to the hosted-fields form.

## 3. User journeys

1. A signed-in customer opens "Payment methods" on a site, enters a card in the drop-in and
   saves it. Commerce lists "Visa ending in ••••1111"; Braintree shows the card on the customer
   whose id is the user's uid.
2. The same customer saves a PayPal account. Commerce lists "PayPal (buyer@example.com)".
3. A customer with no saved source opens a plan's subscribe form, enters a card in the drop-in
   and subscribes. The card is saved as a payment source and the subscription is active in
   Commerce and Braintree.
4. A customer with two saved sources, the second marked primary, subscribes without entering a
   card. The subscription is charged to the primary source.
5. A customer checks out choosing a saved source; the order is paid and the Braintree
   transaction shows the vaulted card.
6. A customer deletes a saved source in Commerce; it is gone from Braintree too.
7. A signed-in customer posts another customer's subscription uid to
   `commerce-braintree/subscriptions/update-payment`, or another customer's token to
   `commerce-braintree/payment-methods/delete`. Both are refused; nothing changes.

## 4. Data model

None. Payment sources are Commerce's `commerce_paymentsources` rows (`customerId`, `gatewayId`,
`token` = Braintree payment method token, `description`, `response`). The Braintree customer id
stays the Craft user's uid, as `getToken()` already creates it.

## 5. Rules

- **BR-1** `supportsPaymentSources()` is true.
- **BR-2** `createPaymentSource($form, $customerId)` vaults the form's nonce against the Braintree
  customer for that user, creating the customer (id = user uid, name, email) if Braintree has none,
  and returns a `PaymentSource` with `customerId`, `gatewayId`, `token`, `response` and a
  description. It sets no `userId` (Commerce 4 and 5 have none; the current code throws on it).
- **BR-3** The description is "{cardType} ending in ••••{last4}" for anything with a card type
  and last four (cards, Apple Pay); "PayPal ({email})" for a PayPal account; otherwise
  "Saved payment method". A description posted with the form still wins (Commerce sets it after).
- **BR-4** A Braintree refusal (`success` false) throws `PaymentSourceException` with Braintree's
  message, so Commerce's add action reports it on the form.
- **BR-5** `deletePaymentSource($token)` deletes the vaulted method in Braintree and returns true;
  a method Braintree no longer has counts as deleted; any other failure is logged and returns
  false. Commerce deletes its own row either way.
- **BR-6** `subscribe()` uses the user's primary payment source when it belongs to this gateway;
  otherwise the most recently created source for this gateway; otherwise it throws
  `PaymentSourceException` "No payment sources are saved to use for subscriptions." Braintree's
  own message is included when the subscription is refused.
- **BR-7** The drop-in's payment-source mode (`paymentSource: true` or `subscription: true`):
  card with vaulting, PayPal with `flow: 'vault'`, no Apple Pay or Google Pay, and the client
  token generated for the signed-in user. `dropinOptions` (3.1.2) still merges last.
- **BR-8** `commerce-braintree/payment-methods/delete` requires POST and a signed-in user, and
  deletes only a method whose Braintree `customerId` is the user's uid; it also removes the
  Commerce payment source with that token. Anything else is a 403 (or a failure response for a
  JSON request) and Braintree is not called to delete.
- **BR-9** `commerce-braintree/subscriptions/update-payment` requires POST and a signed-in user,
  and acts only on a subscription whose `userId` is the user's and whose gateway is a Braintree
  gateway; the plan must exist and belong to the same gateway. Anything else is refused before
  any Braintree call.
- **BR-10** Paying with a saved source (`paymentSourceId`) sends `paymentMethodToken` and the
  user's `customerId`; a nonce, when posted, still wins over a token.
- **BR-11** Nothing changes for a store that never uses payment sources: checkout with the
  drop-in posts a nonce and is charged exactly as in 3.1.2.

## 6. Interfaces

| Surface | Change |
|---|---|
| `Gateway::supportsPaymentSources()` | true |
| `Gateway::createPaymentSource()` | BR-2 to BR-4 |
| `Gateway::deletePaymentSource()` | BR-5 |
| `Gateway::subscribe()` | BR-6 |
| `Gateway::createPaymentMethod()` | ensures the Braintree customer first (shared with `getToken()`) |
| `getPaymentFormHtml({paymentSource: true})` | new param; BR-7 |
| `paymentForms/dropin-ui.twig`, `DropinUi.js` | `data-payment-source`; PayPal vault flow |
| `controllers/PaymentMethodsController` | BR-8 |
| `controllers/SubscriptionsController` | BR-9 |
| README | "Payment sources" and "Subscribing" sections with the three forms (save, subscribe, pay with a saved source) |

## 7. Test plan

All in craft5 (Craft 5.11, Commerce 5.7) against a **Braintree sandbox**, with a plan created in
the sandbox and synced to Commerce. Test cards from Braintree's sandbox docs.

| Id | Scenario | Expect |
|---|---|---|
| TS-1 | Journey 1 with `4111 1111 1111 1111` | Source "Visa ending in ••••1111"; vaulted on customer = user uid |
| TS-2 | Journey 1 for a user with no Braintree customer yet | Customer created, card vaulted |
| TS-3 | Journey 2 (sandbox PayPal) | Source "PayPal (…)" |
| TS-4 | Journey 3: subscribe with the drop-in, no saved source | Source saved, subscription active in Commerce and Braintree |
| TS-5 | Journey 4: two sources, second primary | Subscription's `paymentMethodToken` = the primary's token |
| TS-6 | Journey 5: checkout with `paymentSourceId` | Order paid; Braintree transaction uses the vaulted token |
| TS-7 | Journey 6: delete in Commerce | Gone from Braintree; deleting again in Braintree's absence still clears Commerce |
| TS-8 | Checkout with a nonce as in 3.1.2 (BR-11) | Paid; no payment source created |
| TN-1 | Card `4000 1111 1111 1115` (processor declined) on add | "Could not create the payment source." with Braintree's message; no row |
| TN-2 | Journey 7, both actions, as a second user | 403; subscription and vaulted method unchanged |
| TN-3 | `update-payment` with a plan from another gateway | Refused before any Braintree call |
| TN-4 | Subscribe with no source and no drop-in fields | "No payment sources are saved…" |

Craft 4: the changed PHP is read against Commerce 4.8's `PaymentSource` (`customerId`),
`PaymentSourcesController::actionAdd` and `SubscriptionsController::actionSubscribe`, which match
Commerce 5's in every line this spec relies on (checked 20 Oct 2026).

## 8. Build order

### Tasks

- [x] **1.1 Controller ownership** — `src/controllers/PaymentMethodsController.php`, `src/controllers/SubscriptionsController.php`
      Rules: BR-8, BR-9 · Verify: TN-2, TN-3
      (A security fix in its own right; worth shipping first if 3.2.0 slips.)
      Built 20 Oct 2026. Proved without sandbox keys: the real actions run through `runAction()` in a
      bootstrapped craft5 web app, with the Braintree SDK swapped for a fake that records every call,
      fixtures in a rolled-back transaction. 18 cases: owner delete (302 / JSON 200, Braintree delete,
      Commerce row gone); another user's method, a second user, an unknown token, no token, a
      non-Braintree gateway → 403 / JSON 403 with no Braintree delete; owner update reaches Braintree;
      a second user, a plan on another gateway, no plan, no subscription → 403 with no Braintree call;
      GET → 405, guest → refused. The old controllers fail the cross-user cases (a second user's POST
      deleted the owner's method). TN-2/TN-3 against the sandbox stay in B4.
- [ ] **1.2 Gateway payment sources** — `src/gateways/Gateway.php`
      Rules: BR-1 to BR-6, BR-10, BR-11 · Verify: TS-1, TS-2, TS-5, TS-6, TS-7, TS-8, TN-1, TN-4
- [ ] **1.3 Drop-in payment-source mode** — `src/templates/paymentForms/dropin-ui.twig`, `src/assetbundles/dropinui/dist/js/DropinUi.js`, `Gateway::getSitePaymentFormHtml()`
      Rules: BR-7 · Verify: TS-3, TS-4
- [ ] **1.4 Release** — `README.md`, `CHANGELOG.md` (3.2.0); reply on #53 and close it when tagged
      Verify: README forms copied into craft5 templates and run as TS-1, TS-4, TS-6

---

# Appendix B - build scaffolding

## B1. Before writing anything

**Braintree sandbox credentials in craft5.** The `compatBraintree` gateway holds dummy keys
(merchant id `dummy`), so no token can be generated and nothing in §7 can run. Sam to supply a
sandbox merchant id, public and private key and a merchant account id per currency, set as env
vars in craft5's `.env` and referenced from the gateway settings. A sandbox plan for TS-4/TS-5.
The plugin is installed but disabled in craft5 (Store screenshots); enable it for the build and
disable it after.

## B2. Context to load

1. `src/gateways/Gateway.php`: `getToken()` (282, creates the customer), `createPaymentMethod()`
   (351), `authorize()`/`purchase()` (377/512, token branch already there), `createPaymentSource()`
   (462), `subscribe()` (788), `updateSubscriptionPayment()` (863), `getPaymentSource()` (1004:
   unordered query, so `[0]` is in practice the oldest).
2. Commerce `controllers/PaymentSourcesController::actionAdd()` — login, namespaced payment form
   (`PaymentForm::getPaymentFormParamName()`), primary flag; sets `customerId` and description
   after the gateway returns.
3. Commerce `controllers/SubscriptionsController::actionSubscribe()` — creates a payment source
   from posted payment-form data before `createSubscription()` (deprecated-logged, still the path).
4. Commerce `services/PaymentSources::deletePaymentSourceById()` — calls the gateway, then deletes
   the row regardless.

## B3. Guardrails

- **Never call Braintree to delete or update before the ownership check passes.**
- **Never set `userId` on a `PaymentSource`.**
- **Checkout without a saved source must not change** (BR-11): no vaulting on a plain purchase.
- **Never commit in craft5**, and put it back as found (plugin disabled, keys removed from the
  gateway if Sam wants the dummy ones back).

## B4. Definition of done

- [ ] TS-1 to TS-8 and TN-1 to TN-4 pass in craft5 against the sandbox
- [ ] README documents saving a source, subscribing and paying with one
- [ ] CHANGELOG 3.2.0; committed, not pushed or tagged until Sam says

## B5. Verification

```bash
# from ~/projects/craft5 (lowercase)
ddev exec php -l vendor/webdna/commerce-braintree/src/gateways/Gateway.php
ddev craft plugin/enable commerce-braintree
# then §7 in a browser as a front-end customer; TN-2 with curl as a second user's session
```

---

## Change log

| Date | Version | Change | By |
|---|---|---|---|
| 2026-10-20 | 0.2 | 1.1 built: ownership checks on both controllers | Claude |
| 2026-10-20 | 0.1 | First draft, from #53 and the 3.1.2 triage finding that subscriptions cannot start | Claude |
