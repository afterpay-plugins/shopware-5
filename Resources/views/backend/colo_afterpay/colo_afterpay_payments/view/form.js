//{namespace name="backend/colo_afterpay/index"}

Ext.define('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.view.Form', {
    /**
     * The configuration is an extension of the form panel
     */
    extend: 'Ext.form.Panel',
    /**
     * The configuration panel uses the column layout
     */
    layout: 'anchor',
    cls: 'shopware-form',
    /**
     * A shortcut for setting a padding style on the body element. The value can either be a number to be applied to all sides, or a normal css string describing padding.
     */
    bodyPadding: 10,
    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias: 'widget.colo-afterpay-payments-form',
    /**
     * Default configuration for the left and right container
     * @object
     */
    formDefaults: {
        labelWidth: 155,
        style: 'margin-bottom: 10px !important;padding: 5px;',
        labelStyle: 'font-weight: 700;',
        anchor: '100%'
    },
    /**
     * Contains all snippets for the view component
     * @object
     */
    snippets: {
        amount: '{s name=AfterpayTabLabelAmount}Amount{/s}',
        creditNoteNumber: '{s name=AfterpayTabLabelCreditNoteNumber}Credit note number{/s}',
        buttons: {
            refund: '{s name=AfterpayTabButtonRefund}Refund{/s}'
        },
        successTitle: '{s name=AfterpayTabSuccessTitle}Success Title{/s}',
        successMessage: '{s name=AfterpayTabSuccessMessage}Success Message{/s}',
        failureTitle: '{s name=AfterpayTabFailureTitle}Failure Title{/s}',
        failureMessage: '{s name=AfterpayTabFailureMessage}Failure Message{/s}',
        growlMessage: '{s name=AfterpayTabGrowlMessage}Growl Message{/s}'
    },
    initComponent: function () {
        var me = this;

        me.items = me.createItems();
        me.buttons = me.createButtons();
        me.callParent(arguments);
    },
    /**
     * Registers the custom component events.
     * @return void
     */
    registerEvents: function () {
        this.addEvents('refund');
    },
    /**
     * @return Ext.container.Container
     */
    createItems: function () {
        var me = this;

        return [
            {
                xtype: 'hiddenfield',
                hidden: true,
                name: 'ordernumber',
                value: me.orderRecord.get('number')
            },
            {
                xtype: 'numberfield',
                fieldLabel: me.snippets.amount,
                name: 'amount',
                maxValue: me.orderRecord.get('invoiceAmount'),
                minValue: 0,
                value: me.orderRecord.get('invoiceAmount')
            },
            {
                xtype: 'textfield',
                fieldLabel: me.snippets.creditNoteNumber,
                name: 'creditNoteNumber'
            }
        ];
    },
    /**
     * Creates the form buttons create, reset and preview.
     * @return array
     */
    createButtons: function () {
        var me = this;

        me.refundButton = Ext.create('Ext.button.Button', {
            text: me.snippets.buttons.refund,
            action: 'refund',
            cls: 'primary',
            handler: function (btn) {
                var form = btn.up('form'),
                    params = form.getForm().getValues();
                if (params.amount <= 0) {
                    Shopware.Notification.createGrowlMessage(me.snippets.failureTitle, me.snippets.failureMessage, me.snippets.growlMessage);
                    return false;
                }
                Ext.Ajax.request({
                    url: '{url controller="ColoAfterpay" action="refund"}',
                    method: 'POST',
                    params: params,
                    success: function (response, opts) {
                        var responseObj = JSON.parse(response.responseText);
                        if (responseObj.success) {
                            Shopware.Notification.createGrowlMessage(me.snippets.successTitle, me.snippets.successMessage, me.snippets.growlMessage);
                        } else {
                            Shopware.Notification.createGrowlMessage(me.snippets.failureTitle, me.snippets.failureMessage, me.snippets.growlMessage);
                        }
                        me.transactionStore.load();
                    },
                    failure: function (response, opts) {
                        Shopware.Notification.createGrowlMessage(me.snippets.failureTitle, me.snippets.failureMessage, me.snippets.growlMessage);
                    }
                });
            }
        });

        return [
            me.refundButton
        ];
    }

});