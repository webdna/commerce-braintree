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


(function(){

	(function check() {
		if (typeof braintree !== 'undefined') {
			init();
		} else {
			setTimeout(check, 50);
		}
	})();

	function init() {

		$('form').each(function(){

			var $form = $(this),
				$token = $form.find('[name="gatewayToken"]'),
				$nonce = $form.find('[name="nonce"]'),
				$amount = $form.find('[name="amount"]'),
				$currency = $form.find('[name="currency"]'),
				$dropinUi = $form.find('[data-id="dropInUi"]'),
				$submit = $form.find('button[type="submit"]');

			if ($dropinUi[0]) {

			$submit.data('text', $submit.text());
			if ($submit.data('loading')) {
				$submit.text($submit.data('lodaing'));
			}

			var options = {
				authorization: $token.val(),
				container: $dropinUi[0],
				locale: $dropinUi.data('locale'),
				card: {
					cardholderName: {
						required: true
					}
				},
				paypal: {
					flow: 'checkout',
					env: $dropinUi.attr('data-sandbox') ? 'sandbox':'production',
					amount: $amount.val(),
					currency: $currency.val(),
					buttonStyle: {
						color: 'blue',
						shape: 'rect',
						size: 'responsive',
						label: 'paypal'
					}
				},
				applePay: {
					displayName: $dropinUi.data('name'),
					paymentRequest: {
						total: {
							label: $dropinUi.data('name'),
							amount: $amount.val()
						}
					}
				},
				googlePay: {
					displayName: $dropinUi.data('name'),
					paymentRequest: {
						total: {
							label: $dropinUi.data('name'),
							amount: $amount.val()
						}
					}
				}
			};

			if ($dropinUi.data('threedsecure')) {
				options.threeDSecure = {
					amount: $amount.val()
				};
			}

			braintree.dropin.create(
				options,
				function(err, dropinInstance) {
					if (err) {
						console.error(err);
						if (window.braintreeError ) { window.braintreeError(err) }
						return;
					}
					reset($submit);

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

					$form.on('submit', { dropinInstance:dropinInstance, threeDSecure:$dropinUi.data('threedsecure') }, formSubmit);

				}
			);
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

		dropinInstance.requestPaymentMethod(function(err, payload) {
			if (err) {
				console.error(err);
				if (window.braintreeError ) { window.braintreeError(err) }
				reset($submit);
				return;
			}
			//console.log(payload);
			if (payload.liabilityShifted || payload.type !== 'CreditCard' || !threeDSecure) {
				processing($submit);
				$form.find('input[name=nonce]').val(payload.nonce);
				$form.off('submit', formSubmit);
				$form.submit();
			} else {
				if (window.braintreeError ) { window.braintreeError('3ds failed') }
				//dropinInstance.clearSelectedPaymentMethod();
				reset($submit);
				//$submit.prop('disabled', true);
			}
		});
	};
	function reset($button) {
		$button.prop('disabled', false);
		$button.text($button.data('text'));
	};
	function processing($button) {
		$button.prop('disabled', true);
		if ($button.data('processing')) {
			$button.text($button.data('processing'));
		}
	};
})();