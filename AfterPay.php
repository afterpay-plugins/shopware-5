<?php

namespace AfterPay;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Models\Payment\Payment;

class AfterPay extends Plugin
{

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('colo_afterpay.plugin_dir', $this->getPath());
        parent::build($container);
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $this->createPaymentMethods($context);
        $this->createAttributes();
        $this->createTables();
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        if (!$context->keepUserData()) {
            $this->removePaymentMethods();
            $this->removeAttributes();
            $this->removeTables();
        }
        $context->scheduleClearCache(UninstallContext::CACHE_LIST_ALL);
    }

    /**
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context)
    {
        $version = $context->getCurrentVersion();
        if (version_compare($version, "1.9.1", "<=")) {
            $service = $this->container->get('shopware_attribute.crud_service');

            $service->update('s_core_countries_attributes', 'colo_afterpay_merchant_id', 'string', array(
                'label' => 'Afterpay Merchant ID',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 5,
                'custom' => true
            ));
        }

        if (version_compare($version, "1.9.2", "<=")) {
            $service = $this->container->get('shopware_attribute.crud_service');

            if ($service->get('s_core_countries_attributes', 'colo_afterpay_api_url')) {
                $service->delete('s_core_countries_attributes', 'colo_afterpay_api_url');
            }
            if ($service->get('s_core_countries_attributes', 'colo_afterpay_sandbox_api_url')) {
                $service->delete('s_core_countries_attributes', 'colo_afterpay_sandbox_api_url');
            }
        }

        if (version_compare($version, "1.9.10", "<=")) {
            $service = $this->container->get('shopware_attribute.crud_service');

            $service->update('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key', 'string', array(
                'label' => 'Afterpay Public Sandbox API key',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 3,
                'custom' => true
            ));
        }

        if (version_compare($version, "1.9.11", "<=")) {
            $service = $this->container->get('shopware_attribute.crud_service');

            $service->update('s_order_attributes', 'colo_transaction_mode', 'string', array(
                'translatable' => 0,
                'displayInBackend' => 0,
                'custom' => true
            ));
        }

        if (version_compare($version, "2.0.10", "<=")) {
            $service = $this->container->get('shopware_attribute.crud_service');

            if (!$service->get('s_order_attributes', 'colo_bank_account')) {
                $service->update('s_order_attributes', 'colo_bank_account', 'string', array(
                    'label' => 'Bank account',
                    'translatable' => 0,
                    'displayInBackend' => 1,
                    'custom' => true
                ));
            }
        }

        if (version_compare($version, "2.1.7", "<=")) {
            $service = $this->container->get('shopware_attribute.crud_service');

            if ($service->get('s_core_countries_attributes', 'colo_afterpay_api_key')) {
                $service->update('s_core_countries_attributes', 'colo_afterpay_api_key', 'string', array(
                    'translatable' => 1,
                ));
            }

            if ($service->get('s_core_countries_attributes', 'colo_afterpay_sandbox_api_key')) {
                $service->update('s_core_countries_attributes', 'colo_afterpay_sandbox_api_key', 'string', array(
                    'translatable' => 1,
                ));
            }

            if ($service->get('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key')) {
                $service->update('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key', 'string', array(
                    'translatable' => 1,
                ));
            }

            if ($service->get('s_core_countries_attributes', 'colo_afterpay_merchant_id')) {
                $service->update('s_core_countries_attributes', 'colo_afterpay_merchant_id', 'string', array(
                    'translatable' => 1,
                ));
            }
        }

        if (version_compare($version, "2.1.8", "<=")) {
            $this->createTables();
            $this->transferTableData();
            $this->removeOldTables();
        }

        $this->container->get('models')->generateAttributeModels(array('s_order_attributes', 's_core_countries_attributes', 's_core_paymentmeans_attributes'));

        $context->scheduleClearCache(UpdateContext::CACHE_LIST_ALL);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(ActivateContext::CACHE_LIST_ALL);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleClearCache(DeactivateContext::CACHE_LIST_ALL);
    }

    /**
     * Creates new payment methods
     * @param $context
     * @return bool
     */
    private function createPaymentMethods($context)
    {
        try {
            /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
            $installer = $this->container->get('shopware.plugin_payment_installer');
            $entityManager = $this->container->get('models');
            $repo = $entityManager->getRepository(Payment::class);

            $payment = $repo->findOneBy(['name' => 'colo_afterpay_invoice']);
            if (empty($payment)) {
                $installer->createOrUpdate($context->getPlugin(), [
                    'name' => 'colo_afterpay_invoice',
                    'description' => 'Rechnung',
                    'action' => 'ColoAfterpay',
                    'template' => 'afterpay_invoice.tpl',
                    'class' => 'afterpay_invoice.php',
                    'active' => 0,
                    'position' => 0,
                    'additionalDescription' => 'Erst erleben, dann flexibel bezahlen'
                ]);
            }

            $payment = $repo->findOneBy(['name' => 'colo_afterpay_dd']);
            if (empty($payment)) {
                $installer->createOrUpdate($context->getPlugin(), [
                    'name' => 'colo_afterpay_dd',
                    'description' => 'Lastschrift',
                    'action' => 'ColoAfterpay',
                    'template' => 'afterpay_debit.tpl',
                    'class' => 'afterpay_debit.php',
                    'active' => 0,
                    'position' => 0,
                    'additionalDescription' => 'Zahle bequem per Lastschrifteinzug'
                ]);
            }

            $payment = $repo->findOneBy(['name' => 'colo_afterpay_installment']);
            if (empty($payment)) {
                $installer->createOrUpdate($context->getPlugin(), [
                    'name' => 'colo_afterpay_installment',
                    'description' => 'Ratenzahlung',
                    'action' => 'ColoAfterpay',
                    'template' => 'afterpay_installment.tpl',
                    'class' => 'afterpay_installment.php',
                    'active' => 0,
                    'position' => 0,
                    'additionalDescription' => 'Zahle in Raten'
                ]);
            }

            $payment = $repo->findOneBy(['name' => 'colo_afterpay_campaigns']);
            if (empty($payment)) {
                $installer->createOrUpdate($context->getPlugin(), [
                    'name' => 'colo_afterpay_campaigns',
                    'description' => 'Kampagne',
                    'action' => 'ColoAfterpay',
                    'template' => 'afterpay_campaign.tpl',
                    'class' => 'afterpay_campaign.php',
                    'active' => 0,
                    'position' => 0,
                    'additionalDescription' => ''
                ]);
            }
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * Creates new attribute for order
     */
    private function createAttributes()
    {
        $service = $this->container->get('shopware_attribute.crud_service');

        if (!$service->get('s_order_attributes', 'colo_captured')) {
            $service->update('s_order_attributes', 'colo_captured', 'boolean', array(
                'translatable' => 0,
                'displayInBackend' => 0,
                'position' => 0,
                'custom' => true
            ));
        }

        if (!$service->get('s_order_attributes', 'colo_capture_number')) {
            $service->update('s_order_attributes', 'colo_capture_number', 'string', array(
                'label' => 'Capture number',
                'translatable' => 0,
                'displayInBackend' => 1,
                'position' => 1,
                'custom' => true
            ));
        }

        if (!$service->get('s_order_attributes', 'colo_transaction_mode')) {
            $service->update('s_order_attributes', 'colo_transaction_mode', 'string', array(
                'translatable' => 0,
                'displayInBackend' => 0,
                'custom' => true
            ));
        }

        if (!$service->get('s_order_attributes', 'colo_bank_account')) {
            $service->update('s_order_attributes', 'colo_bank_account', 'string', array(
                'label' => 'Bank account',
                'translatable' => 0,
                'displayInBackend' => 1,
                'custom' => true
            ));
        }

        if (!$service->get('s_core_countries_attributes', 'colo_afterpay_active')) {
            $service->update('s_core_countries_attributes', 'colo_afterpay_active', 'boolean', array(
                'label' => 'Afterpay Aktiv',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 0,
                'custom' => true
            ));
        }

        if (!$service->get('s_core_countries_attributes', 'colo_afterpay_api_key')) {
            $service->update('s_core_countries_attributes', 'colo_afterpay_api_key', 'string', array(
                'label' => 'Afterpay API key',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 1,
                'custom' => true
            ));
        }

        if (!$service->get('s_core_countries_attributes', 'colo_afterpay_sandbox_api_key')) {
            $service->update('s_core_countries_attributes', 'colo_afterpay_sandbox_api_key', 'string', array(
                'label' => 'Afterpay Sandbox API key',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 2,
                'custom' => true
            ));
        }

        if (!$service->get('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key')) {
            $service->update('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key', 'string', array(
                'label' => 'Afterpay Public Sandbox API key',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 3,
                'custom' => true
            ));
        }

        if (!$service->get('s_core_countries_attributes', 'colo_afterpay_merchant_id')) {
            $service->update('s_core_countries_attributes', 'colo_afterpay_merchant_id', 'string', array(
                'label' => 'Afterpay Merchant ID',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 5,
                'custom' => true
            ));
        }

        if (!$service->get('s_core_paymentmeans_attributes', 'colo_afterpay_min_basket_value')) {
            $service->update('s_core_paymentmeans_attributes', 'colo_afterpay_min_basket_value', 'float', array(
                'label' => 'Minimum basket value',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 0,
                'custom' => true
            ), null, false, 5);
        }

        if (!$service->get('s_core_paymentmeans_attributes', 'colo_afterpay_max_basket_value')) {
            $service->update('s_core_paymentmeans_attributes', 'colo_afterpay_max_basket_value', 'float', array(
                'label' => 'Maximum basket value',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 1,
                'custom' => true
            ), null, false, 1500);
        }

        if (!$service->get('s_core_paymentmeans_attributes', 'colo_afterpay_payment_status_id')) {
            $service->update('s_core_paymentmeans_attributes', 'colo_afterpay_payment_status_id', 'single_selection', array(
                'label' => 'Order payment status',
                'translatable' => 1,
                'displayInBackend' => 1,
                'position' => 2,
                'entity' => 'Shopware\Models\Order\Status|group:payment',
                'custom' => true
            ));
        }

        $this->container->get('models')->generateAttributeModels(array('s_order_attributes', 's_core_countries_attributes', 's_core_paymentmeans_attributes'));
    }

    /**
     * Creates tables
     * @return bool
     */
    private function createTables()
    {
        $entityManager = $this->container->get('models');
        $tool = new SchemaTool($entityManager);

        $classes = $this->getModelClasses();
        try {
            $tool->updateSchema($classes, true); // make sure to use the save mode
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function removePaymentMethods()
    {
        try {
            $entityManager = $this->container->get('models');
            $repo = $entityManager->getRepository(Payment::class);

            $payment = $repo->findOneBy(["name" => 'colo_afterpay_invoice']);
            if (!empty($payment)) {
                $entityManager->remove($payment);
                $entityManager->flush();
            }
            $payment = $repo->findOneBy(['name' => 'colo_afterpay_dd']);
            if (!empty($payment)) {
                $entityManager->remove($payment);
                $entityManager->flush();
            }
            $payment = $repo->findOneBy(['name' => 'colo_afterpay_installment']);
            if (!empty($payment)) {
                $entityManager->remove($payment);
                $entityManager->flush();
            }
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * Removes attributes
     */
    private function removeAttributes()
    {
        $service = $this->container->get('shopware_attribute.crud_service');

        if ($service->get('s_order_attributes', 'colo_captured')) {
            $service->delete('s_order_attributes', 'colo_captured');
        }

        if ($service->get('s_order_attributes', 'colo_capture_number')) {
            $service->delete('s_order_attributes', 'colo_capture_number');
        }

        if ($service->get('s_order_attributes', 'colo_transaction_mode')) {
            $service->delete('s_order_attributes', 'colo_transaction_mode');
        }

        if ($service->get('s_order_attributes', 'colo_bank_account')) {
            $service->delete('s_order_attributes', 'colo_bank_account');
        }

        if ($service->get('s_core_countries_attributes', 'colo_afterpay_active')) {
            $service->delete('s_core_countries_attributes', 'colo_afterpay_active');
        }

        if ($service->get('s_core_countries_attributes', 'colo_afterpay_api_key')) {
            $service->delete('s_core_countries_attributes', 'colo_afterpay_api_key');
        }

        if ($service->get('s_core_countries_attributes', 'colo_afterpay_sandbox_api_key')) {
            $service->delete('s_core_countries_attributes', 'colo_afterpay_sandbox_api_key');
        }

        if ($service->get('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key')) {
            $service->delete('s_core_countries_attributes', 'colo_afterpay_public_sandbox_api_key');
        }

        if ($service->get('s_core_countries_attributes', 'colo_afterpay_merchant_id')) {
            $service->delete('s_core_countries_attributes', 'colo_afterpay_merchant_id');
        }

        if ($service->get('s_core_paymentmeans_attributes', 'colo_afterpay_min_basket_value')) {
            $service->delete('s_core_paymentmeans_attributes', 'colo_afterpay_min_basket_value');
        }

        if ($service->get('s_core_paymentmeans_attributes', 'colo_afterpay_max_basket_value')) {
            $service->delete('s_core_paymentmeans_attributes', 'colo_afterpay_max_basket_value');
        }

        if ($service->get('s_core_paymentmeans_attributes', 'colo_afterpay_payment_status_id')) {
            $service->delete('s_core_paymentmeans_attributes', 'colo_afterpay_payment_status_id');
        }

        $this->container->get('models')->generateAttributeModels(array('s_order_attributes', 's_core_countries_attributes', 's_core_paymentmeans_attributes'));
    }

    /**
     * @return bool
     */
    private function removeTables()
    {
        $entityManager = $this->container->get('models');
        $tool = new SchemaTool($entityManager);

        $classes = $this->getModelClasses();
        try {
            $tool->dropSchema($classes);
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function transferTableData()
    {
        try {
            $sql = 'INSERT INTO `s_plugin_afterpay_transactions`
                SELECT * FROM `colo_afterpay_transactions`;';
            $this->container->get('db')->query($sql);
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function removeOldTables()
    {
        try {
            $sql = 'DROP TABLE IF EXISTS `colo_afterpay_transactions`;';
            $this->container->get('db')->query($sql);
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * Returns all corresponding models for this module.
     *
     * @return array
     */
    private function getModelClasses()
    {
        $entityManager = $this->container->get('models');
        return [
            $entityManager->getClassMetadata('AfterPay\Models\Transactions')
        ];
    }

}
