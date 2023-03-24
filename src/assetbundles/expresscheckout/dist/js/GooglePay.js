
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
				$googlePay = $form.querySelector('[data-id="google-pay"]'),
				$processing = $form.querySelector('[id="processing"]');
				
			if ($googlePay) {
				
				const paymentsClient = new google.payments.api.PaymentsClient({
					environment: $googlePay.dataset.env,
					merchantInfo: {
						merchantName: orderData.store,
						merchantId: $googlePay.dataset.googlePayId
					},
					paymentDataCallbacks: {
						onPaymentAuthorized: function(paymentData) {
							return new Promise(function(resolve, reject){
							
								updateCart(paymentData.shippingAddress, paymentData.shippingOptionData, paymentData.email)
								.then((result) => {
									resolve({transactionState: 'SUCCESS'});
								})
							});
						},
						onPaymentDataChanged: function(intermediatePaymentData) {
							let shippingAddress = intermediatePaymentData.shippingAddress;
							let shippingOptionData = intermediatePaymentData.shippingOptionData;
							let paymentDataRequestUpdate = {};
						
							if (intermediatePaymentData.callbackTrigger == "INITIALIZE" || intermediatePaymentData.callbackTrigger == "SHIPPING_ADDRESS") {
								return new Promise(function(resolve, reject) {
									resolve(
										getSessionInfo()
										.then(result => {
											return updateCart(shippingAddress, shippingOptionData)
											.then(result => {
												let availableShippingMethodOptions = result.cart.availableShippingMethodOptions;
												paymentDataRequestUpdate.newShippingOptionParameters = newShippingOptionParameters(availableShippingMethodOptions);
												paymentDataRequestUpdate.newTransactionInfo = newTransactionInfo(result.cart);
												return paymentDataRequestUpdate;
											})
										})
									)
									
								})
							}
							
							if (intermediatePaymentData.callbackTrigger == "SHIPPING_OPTION") {
								return new Promise(function(resolve, reject) {
									resolve(
										updateCart(shippingAddress, shippingOptionData)
										.then(result => {
											let availableShippingMethodOptions = result.cart.availableShippingMethodOptions;
											paymentDataRequestUpdate.newTransactionInfo = newTransactionInfo(result.cart);
											return paymentDataRequestUpdate;
										})
									)
								})
							}
						}
					}
				});
				
				braintree.client.create({
					authorization: orderData.token,
				}, function(clientErr, clientInstance) {
					
					let settings = {
						client: clientInstance,
						googlePayVersion: 2,
					}
					if ($googlePay.dataset.googlePayId) {
						settings.googleMerchantId = $googlePay.dataset.googlePayId;
					}
					
					braintree.googlePayment.create(settings, function(googlePaymentErr, googlePaymentInstance) {
						paymentsClient.isReadyToPay({
							// see https://developers.google.com/pay/api/web/reference/object#IsReadyToPayRequest
							apiVersion: 2,
							apiVersionMinor: 0,
							allowedPaymentMethods: googlePaymentInstance.createPaymentDataRequest().allowedPaymentMethods,
							existingPaymentMethodRequired: true // Optional
						})
						.then(function(response) {
							if (response.result) {
								const button = paymentsClient.createButton({
									buttonSizeMode: 'fill',
									buttonColor: $googlePay.dataset.color || 'default',
									buttonType: $googlePay.dataset.type || 'plain',
									buttonLocale: $googlePay.dataset.locale || 'en',
									onClick: function(e) {
									
										let paymentDataRequest = googlePaymentInstance.createPaymentDataRequest();
										
										paymentDataRequest.transactionInfo = newTransactionInfo(orderData);
										paymentDataRequest.merchantInfo = {
											merchantName: orderData.store,
										}
										if ($googlePay.dataset.googlePayId) {
											paymentDataRequest.merchantInfo.merchantId = $googlePay.dataset.googlePayId;
										}
										
										paymentDataRequest.callbackIntents = ["SHIPPING_ADDRESS",  "SHIPPING_OPTION", "PAYMENT_AUTHORIZATION"];
										paymentDataRequest.emailRequired = true;
										paymentDataRequest.shippingAddressRequired = true;
										paymentDataRequest.shippingAddressParameters = {
											allowedCountryCodes: orderData.allowedCountryCodes,
											phoneNumberRequired: true
										}
										paymentDataRequest.shippingOptionRequired = true;
										
										paymentsClient.loadPaymentData(paymentDataRequest)
										.then((paymentData) => {
											googlePaymentInstance.parseResponse(paymentData, function (err, result) {
												if (err) {
													console.log(err)
												}
												
												$form.querySelector('[name*="nonce"]').value = result.nonce;
												
												if ($processing) {
													$processing.style.display = 'block';
												}
												
												$form.submit();
												
												
											})
										})
									}
								});
								$googlePay.style.display = 'inline-block';
								$googlePay.appendChild(button);
								// @todo prefetch payment data to improve performance after confirming site functionality
								// prefetchGooglePaymentData();
							}
						})
						.catch(function(err) {
							// show error in developer console for debugging
							console.error(err);
							$googlePay.remove();
						});
					});
				})
				
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
			params.append('shippingAddress[administrativeArea]', shippingAddress.administrativeArea);
			params.append('shippingAddress[countryCode]', shippingAddress.countryCode);
			params.append('shippingAddress[postalCode]', shippingAddress.postalCode);
			params.append('shippingAddress[locality]', shippingAddress.locality);
			params.append('shippingAddress[fullName]', shippingAddress.name || '-');
			params.append('shippingAddress[addressLine1]', shippingAddress.address1 || '-');
			params.append('shippingAddress[addressLine2]', shippingAddress.address2 || '');
			params.append('shippingAddress[addressLine3]', shippingAddress.address3 || '');
			params.append('shippingAddress[fields][phoneNumber]', shippingAddress.phoneNumber || '1234567890');
		}
		if (data.shippingMethod) {
			params.append('shippingMethodHandle', shippingOptionData.id);
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
	
	function newShippingOptionParameters(availableOptions) {
		var options = [];
		  for (const prop in availableOptions) {
			  options.push({
				  id: availableOptions[prop].handle,
				  label: availableOptions[prop].name+' - '+availableOptions[prop].priceAsCurrency
			  })
		  }
		  return {
			  shippingOptions: options
		  };
	}
	
	function newTransactionInfo(cart) {
		return {
			countryCode: 'US',
			currencyCode: cart.currency,
			totalPriceStatus: 'FINAL',
			totalPrice: cart.total.toString(),
			totalPriceLabel: 'Total',
			displayItems: [
				{
				  label: "Subtotal",
				  type: "SUBTOTAL",
				  price: cart.itemSubtotal.toString(),
				},
				{
				  label: "Tax",
				  type: "TAX",
				  price: cart.totalTax.toString(),
				},
				{
				  label: "Shipping",
				  type: "LINE_ITEM",
				  price: cart.totalShippingCost.toString(), // Won't be displayed since status is PENDING
				  status: cart.shippingMethodHandle ? "FINAL" : "PENDING",
				}
			]
		}
	}
	
})()