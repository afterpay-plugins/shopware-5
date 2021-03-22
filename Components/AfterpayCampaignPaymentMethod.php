<?php

namespace ShopwarePlugin\PaymentMethods\Components;

/**
 * Replacement class for legacy core/paymentmeans/afterpay_campaigns.php class.
 *
 * Class AfterpayCampaignPaymentMethod
 * Used to handle debit payment
 *
 * @package ShopwarePlugin\PaymentMethods\Components
 */
class AfterpayCampaignPaymentMethod extends AfterpayPaymentMethod
{

    const PAYMENTNAME = "colo_afterpay_campaigns";

    /**
     * @inheritdoc
     */
    public function validate($paymentData)
    {
        $this->setPaymentName(self::PAYMENTNAME);
        return parent::validate($paymentData);
    }

    /**
     * @inheritdoc
     */
    public function savePaymentData($userId, \Enlight_Controller_Request_Request $request)
    {
        $this->setPaymentName(self::PAYMENTNAME);
        return parent::savePaymentData($userId, $request);
    }

    /**
     * @inheritdoc
     */
    public function getCurrentPaymentDataAsArray($userId)
    {
        $this->setPaymentName(self::PAYMENTNAME);
        return parent::getCurrentPaymentDataAsArray($userId);
    }

    /**
     * @inheritdoc
     */
    public function createPaymentInstance($orderId, $userId, $paymentId)
    {
        $this->setPaymentName(self::PAYMENTNAME);
        return parent::createPaymentInstance($orderId, $userId, $paymentId);
    }

}
