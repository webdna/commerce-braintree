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
		if (typeof braintree !== 'undefined' && typeof braintree.client !== 'undefined') {
			init();
		} else {
			setTimeout(check, 50);
		}
	})();

	function init() {

		$('.gateway-form form').each(function(){
			var $form = $(this);
			
			if ($form.find('[data-bt-hostedFields]')[0]) {

				var $token = $form.find('[name*="gatewayToken"]'),
					$nonce = $form.find('[name*="nonce"]'),
					$amount = $form.find('[name*="amount"]'),
					$currency = $form.find('[name*="currency"]'),
					$submit = $form.find('input[type*="submit"]');

					braintree.client.create(
						{
							authorization: $token.val()
						},
						function(clientErr, clientInstance) {
							if (clientErr) {
								console.error(clientErr);
								return;
							}
				
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
				
									$form.on('submit', { hostedFields: hostedFieldsInstance }, formSubmit);
								}
							);
						}
					);
			}
		});
	}

	function formSubmit(e) {
		e.preventDefault();

		var hostedFields = e.data.hostedFields,
			$form = $(e.currentTarget);

			hostedFields.tokenize(function(err, payload) {
				if (err) {
					console.error(err);
					return;
				}

				$form.find('input[name*=nonce]').val(payload.nonce);
				$form.off('submit', formSubmit);
				$form[0].submit();
			});
	}
})()


