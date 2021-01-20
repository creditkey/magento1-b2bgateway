/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

const B2BGATEWAY_METHOD_CODE = 'b2bgateway';
var b2bgatewayElems = null;
var labelB2bGateway = null;
var oldB2bGatewayTitle = '';
const SMALL_LOGO_IMAGE = 'https://s3-us-west-2.amazonaws.com/creditkey-assets/sdk/ck-btn-small.svg';
const CREDIT_BASE_URL = 'https://www.creditkey.com/';
Payment.prototype.afterInit = function () {
    (this.afterInitFunc).each(function (init) {
        (init.value)();
    });
    var methods = document.getElementsByName('payment[method]');
    if (methods.length) {
        methods.forEach(function (elem) {
            if (elem.value == B2BGATEWAY_METHOD_CODE) {
                b2bgatewayElems = elem;
            }
        });

        if (b2bgatewayElems !== null) {
            labelB2bGateway = jQuery(b2bgatewayElems).parents('#dt_method_b2bgateway').find('label[for="p_method_' + B2BGATEWAY_METHOD_CODE + '"]');
            oldB2bGatewayTitle = labelB2bGateway.html();
            labelB2bGateway.html('Loading Credit Key...');
            checkout.setLoadWaiting('payment');

            var ckConfig = creditkey_config.ckConfig;
            var self = this;

            var ckClient = new ck.Client(ckConfig.publicKey, ckConfig.endpoint);

            jQuery.ajax({
                url: ckConfig.chargesUrl,
                type: "GET",
                dataType: 'json',
                success: function (totals) {
                    if (totals) {
                        var charges = new ck.Charges(totals.total,totals.totals, totals.tax, totals.discount_amount, totals.grand_total);
                        ckClient.get_marketing_display(charges, "checkout", ckConfig.type, ckConfig.size)
                            .then(function(res) {
                                originalOrderButtonVal = labelB2bGateway.html();
                                labelB2bGateway.html(res);
                            });
                    }
                }
            }).always(function () {
                checkout.setLoadWaiting(false);
            }).error(function () {
                checkout.ajaxFailure.bind(checkout)
            });
        }
    }
};