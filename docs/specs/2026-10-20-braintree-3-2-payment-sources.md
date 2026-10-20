---
spec: Braintree for Craft Commerce 3.2.0 — payment sources
slug: braintree-3-2-payment-sources
status: draft
version: 0.8
date: 2026-10-20
author: Claude (with Sam Birch)
client: webdna (Craft Plugin Store product)
approver: Sam Birch
related: [webdna/commerce-braintree#53]
---

# Braintree for Craft Commerce 3.2.0 — payment sources

> **Status:** draft - **Version:** 0.8
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

**Out:** 3-D Secure on vaulting. Apple Pay / Google Pay sources. Payment sources in the CP payment modal
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
- **BR-12** A gateway setting, "Verify cards when saved?" (`verifyCards`, env-able, **off by
  default**). On: `createPaymentMethod()` sends `verifyCard` with the primary payment currency's
  merchant account as `verificationMerchantAccountId`, and the customer's primary billing address
  when they have one; a declined card is refused under BR-4. Off: no verification is asked for,
  as in 3.1.2. (Sam, 20 Oct 2026: off by default because an account's AVS rules apply to the
  verification — mw-core's sandbox refuses every card without a street address, and the drop-in
  collects only a postcode.)

## 6. Interfaces

| Surface | Change |
|---|---|
| `Gateway::supportsPaymentSources()` | true |
| `Gateway::createPaymentSource()` | BR-2 to BR-4 |
| `Gateway::deletePaymentSource()` | BR-5 |
| `Gateway::subscribe()` | BR-6 |
| `Gateway::createPaymentMethod()` | ensures the Braintree customer first (shared with `getToken()`); BR-12 |
| `Gateway` `verifyCards` setting, `gatewaySettings.twig` | BR-12 |
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
| TN-1 | With "Verify cards when saved?" on and a primary billing address: a processor-declined card on add | "Could not create the payment source." with Braintree's message ("Do Not Honor") on the form; no row. With it off the card is saved, as in 3.1.2 |
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
- [x] **1.2 Gateway payment sources** — `src/gateways/Gateway.php`, `src/models/Payment.php`
      Rules: BR-1 to BR-6, BR-10, BR-11 · Verify: TS-1, TS-2, TS-5, TS-6, TS-7, TS-8, TN-1, TN-4
      Built 20 Oct 2026, proved without sandbox keys as 1.1 was: the gateway and Commerce's own
      `PaymentSources` service driven in a bootstrapped craft5 app, the SDK swapped for a recording
      fake, fixtures rolled back. 32 cases pass (the committed code fails at the first two). Found on
      the way: `subscribe()` wrapped Commerce's plan in `new Plan($plan)`, which throws a TypeError
      on Craft 4 and 5, so no subscription could ever start; it now uses the plan as given. The
      response is stored as JSON (`type`, `cardType`, `last4`, `email`, `expirationDate`) because
      `PaymentSource::$response` is a string, and `Payment::populateFromPaymentSource()` reads the
      type back so a saved PayPal account is charged without the order's addresses, as a posted
      PayPal nonce is. A token Commerce already holds is replaced by deleting the old row only, never
      the method in Braintree. TS-* against the sandbox stay in B4.
- [x] **1.3 Drop-in payment-source mode** — `src/templates/paymentForms/dropin-ui.twig`, `src/assetbundles/dropinui/dist/js/DropinUi.js`, `Gateway::getSitePaymentFormHtml()`
      Rules: BR-7 · Verify: TS-3, TS-4
      Built 20 Oct 2026, proved without sandbox keys. `paymentSource: true` (or `subscription: true`)
      sets `vault`, so the client token carries the signed-in user's customer, and renders
      `data-payment-source`. The real form rendered in craft5 with a recording fake SDK, six cases:
      checkout signed in and as a guest (no attribute, no customer on the token), `paymentSource`
      and `subscription` (attribute, token customer = user uid), `vault`+`manage` alone (customer,
      no attribute), a guest in source mode (attribute, no customer). The real `DropinUi.js`, old and
      new, run in Node against a fake form and a fake `braintree.dropin.create`: checkout options
      byte-identical to 3.1.2 (plain, and sandbox + 3DS + `dropinOptions`); source and subscription
      mode give PayPal `flow: 'vault'` with no amount, no Apple Pay or Google Pay, card vaulted with
      no override; `dropinOptions` still merges last. Subscription mode had no PayPal at all before.
      Found, left: `data-sandbox` renders with no value, so checkout's `dataset.sandbox ? …` always
      says production; Drop-in takes the environment from the client token, so it is inert, and
      source mode tests presence instead. TS-3/TS-4 against the sandbox stay in B4.
- [x] **1.4 Release** — `README.md`, `CHANGELOG.md` (3.2.0); reply on #53 and close it when tagged
      Verify: README forms copied into craft5 templates and run as TS-1, TS-4, TS-6
      Built 20 Oct 2026. README "Payment sources" (save, list and delete, pay with a saved source),
      "Subscribing" (the drop-in only for a customer with nothing saved) and "Changing a
      subscription's payment method"; CHANGELOG 3.2.0 dated "Unreleased" until the tag. Proved
      without sandbox keys: the README's Twig blocks extracted verbatim into craft5 templates (the
      gateway handle swapped), rendered as a signed-in customer and their fields posted to Commerce's
      real `payment-sources/add`, `payment-sources/delete` and `subscriptions/subscribe` actions with
      the recording fake SDK, fixtures rolled back. 23 checks: subscribing with nothing saved shows
      the namespaced drop-in, vaults the nonce on the user's customer, saves the source and charges
      the subscription to it; two saved cards (the first primary) are described "Visa ending in
      ••••1111" with the primary set; a second subscribe has no drop-in and charges the primary, not
      the newest; the pay form lists one `paymentSourceId` radio per source; delete removes the
      method in Braintree and the row in Commerce. The pay form was rendered, not posted (no cart);
      paying with a source is 1.2's. Found by it: the drop-in must be output with `|raw` —
      `getPaymentFormHtml()` is declared `string`, so the plugin's Markup is cast back and Twig
      escapes it — so every README example now carries `|raw`, the 3.1.2 `dropinOptions` one
      included. The #53 reply and close wait for the tag (Sam). TS-* against the sandbox stay in B4.

---

# Appendix B - build scaffolding

## B1. Before writing anything

**Resolved 20 Oct 2026 for everything but plans:** mw-core's sandbox keys (Sam) are in craft5's
`.env` as `BRAINTREE_MERCHANT_ID`, `_PUBLIC_KEY`, `_PRIVATE_KEY`, `_TEST_MODE`, `_USD_MERCHANT_ID`
(craft5's store is USD); gateway settings reference them, never the values. **That sandbox has no
subscription plans**, and Braintree makes plans only in its Control Panel, so TS-4/TS-5 wait on one.

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

- [x] TS-1 to TS-8 and TN-1 to TN-4 pass in craft5 against the sandbox — **TS-4/TS-5 accepted on the
      fake-SDK proof (Sam, 20 Oct 2026)**: mw-core's sandbox has no plan, and subscribing with a new card
      and charging the primary source are proven by 1.2's recorded calls and the README forms' run (1.4)
      **Run 20 Oct 2026 against mw-core's sandbox.** Server side, Commerce's real actions with the real
      SDK (Commerce fixtures rolled back; sandbox customers deleted, transactions voided): TS-1 (the
      `fake-valid-visa-nonce` card is 4012…1881, so "Visa ending in ••••1881"), TS-2, TS-3 ("PayPal
      (jane.doe@paypal.com)" from `fake-paypal-billing-agreement-nonce`), TS-6 (Braintree's
      transaction carries the vaulted token), TS-7 both halves, TS-8, TN-2 both actions, TN-3, TN-4
      pass; a Luhn-invalid card is refused with "Credit card number is invalid." on the form (BR-4).
      In a browser, the README's save form on a temporary frontend gateway: the drop-in offers Card
      and PayPal only; 4111 1111 1111 1111 typed in saves "Visa ending in ••••1111" (TS-1); Delete
      removes it from Commerce and Braintree (TS-7). **Open:** TS-4/TS-5 (no sandbox plan); TN-1 —
      `fake-processor-declined-visa-nonce` IS saved, because vaulting sends no `verifyCard` and the
      sandbox account does not verify cards; §2 puts verification out of scope, so TN-1 holds only
      for an account that verifies. **Resolved (BR-12, Sam): a setting, off by default.** Run against
      the sandbox, 14 checks: off — a declined card is saved as before; on, no address — "Street
      address is required." on the form; on with a primary billing address — a valid Visa is
      verified ("verified", street address sent), the declined card refused "Do Not Honor" (TN-1
      passes), PayPal saves, re-saving keeps the row, a Luhn-invalid card is refused; the settings
      page renders the field.
      Found and fixed on the way: a saved method selected on load posted an empty nonce ("Nonce is
      required."), as the submit handler was attached only on `paymentMethodRequestable`; re-saving
      a saved card replaced its row and so dropped its primary flag — the existing row is now reused;
      Braintree's refusal reason now reaches `paymentFormErrors`.
- [x] README documents saving a source, subscribing and paying with one
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
| 2026-10-20 | 0.8 | TS-4/TS-5 accepted on the fake-SDK proof (no sandbox plan; Sam); B4 ticked | Claude |
| 2026-10-20 | 0.7 | BR-12: "Verify cards when saved?" setting, off by default (Sam); TN-1 passes with it on | Claude |
| 2026-10-20 | 0.6 | Sandbox run (mw-core keys): all but TS-4/TS-5 (no plan) and TN-1 (verification) pass; three fixes | Claude |
| 2026-10-20 | 0.5 | 1.4 built: README payment sources + subscribing, CHANGELOG 3.2.0; README forms need `|raw` | Claude |
| 2026-10-20 | 0.4 | 1.3 built: drop-in payment-source mode (PayPal vault flow, token for the signed-in user) | Claude |
| 2026-10-20 | 0.3 | 1.2 built: gateway payment sources; subscribe()'s plan TypeError fixed | Claude |
| 2026-10-20 | 0.2 | 1.1 built: ownership checks on both controllers | Claude |
| 2026-10-20 | 0.1 | First draft, from #53 and the 3.1.2 triage finding that subscriptions cannot start | Claude |
