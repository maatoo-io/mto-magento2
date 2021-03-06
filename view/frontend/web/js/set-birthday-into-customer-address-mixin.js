/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/quote',
    'mage/storage',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'Magento_Checkout/js/model/error-processor'
], function (wrapper, resourceUrlManager, quote, storage, shippingService, rateRegistry, errorProcessor) {
    'use strict';
    return function (target) {
        let getRatesF = target.getRates;

        getRatesF = wrapper.wrap(getRatesF, (originalAction, address) => {

            var cache, serviceUrl, payload;


            shippingService.isLoading(true);
            cache = rateRegistry.get(address.getCacheKey());
            serviceUrl = resourceUrlManager.getUrlForEstimationShippingMethodsForNewAddress(quote);

            let extAttributes = {};
            if (address.customAttributes) {
                let attribute = address.customAttributes.find(
                    function (element) {
                        return element.attribute_code === 'birthday';
                    }
                );

                if (attribute) {
                    extAttributes['birthday'] = attribute.value;
                }
            }

            payload = JSON.stringify({
                    address: {
                        'street': address.street,
                        'city': address.city,
                        'region_id': address.regionId,
                        'region': address.region,
                        'country_id': address.countryId,
                        'postcode': address.postcode,
                        'email': address.email,
                        'customer_id': address.customerId,
                        'firstname': address.firstname,
                        'lastname': address.lastname,
                        'middlename': address.middlename,
                        'prefix': address.prefix,
                        'suffix': address.suffix,
                        'vat_id': address.vatId,
                        'company': address.company,
                        'telephone': address.telephone,
                        'fax': address.fax,
                        'custom_attributes': address.customAttributes,
                        'save_in_address_book': address.saveInAddressBook,
                        'extension_attributes': extAttributes
                    }
                }
            );

            if (cache) {
                shippingService.setShippingRates(cache);
                shippingService.isLoading(false);
            } else {
                storage.post(
                    serviceUrl, payload, false
                ).done(function (result) {
                    rateRegistry.set(address.getCacheKey(), result);
                    shippingService.setShippingRates(result);
                }).fail(function (response) {
                    shippingService.setShippingRates([]);
                    errorProcessor.process(response);
                }).always(function () {
                    shippingService.isLoading(false);
                });
            }
        })

        target.getRates =  getRatesF;

        return target;
    }

});
