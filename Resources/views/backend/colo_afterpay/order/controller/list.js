//{namespace name=backend/colo_afterpay/index}
//{block name="backend/order/controller/list" append}

Ext.define("Shopware.apps.ColoAfterpay.Order.controller.List", {
    override: "Shopware.apps.Order.controller.List",
    init: function () {
        var me = this;
        me.callParent(arguments);
        me.subApplication.getController("Shopware.apps.ColoAfterpay.Order.controller.ListDispatch");
    }
});

Ext.define('Shopware.apps.ColoAfterpay.Order.controller.ListDispatch', {
    extend: "Ext.app.Controller",
    snippets: {
        growlMessage: "{s name='CapturePaymentGrowlMessage'}growlMessage{/s}",
        capturePayment: {
            message: "{s name='CapturePaymentMessage'}message{/s}",
            title: "{s name='CapturePaymentTitle'}title{/s}",
            successTitle: "{s name='CapturePaymentSuccessTitle'}successTitle{/s}",
            failureTitle: "{s name='CapturePaymentFailureTitle'}failureTitle{/s}",
            successMessage: "{s name='CapturePaymentSuccessMessage'}successMessage{/s}",
            failureMessage: "{s name='CapturePaymentFailureMessage'}failureMessage{/s}"
        }
    },
    init: function () {
        var me = this;
        me.callParent(arguments);
        me.control({
            "order-list-main-window order-list": {
                captureAfterpayPayment: me.onCaptureAfterpayPayment
            }
        });
    },
    onCaptureAfterpayPayment: function (record) {
        var me = this,
            message = me.snippets.capturePayment.message + ' ' + record.get('number'),
            title = me.snippets.capturePayment.title;

        // we do not just capture - we ask the user if he is sure.
        Ext.MessageBox.confirm(title, message, function (response) {
            if (response !== 'yes') {
                return;
            }
            Ext.Ajax.request({
                url: '{url controller="ColoAfterpay" action="capture"}',
                method: 'POST',
                params: {
                    ordernumber: record.get('number')
                },
                success: function (response, operation) {
                    var responseObj = Ext.decode(response.responseText);
                    if (responseObj.success === true) {
                        Shopware.Notification.createGrowlMessage(me.snippets.capturePayment.successTitle, me.snippets.capturePayment.successMessage, me.snippets.growlMessage);
                    } else {
//                        var records = operation.getRecords(),
//                                record = records[0],
//                                proxyReader = record.getProxy().getReader(),
//                                rawData = proxyReader.rawData;
                        Shopware.Notification.createGrowlMessage(me.snippets.capturePayment.failureTitle, me.snippets.capturePayment.failureMessage, me.snippets.growlMessage);
                    }
                    me.getStore("Shopware.apps.Order.store.Order").load();
                }
            });
        });
    }
});
//{/block}
