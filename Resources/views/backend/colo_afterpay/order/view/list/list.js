//{namespace name=backend/colo_afterpay/index}
//{block name="backend/order/view/list/list" append}

Ext.define('Shopware.apps.ColoAfterpay.Order.view.list.List', {
    override: 'Shopware.apps.Order.view.list.List',
    getColumns: function () {
        var me = this;
        var columns = me.callParent(arguments), actionColumnIndex = -1, actionColumns;
        Ext.Array.each(columns, function (item, index) {
            if (Ext.getClassName(item) == "Ext.grid.column.Action") {
                actionColumnIndex = index;
                return false;
            }
        });
        if (actionColumnIndex > -1) {
            actionColumns = columns[actionColumnIndex];
            actionColumns.items.push(me.createCaptureAfterpayPaymentColumn());
            columns[actionColumnIndex] = Ext.create("Ext.grid.column.Action", {
                width: actionColumns.width + 45,
                items: actionColumns.items
            });
            actionColumns.destroy();
        }
        Ext.Array.each(columns, function (item, index) {
            if (typeof item.renderer === "undefined" && item.dataIndex === "number") {
                columns[index].renderer = me.backgroundRenderer;
            }
        });
        return columns;
    },
    createCaptureAfterpayPaymentColumn: function () {
        var me = this;
        return {
            iconCls: 'misc--send-feedback',
            action: 'captureAfterpayPayment',
            tooltip: '{s name="CapturePayment"}{/s}',
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore(),
                    record = store.getAt(rowIndex);

                me.fireEvent('captureAfterpayPayment', record);
            },
            getClass: function (value, metadata, record, rowIdx) {
                var orderStatus = record.get('status');
                var captureStatus = record.get('colo_capture_status');
                var captured = record.get('colo_captured');
                var afterpayPayments = record.get('colo_afterpay_payments');
                var paymentName = record.getPaymentStore.getAt(0).get('name');
                if (orderStatus != captureStatus || captured || afterpayPayments.indexOf(paymentName) === -1) {
                    return 'x-hidden';
                }
            }
        };
    },
    backgroundRenderer: function (value, metaData, record, rowIndex, colIndex, store) {
        var style = "background: #fff3cd;";
        if (record.get('colo_transaction_mode') === 'sandbox' || record.get('colo_transaction_mode') === 'public_sandbox') {
            return (Ext.isDefined(value)) ? Ext.String.format('<span style="[0]">[1]</span>', style, value) : value;
        }
        return value;
    }

});
//{/block}

