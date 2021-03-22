//{namespace name="backend/colo_afterpay/index"}

Ext.define('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.view.Grid', {
    /**
     * Extend from the standard ExtJS 4
     * @string
     */
    extend: 'Ext.grid.Panel',
    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias: 'widget.colo-afterpay-payments-list',
    /**
     * Set css class
     * @string
     */
    cls: Ext.baseCSSPrefix + 'document-grid',
    /**
     * The view needs to be scrollable
     * @string
     */
    autoScroll: true,
    /**
     * Contains all snippets for the view component
     * @object
     */
    snippets: {
        columns: {
            number: '{s name=AfterpayTabColumnNumber}Number{/s}',
            date: '{s name=AfterpayTabColumnDate}Date{/s}',
            amount: '{s name=AfterpayTabColumnAmount}Amount{/s}',
            status: '{s name=AfterpayTabColumnStatus}Status{/s}'
        }
    },
    initComponent: function () {
        var me = this;
        me.columns = me.getColumns();
        me.dockedItems = [me.getPagingBar()];
        me.callParent(arguments);
    },
    /**
     * Creates the grid columns.
     */
    getColumns: function () {
        var me = this;

        return [{
            header: me.snippets.columns.number,
            dataIndex: 'number',
            flex: 2
        }, {
            header: me.snippets.columns.amount,
            dataIndex: 'amount',
            flex: 1,
            renderer: me.amountColumn
        }, {
            header: me.snippets.columns.date,
            dataIndex: 'date',
            flex: 1,
            renderer: me.dateColumn
        }, {
            header: me.snippets.columns.status,
            dataIndex: 'status',
            flex: 1,
            renderer: me.statusColumn
        }
        ];
    },
    /**
     * Creates pagingbar
     *
     * @return Ext.toolbar.Paging
     */
    getPagingBar: function () {
        var me = this;
        var pageSize = Ext.create('Ext.form.field.ComboBox', {
            labelWidth: 120,
            cls: Ext.baseCSSPrefix + 'page-size',
            queryMode: 'local',
            width: 180,
            listeners: {
                scope: me,
                select: me.onPageSizeChange
            },
            store: Ext.create('Ext.data.Store', {
                fields: ['value'],
                data: [
                    {
                        value: '20'
                    },
                    {
                        value: '40'
                    },
                    {
                        value: '60'
                    },
                    {
                        value: '80'
                    },
                    {
                        value: '100'
                    },
                    {
                        value: '250'
                    }
                ]
            }),
            displayField: 'value',
            valueField: 'value'
        });
        pageSize.setValue(me.store.pageSize);

        var pagingBar = Ext.create('Ext.toolbar.Paging', {
            store: me.store,
            dock: 'bottom',
            displayInfo: true
        });

        pagingBar.insert(pagingBar.items.length - 2, [{
            xtype: 'tbspacer',
            width: 6
        }, pageSize]);
        return pagingBar;
    },
    /**
     * Event listener method which fires when the user selects
     * a entry in the "number of orders"-combo box.
     *
     * @event select
     * @param [object] combo - Ext.form.field.ComboBox
     * @param [array] records - Array of selected entries
     * @return void
     */
    onPageSizeChange: function (combo, records) {
        var record = records[0],
            me = this;

        me.store.pageSize = record.get('value');
        me.store.loadPage(1);
    },
    /**
     * Column renderer function which formats the date column of the document grid.
     * @param value
     */
    dateColumn: function (value) {
        if (!Ext.isDate(value)) {
            return value;
        }
        return Ext.util.Format.date(value);
    },
    /**
     * Column renderer function which formats the amount column with the Ext.util.Format.currency() function.
     * @param value
     */
    amountColumn: function (value) {
        if (!Ext.isNumeric(value)) {
            return value;
        }
        return Ext.util.Format.currency(value);
    },
    statusColumn: function (value) {
        return value;
    }
});