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

var $token = $('#btToken'),
	$nonce = $('#btNonce'),
	$amount = $('#btAmount'),
	$form = $token.parents('form'),
	$submit = $form.find('input[type="submit"]'),
	hostedFields = null;

$form.attr('action', '/');

(function check() {
	if (typeof braintree !== 'undefined' && typeof braintree.client !== 'undefined') {
		init();
	} else {
		setTimeout(check, 50);
	}
})();

function init() {
	braintree.client.create(
		{
			authorization: $token.val()
		},
		function(clientErr, clientInstance) {
			if (clientErr) {
				console.error(clientErr);
				return;
			}

			// This example shows Hosted Fields, but you can also use this
			// client instance to create additional components here, such as
			// PayPal or Data Collector.

			braintree.hostedFields.create(
				{
					client: clientInstance,
					styles: {
						input: {
							'font-size': '14px'
						},
						'input.invalid': {
							color: 'red'
						},
						'input.valid': {
							color: 'green'
						}
					},
					fields: {
						number: {
							selector: '#card-number',
							placeholder: 'Card Number'
						},
						cvv: {
							selector: '#cvv',
							placeholder: 'CVV'
						},
						expirationDate: {
							selector: '#expiration-date',
							placeholder: 'MM / YYYY'
						}
					}
				},
				function(hostedFieldsErr, hostedFieldsInstance) {
					if (hostedFieldsErr) {
						console.error(hostedFieldsErr);
						return;
					}

					hostedFields = hostedFieldsInstance;
				}
			);
		}
	);
}

$('#make-payment').on('click', function(e) {
	//console.log('reset');
	$('#card-number').empty();
	$('#cvv').empty();
	$('#expiration-date').empty();
	init();
});

$form.on('submit', function(event) {
	//console.log($nonce.val());
	if ($nonce.val() == '') {
		event.preventDefault();

		hostedFields.tokenize(function(tokenizeErr, payload) {
			if (tokenizeErr) {
				console.error(tokenizeErr);
				return;
			}

			// If this was a real integration, this is where you would
			// send the nonce to your server.
			//console.log('Got a nonce: ' + payload.nonce);
			$nonce.val(payload.nonce);
			$form.off('submit');
			$form.trigger('submit');
		});
	}
});
