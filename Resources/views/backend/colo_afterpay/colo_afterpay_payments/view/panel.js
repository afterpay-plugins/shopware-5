//{namespace name=backend/colo_afterpay/index}

Ext.define('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.view.Panel', {

    /**
     * Define that the additional information is an Ext.panel.Panel extension
     * @string
     */
    extend: 'Ext.container.Container',

    /**
     * Defines the component layout.
     */
    layout: 'auto',
    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias: 'widget.colo-afterpay-payments-panel',

    /**
     * An optional extra CSS class that will be added to this component's Element.
     */
    cls: Ext.baseCSSPrefix + 'document-panel',

    /**
     * A shortcut for setting a padding style on the body element. The value can either be a number to be applied to all sides, or a normal css string describing padding.
     */
    padding: 10,

    /**
     * True to use overflow:'auto' on the components layout element and show scroll bars automatically when necessary, false to clip any overflowing content.
     */
    autoScroll: true,

    /**
     * Contains all snippets for the view component
     * @object
     */
    snippets: {
        title: '{s name=AfterpayTabWindowTitle}Payments{/s}'
    },

    initComponent: function () {
        var me = this;

        me.items = [
            me.createGrid(),
            me.createForm()
        ];
        me.title = me.snippets.title;
        me.callParent(arguments);
    },

    createGrid: function () {
        var me = this;

        me.grid = Ext.create('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.view.Grid', {
            store: me.store,
            minHeight: 150,
            minWidth: 250,
            region: 'center',
            style: 'margin-bottom: 10px;'
        });

        return me.grid;
    },

    createForm: function () {
        var me = this;

        me.form = Ext.create('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.view.Form', {
            region: 'bottom',
            orderRecord: me.record,
            transactionStore: me.store
        });

        return me.form;
    }

});