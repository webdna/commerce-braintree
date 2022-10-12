/**
 * Commerce Braintree plugin for Craft CMS
 *
 * Commerce Braintree JS
 *
 * @author    Kurious Agency
 * @copyright Copyright (c) 2018 Kurious Agency
 * @link      https://kurious.agency
 * @package   CommerceBraintree
 * @since     1.0.0
 */

(function() {
	(function check() {
		if (typeof braintree !== 'undefined' && typeof jQuery !== 'undefined') {
			init(jQuery);
		} else {
			setTimeout(check, 50);
		}
	})();

	function init($) {
		$('form').each(function() {
			var $form = $(this),
				$token = $form.find('[name*="gatewayToken"]'),
				$nonce = $form.find('[name*="nonce"]'),
				amount = $form.find('[name*="amount"]').val(),
				currency = $form.find('[name*="currency"]').val(),
				email = $form.find('[name*="email"]').val(),
				address = $form.find('[name*="address"]').val(),
				$dropinUi = $form.find('[data-id="dropInUi"]'),
				$submit = $form.find('button[type="submit"]');

			if ($dropinUi[0]) {
				$submit.data('text', $submit.text());
				if ($submit.data('loading')) {
					$submit.text($submit.data('loading'));
				}

				var options = {
					authorization: $token.val(),
					container: $dropinUi[0],
					locale: $dropinUi.data('locale'),
					vaultManager: $dropinUi.data('manage'),
					card: {
						cardholderName: {
							required: true
						},
						vault: {
							vaultCard: true,
							allowVaultCardOverride: true
						}
					}
				};
				if ($dropinUi.attr('data-translations') != '') {
					options.translations = JSON.parse($dropinUi.attr('data-translations'));
				}

				if (Boolean($dropinUi.attr('data-subscription')) != true) {
					options.paypal = {
						flow: 'checkout',
						env: $dropinUi.attr('data-sandbox') ? 'sandbox' : 'production',
						amount: amount,
						currency: currency,
						buttonStyle: {
							color: 'blue',
							shape: 'rect',
							size: 'responsive',
							label: 'paypal'
						}
					};

					options.applePay = {
						displayName: $dropinUi.data('name'),
						paymentRequest: {
							total: {
								label: $dropinUi.data('name'),
								amount: amount
							}
						}
					};

					options.googlePay = {
						merchantId: $dropinUi.data('google-pay-id'),
						googlePayVersion: 2,
						transactionInfo: {
							countryCode: address ? JSON.parse(address).countryCodeAlpha2 : '',
							currencyCode: currency,
							totalPriceStatus: 'FINAL',
							totalPrice: amount
						}
					};
				} else {
					options.card.vault.allowVaultCardOverride = false;
				}

				if ($dropinUi.data('threedsecure')) {
					options.threeDSecure = true;
				}

				braintree.dropin.create(options, function(err, dropinInstance) {
					if (err) {
						console.error(err);
						if (window.braintreeError) {
							window.braintreeError(err);
						}
						return;
					}

					if (dropinInstance.isPaymentMethodRequestable()) {
						reset($submit);
					}
					//need for vault
					dropinInstance.on('paymentMethodRequestable', function(e) {
						reset($submit);
					});
					dropinInstance.on('noPaymentMethodRequestable', function(e) {
						processing($submit);
					});
					dropinInstance.on('paymentOptionSelected', function(e) {
						//$submit.prop('disabled', false);
					});

					$form.on(
						'submit',
						{
							dropinInstance: dropinInstance,
							threeDSecure: $dropinUi.data('threedsecure'),
							options: {
								threeDSecure: {
									amount: amount,
									email: email,
									billingAddress: address ? JSON.parse(address) : address
								}
							}
						},
						formSubmit
					);
				});
			}
		});
	}

	function formSubmit(e) {
		e.preventDefault();
		//console.log(e)
		var dropinInstance = e.data.dropinInstance,
			$form = $(e.currentTarget),
			threeDSecure = e.data.threeDSecure,
			$submit = $form.find('button[type="submit"]');
		processing($submit);

		dropinInstance.requestPaymentMethod(threeDSecure ? e.data.options : {}, function(err, payload) {
			if (err) {
				console.error(err);
				if (window.braintreeError) {
					window.braintreeError(err);
				}
				reset($submit);
				return;
			}
			//console.log(payload);
			if ((payload.liabilityShiftPossible && payload.liabilityShifted) || !payload.liabilityShiftPossible || payload.type !== 'CreditCard' || !threeDSecure) {
				processing($submit);
				$form.find('input[name*=nonce]').val(payload.nonce);
				$form.off('submit', formSubmit);
				$form.trigger('submit');
			} else {
				if (window.braintreeError) {
					window.braintreeError('3ds failed');
				}
				//dropinInstance.clearSelectedPaymentMethod();
				reset($submit);
				//$submit.prop('disabled', true);
			}
		});
	}
	function reset($button) {
		$button.prop('disabled', false);
		$button.text($button.data('text'));
	}
	function processing($button) {
		$button.prop('disabled', true);
		if ($button.data('processing')) {
			$button.text($button.data('processing'));
		}
	}
})();
