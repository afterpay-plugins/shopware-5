//{block name="backend/order/view/detail/window" append}

//{include file='backend/colo_afterpay/colo_afterpay_payments/model/list.js'}
//{include file='backend/colo_afterpay/colo_afterpay_payments/store/list.js'}
//{include file='backend/colo_afterpay/colo_afterpay_payments/view/grid.js'}
//{include file='backend/colo_afterpay/colo_afterpay_payments/view/form.js'}
//{include file='backend/colo_afterpay/colo_afterpay_payments/view/panel.js'}

Ext.define('Shopware.apps.ColoAfterpay.Order.view.detail.Window', {
    override: 'Shopware.apps.Order.view.detail.Window',
    createTabPanel: function () {
        var me = this,
            record = me.record,
            disabled = true;

        var afterpayPayments = record.get('colo_afterpay_payments');
        var paymentName = record.getPaymentStore.getAt(0).get('name');
        var captured = record.get('colo_captured');
        if (captured && afterpayPayments.indexOf(paymentName) > -1) {
            disabled = false;
        }

        var tabPanel = me.callOverridden(arguments);
        var afterpayPaymentTransactionsStore = Ext.create('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.store.List');
        afterpayPaymentTransactionsStore.getProxy().extraParams = {
            orderId: record.get('id')
        };
        var afterpayPaymentsTab = Ext.create('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.view.Panel', {
            store: afterpayPaymentTransactionsStore.load(),
            record: record,
            disabled: disabled
        });

        tabPanel.add(afterpayPaymentsTab);

        return tabPanel;
    }
});
//{/block}
