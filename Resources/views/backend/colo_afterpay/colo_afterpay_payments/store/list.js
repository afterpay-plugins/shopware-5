// store
Ext.define('Shopware.apps.ColoAfterpay.ColoAfterpayPayments.store.List', {
    /**
     * Define that this component is an extension of the Ext.data.Store
     */
    extend: 'Ext.data.Store',
    /**
     * Define how much rows loaded with one request
     */
    pageSize: 20,
    /**
     * Auto load the store after the component
     * is initialized
     * @boolean
     */
    autoLoad: false,
    /**
     * Enable remote sorting
     */
    remoteSort: true,
    /**
     * Enable remote filtering
     */
    remoteFilter: false,
    /**
     * Define the used model for this store
     * @string
     */
    model: 'Shopware.apps.ColoAfterpay.ColoAfterpayPayments.model.List',
    /**
     * Configure the data communication
     * @object
     */
    proxy: {
        type: 'ajax',
        /**
         * Configure the url mapping for the different
         * store operations based on
         * @object
         */
        url: '{url controller="ColoAfterpay" action="transactions"}',
        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
