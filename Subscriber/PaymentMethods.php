<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentMethods implements SubscriberInterface
{

    /**
     * @var string
     */
    private $componentsDir;

    /**
     * @var ContainerInterface
     */
    private $container;

    public static function getSubscribedEvents()
    {
        return array(
            'Shopware_Modules_Admin_InitiatePaymentClass_AddClass' => 'addPaymentClass'
        );
    }

    /**
     * @param ContainerInterface $container
     */
    public function __construct($componentsDir, ContainerInterface $container)
    {
        $this->componentsDir = $componentsDir;
        $this->container = $container;
    }

    /**
     * This method registers the debit payment method handler
     *
     * @param \Enlight_Event_EventArgs $args
     * @return array
     */
    public function addPaymentClass(\Enlight_Event_EventArgs $args)
    {
        $dirs = $args->getReturn();

        Shopware()->Loader()->registerNamespace('ShopwarePlugin\PaymentMethods\Components', $this->componentsDir);

        $dirs['afterpay_debit'] = 'ShopwarePlugin\PaymentMethods\Components\AfterpayDebitPaymentMethod';
        $dirs['afterpay_installment'] = 'ShopwarePlugin\PaymentMethods\Components\AfterpayInstallmentPaymentMethod';
        $dirs['afterpay_invoice'] = 'ShopwarePlugin\PaymentMethods\Components\AfterpayInvoicePaymentMethod';
        $dirs['afterpay_campaign'] = 'ShopwarePlugin\PaymentMethods\Components\AfterpayCampaignPaymentMethod';

        return $dirs;
    }

}