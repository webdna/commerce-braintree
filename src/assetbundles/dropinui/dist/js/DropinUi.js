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
console.log('here');

var $token = $('#btToken'),
	$nonce = $('#btNonce'),
	$amount = $('#btAmount');
//currency = $form.find('input[name="currency"]').val();

(function check() {
	if (typeof braintree !== 'undefined') {
		init();
	} else {
		setTimeout(check, 50);
	}
})();

function init() {
	braintree.dropin.create(
		{
			authorization: $token.val(),
			container: '#dropInUi',
			locale: 'en_GB',
			card: {
				cardholderName: {
					required: true
				}
			},
			paypal: {
				flow: 'checkout',
				env: 'sandbox',
				amount: $amount.val(),
				currency: $amount.data('currency'),
				buttonStyle: {
					color: 'blue',
					shape: 'rect',
					size: 'responsive',
					label: 'paypal'
				}
			},
			/*threeDSecure: {
				amount: $amount.val()
			},*/
			applePay: {
				displayName: 'DSD',
				paymentRequest: {
					total: {
						label: 'DSD',
						amount: $amount.val()
					}
				}
			},
			googlePay: {
				displayName: 'DSD',
				paymentRequest: {
					total: {
						label: 'DSD',
						amount: $amount.val()
					}
				}
			}
		},
		function(err, dropinInstance) {
			if (err) {
				console.error(err);
				return;
			}
			//reset();

			if (dropinInstance.isPaymentMethodRequestable()) {
				//reset();
			}
			//need for vault
			/*dropinInstance.on('paymentMethodRequestable', function(e) {
				reset();
			});*/
			dropinInstance.on('noPaymentMethodRequestable', function(e) {
				//processing();
			});

			$form.on('submit', function(e) {
				e.preventDefault();
				dropinInstance.requestPaymentMethod(function(err, payload) {
					if (err) {
						console.error(err);
						//reset();
						return;
					}
					console.log(payload);
					//if (payload.liabilityShifted || payload.type !== 'CreditCard') {
					//processing();
					$form.find('input[name=nonce]').val(payload.nonce);
					$form.off('submit', formSubmit);
					$form.submit();
					console.log('SUBMIT');
					//processing();
					/*} else {
						dropinInstance.clearSelectedPaymentMethod();
						reset();
					}*/
				});
			});
		}
	);
}
