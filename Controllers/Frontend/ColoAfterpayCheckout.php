<?php

class Shopware_Controllers_Frontend_ColoAfterpayCheckout extends Shopware_Controllers_Frontend_Checkout
{

    /**
     * Called from shippingpayment page via ajax
     * Returns available installments
     */
    public function getInstallmentsAction()
    {
        $view = $this->View();
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $user = $this->getUserData();
        $valid = $service->checkGeneralRequirements($user);
        if ($valid) {
            $basket = $this->getBasket();
            $valid = $service->validatePayment('colo_afterpay_installment', $basket['sAmount']);
            if (!$valid) {
                $view->loadTemplate('frontend/colo_afterpay/installments_error.tpl');
            } else {
                $installments = $service->getAvailableInstallments($user['additional']['country']['countryiso'], $basket['sAmount'], $basket['sCurrencyName']);
                if (!empty($installments)) {
                    $view->loadTemplate('frontend/colo_afterpay/installments_success.tpl');

                    $shop = $this->container->get('shop');
                    $coloAfterpayLanguageCode = strtolower($shop->getLocale()->getLocale());

                    $countryID = $user['billingaddress']['countryId'];
                    $coloAfterpayMerchantID = $service->getMerchantId($shop, $countryID);
                    $view->assign(array(
                        'coloAfterpayMerchantID' => $coloAfterpayMerchantID,
                        'coloAfterpayLanguageCode' => $coloAfterpayLanguageCode,
                        'coloAfterpayInstallments' => $installments,
                        'sBasket' => $basket
                    ));
                    $installmentPlan = $this->container->get('session')->offsetGet('ColoAfterpayInstallmentPlan');
                    if (!empty($installmentPlan)) {
                        $view->assign(array('coloAfterpaySelectedInstallment' => $installmentPlan));
                    }
                } else {
                    $view->loadTemplate('frontend/colo_afterpay/installments_error.tpl');
                }
            }
        } else {
            $view->loadTemplate('frontend/colo_afterpay/installments_error.tpl');
        }
    }

}
