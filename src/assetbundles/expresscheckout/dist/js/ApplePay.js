
(function(){
	
	let csrfTokenValue = null;
	
	(function check() {
		if (typeof braintree !== 'undefined') {
			init();
		} else {
			setTimeout(check, 50);
		}
	})();
	
	function init() {
		document.querySelectorAll('form').forEach(($form) => {
			const $nonce = $form.querySelector('[name*="nonce"]'),
				$applePay = $form.querySelector('apple-pay-button'),
				$processing = $form.querySelector('[id="processing"]');
				
			if ($applePay) {
				
				if (window.ApplePaySession && ApplePaySession.supportsVersion(3) && ApplePaySession.canMakePayments()) {
					// This device supports version 3 of Apple Pay.
					
					braintree.client.create({
						  authorization: orderData.token,
					}, function(clientErr, clientInstance) {
						  if (clientErr) {
							console.error('Error creating client:', clientErr);
							return;
						}
						
						braintree.applePay.create({
							client: clientInstance
						}, function(applePayErr, applePayInstance) {
							if (applePayErr) {
								console.error('Error creating applePayInstance:', applePayErr);
								return;
							}
							
							$applePay.style.display = 'inline-block';
	
							$applePay.addEventListener('click', (e) => {
								let paymentRequestData = applePayInstance.createPaymentRequest();
								
								paymentRequestData.supportedCountries = orderData.allowedCountryCodes;
								
								paymentRequestData.requiredShippingContactFields = [
									"postalAddress",
									"name",
									"email",
									"phone",
								];
								
								paymentRequestData.total = newTransactionInfo(orderData);
								paymentRequestData.lineItems = newLineItems(orderData);
								paymentRequestData.shippingMethods = newShippingOptionParameters(orderData.shippingOptions);
								
								let session = new ApplePaySession(3, paymentRequestData);
								
								session.onvalidatemerchant = function(e) {
									applePayInstance.performValidation({
										validationURL: e.validationURL,
										displayName: orderData.store,
									}, function(err, merchantSession) {
										if (err) {
											console.log(err)
											return;
										}
										
										session.completeMerchantValidation(merchantSession);
									})
								}
								
								session.onpaymentauthorized = function(e) {
									
									applePayInstance.tokenize({
										token: e.payment.token,
									}, function(tokenizeErr, payload) {
										if (tokenizeErr) {
											console.error('Error tokenizing Apple Pay:', tokenizeErr);
											session.completePayment(ApplePaySession.STATUS_FAILURE);
											return;
										}
										
										updateCart({
											shippingAddress: e.shippingContact,
											email: e.payment.shippingContact.emailAddress,
										})
										.then((result) => {
											session.completePayment(ApplePaySession.STATUS_SUCCESS);
											$form.querySelector('[name*="nonce"]').value = payload.nonce;
											
											if ($processing) {
												$processing.style.display = 'block';
											}
											
											$form.submit();
										})
									})
								}
								
								session.onshippingcontactselected = function(e) {
									
									getSessionInfo()
									.then(result => {
										updateCart({
											shippingAddress: e.shippingContact
										})
										.then((result) => {
											let errors = [];
											if (result.cart.availableShippingMethodOptions.length == 0) {
												errors.push(new ApplePayError("addressUnserviceable", "countryCode", "Call us for shipping"));
											}
											
											session.completeShippingContactSelection({
												newTotal: newTransactionInfo(result.cart),
												newLineItems: newLineItems(result.cart),
												newShippingMethods: newShippingOptionParameters(result.cart.availableShippingMethodOptions, result.cart.shippingMethodHandle),
												errors: errors
												
											})
										})
									})
								}
								
								session.onshippingmethodselected = function(e) {
									getSessionInfo()
									.then(result => {
										updateCart({
											shippingMethod: e.shippingMethod.identifier
										})
										.then((result) => {
											session.completeShippingMethodSelection({
												newTotal: newTransactionInfo(result.cart),
												newLineItems: newLineItems(result.cart),
												newShippingMethods: newShippingOptionParameters(result.cart.availableShippingMethodOptions, result.cart.shippingMethodHandle),
												
											})
										})
									})
								}
								
								session.begin();
							})
						});
					})
				}
				
			}
		})
	}
	
	
	function getSessionInfo() {
	  return fetch('/actions/users/session-info', {
		headers: {
		  'Accept': 'application/json',
		},
	  })
	  .then(response => response.json())
	  .then(session => {
		  csrfTokenValue = session.csrfTokenValue;
	  });
	};
	
	function updateCart(data={}) {
		
		data = Object.assign({
			shippingAddress: null,
			shippingMethod: null,
			email: null,
		}, data);
		
		var params = new FormData();
		
		if (data.shippingAddress) {
			params.append('billingAddressSameAsShipping', 1);
			params.append('shippingAddress[administrativeArea]', data.shippingAddress.administrativeArea);
			params.append('shippingAddress[countryCode]', data.shippingAddress.countryCode);
			params.append('shippingAddress[postalCode]', data.shippingAddress.postalCode);
			params.append('shippingAddress[locality]', data.shippingAddress.locality);
			params.append('shippingAddress[fullName]', data.shippingAddress.name || '-');
			params.append('shippingAddress[addressLine1]', getAddressLine(data.shippingAddress, 1, '-'));
			params.append('shippingAddress[addressLine2]', getAddressLine(data.shippingAddress, 2, '-'));
			params.append('shippingAddress[addressLine3]', getAddressLine(data.shippingAddress, 3, '-'));
			params.append('shippingAddress[fields][phoneNumber]', data.shippingAddress.phoneNumber || '1234567890');
		}
		if (data.shippingMethod) {
			params.append('shippingMethodHandle', data.shippingMethod);
		} else {
			let shippingMethod = null;
			orderData.shippingOptions.forEach(() => {
				if (this.selected) {
					shippingMethod = this.handle;
				}
			})
			params.append('shippingMethodHandle', shippingMethod || '');
		}
		if (data.email) {	
			params.append('email', data.email);
		} else {
			params.append('email', 'test@test.test');
		}
		  
		return fetch('/actions/commerce/cart/update-cart', {
			  method: 'POST',
			  headers: {
				  "Accept": "application/json",
				  'X-CSRF-Token': csrfTokenValue,
				  'X-Requested-With': 'XMLHttpRequest',
				},
				body: params
		  })
		  .then(response => response.json())
	};
	
	function newShippingOptionParameters(availableOptions, selected=null) {
		var options = [];
		  for (const prop in availableOptions) {
			  options.push({
				  identifier: availableOptions[prop].handle,
				  label: availableOptions[prop].name,
				  detail: '',
				  amount: availableOptions[prop].price,
				  selected: availableOptions[prop].handle == selected,
			  })
		  }
		  return options;
	}
	
	function newLineItems(cart) {
		return [
			{
				label: 'TAX',
				amount: cart.totalTax.toString(),
				pending: false
			},
			{
				label: 'SHIPPING',
				amount: cart.totalShippingCost.toString(),
				pending: false
			}
		];
	}
	
	function newTransactionInfo(cart) {
		
		return {
			label: orderData.store,
			amount: cart.total.toString(),
			pending: false,
		}
	}
	
	function getAddressLine(address, line, d='') {
		if (address['address'+line]) {
			return address['address'+line];
		}
		if (address.hasOwnProperty('addressLines')) {
			if (address.addressLines.length-1 >= line) {
				return address.addressLines[line-1];
			}
		}
		return d;
	}
	
})()