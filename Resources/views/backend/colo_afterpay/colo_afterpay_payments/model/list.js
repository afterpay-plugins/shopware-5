// model
Ext.define('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.model.List', {
    /**
     * Extends the standard Ext Model
     * @string
     */
    extend: 'Shopware.data.Model',
    /**
     * unique id
     * @int
     */
    idProperty: 'id',
    /**
     * The fields used for this model
     * @array
     */
    fields: [
        {
            name: 'id', type: 'int'
        },
        {
            name: 'orderId', type: 'int'
        },
        {
            name: 'number', type: 'string'
        },
        {
            name: 'amount', type: 'float'
        },
        {
            name: 'status', type: 'int'
        },
        {
            name: 'date', type: 'date'
        }
    ]
});