<?php

use AfterPay\Components\Constants;

class Shopware_Controllers_Frontend_ColoAfterpay extends Shopware_Controllers_Frontend_Payment
{

    /**
     * Checks general requirements for payment methods
     */
    public function preDispatch()
    {
        $request = $this->Request();
        if ($request->getParam('isXHR') == 1) {
            return;
        }
        $user = $this->getUser();
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $isForward = (int)$this->container->get('session')->offsetGet('ColoForwardToCancel');
        if ($isForward === 1) {
            $this->container->get('session')->offsetUnset('ColoForwardToCancel');
            return;
        }
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        $valid = $service->checkGeneralRequirements($user);
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        /**
         * Check if one of the payment methods is selected. Else return to default controller.
         */
        switch ($this->getPaymentShortName()) {
            case 'colo_afterpay_invoice':
                return $this->redirect(['action' => 'invoice']);
            case 'colo_afterpay_dd':
                return $this->redirect(['action' => 'debit']);
            case 'colo_afterpay_installment':
                return $this->redirect(['action' => 'installment']);
            case 'colo_afterpay_campaigns':
                return $this->redirect(['action' => 'campaigns']);
            default:
                return $this->redirect(['controller' => 'checkout']);
        }
    }

    /**
     * Invoice action method
     * Processes the invoice payment
     * API call "Authorize payment"
     * If "Authorize payment" successful: finish order
     */
    public function invoiceAction()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $paymentName = $this->getPaymentShortName();
        $this->unsetSessionVariables();
        if ($paymentName !== "colo_afterpay_invoice" || empty($user) || empty($basket) || empty($basket['content'])) {
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        if (!$this->isAgreementValid()) {
            return $this->forward('confirm', 'checkout', null, ['coloAfterpayMerchantError' => true]);
        }
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->validatePayment($paymentName, $basket['sAmount']);
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $response = $service->authorizePayment($user, $basket, $paymentName, 0);
        $this->finalizeOrder($response);
    }

    /**
     * Debit action method
     * Processes the direct debit payment
     * 1. API Call "Available Payment Methods".
     * 2. If Direct Debit is allowed: 2nd API Call "Validate Bank Account".
     * 3. If "Validate Bank Account" successful: 3rd API Call "Create Contract Request".
     * 4. If "Create Contract Request" successful: Show SEPA Mandat + Button "Allow debiting".
     * 5. If customer clicked "Allow debiting"-Button: API call "Authorize payment"
     * 6. If "Authorize payment" successful: finish order
     */
    public function debitAction()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $paymentName = $this->getPaymentShortName();
        $this->unsetSessionVariables();
        if ($paymentName !== "colo_afterpay_dd" || empty($user) || empty($basket) || empty($basket['content'])) {
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        if (!$this->isAgreementValid()) {
            return $this->forward('confirm', 'checkout', null, ['coloAfterpayMerchantError' => true]);
        }
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->validatePayment($paymentName, $basket['sAmount']);
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $isDebitAvailable = $service->isPaymentAvailable($user, $basket, 'directDebit');
        if (!$isDebitAvailable) {
            $message = $this->container->get('session')->offsetGet('ColoAfterpayErrorMessages');
            $this->container->get('session')->offsetUnset('ColoAfterpayErrorMessages');
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $iban = trim($user['additional']['payment']['data']['sSepaIban']);
        $bic = trim($user['additional']['payment']['data']['sSepaBic']);
        $response = $service->validateBankAccount($iban, $bic, $user);
        if (!$response['success']) {
            if (empty($response['message'])) {
                $message = $snippetManager->get('BankAccountNotValid');
            } else {
                $message = $response['message'];
            }
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $token = $this->container->get('session')->offsetGet('ColoPaymentTempId');

        /*
        // Skip the sepa contract
        $response = $service->createContract($token, $iban, $bic, $user, $basket, $paymentName);
        if (!$response['success']) {
            $message = $this->container->get('session')->offsetGet('ColoAfterpayErrorMessages');
            $this->container->get('session')->offsetUnset('ColoAfterpayErrorMessages');
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $this->forward('contract', null, null, array('contractDetails' => $response));
        */

        $response = $service->authorizePayment($user, $basket, $paymentName, 0);
        $this->finalizeOrder($response);
    }

    /**
     * Installment action method
     * Processes the installment payment
     * 1. API Call "Available Payment Methods".
     * 2. If Installment is allowed: 2nd API Call "Validate Bank Account".
     * 3. If "Validate Bank Account" successful: 3rd API Call "Create Contract Request".
     * //REMOVED: 4. If "Create Contract Request" successful: Show SEPA Mandat + Button "Allow debiting".
     * //REMOVED: 5. If customer clicked "Allow debiting"-Button: API call "Authorize payment"
     * 6. If "Authorize payment" successful: finish order
     */
    public function installmentAction()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $paymentName = $this->getPaymentShortName();
        $this->unsetSessionVariables();
        if ($paymentName !== "colo_afterpay_installment" || empty($user) || empty($basket) || empty($basket['content'])) {
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        if (!$this->isAgreementValid()) {
            return $this->forward('confirm', 'checkout', null, ['coloAfterpayMerchantError' => true]);
        }
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->validatePayment($paymentName, $basket['sAmount']);
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
//        $isInstallmentAvailable = $service->isPaymentAvailable($user, $basket, 'installment');
//        if (!$isInstallmentAvailable) {
//            $message = $this->container->get('session')->offsetGet('ColoAfterpayErrorMessages');
//            $this->container->get('session')->offsetUnset('ColoAfterpayErrorMessages');
//            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
//        }
        $installmentPlan = $this->container->get('session')->offsetGet('ColoAfterpayInstallmentPlan');
        $installmentPlanValid = $service->isInstallmentPlanValid($installmentPlan, $user, $basket);
        if (!$installmentPlanValid) {
            $message = $this->container->get('session')->offsetGet('ColoAfterpayErrorMessages');
            $this->container->get('session')->offsetUnset('ColoAfterpayErrorMessages');
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $iban = trim($user['additional']['payment']['data']['sSepaIban']);
        $bic = trim($user['additional']['payment']['data']['sSepaBic']);
        $response = $service->validateBankAccount($iban, $bic, $user);
        if (!$response['success']) {
            if (empty($response['message'])) {
                $message = $snippetManager->get('BankAccountNotValid');
            } else {
                $message = $response['message'];
            }
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
//        $token = $this->container->get('session')->offsetGet('ColoPaymentTempId');
//        $response = $service->createContract($token, $iban, $bic, $user, $basket, $paymentName);
//        if (!$response['success']) {
//            $message = $this->container->get('session')->offsetGet('ColoAfterpayErrorMessages');
//            $this->container->get('session')->offsetUnset('ColoAfterpayErrorMessages');
//            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
//        }

        $response = $service->authorizePayment($user, $basket, $paymentName, 0);
        $this->finalizeOrder($response);
    }

    /**
     * Campaigns action method
     * Processes the campaigns payment
     * API call "Authorize payment"
     * If "Authorize payment" successful: finish order
     */
    public function campaignsAction()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $paymentName = $this->getPaymentShortName();
        $this->unsetSessionVariables();
        if ($paymentName !== "colo_afterpay_campaigns" || empty($user) || empty($basket) || empty($basket['content'])) {
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        if (!$this->isAgreementValid()) {
            return $this->forward('confirm', 'checkout', null, ['coloAfterpayMerchantError' => true]);
        }
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->validatePayment($paymentName, $basket['sAmount']);
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $response = $service->authorizePayment($user, $basket, $paymentName, 1);
        $this->finalizeOrder($response);
    }

    /**
     * Contract action method
     * Loads contract for customer to submit before finalizing the order
     */
    public function contractAction()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $paymentName = $this->getPaymentShortName();
        if (!in_array($paymentName, array("colo_afterpay_dd", "colo_afterpay_installment")) || empty($user) || empty($basket) || empty($basket['content'])) {
            $this->unsetSessionVariables();
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        if (!$this->isAgreementValid()) {
            return $this->forward('confirm', 'checkout', null, ['coloAfterpayMerchantError' => true]);
        }
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->validatePayment($paymentName, $basket['sAmount']);
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        $contractDetails = $this->Request()->getParam('contractDetails');
        if (empty($contractDetails)) {
            $this->unsetSessionVariables();
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        $this->View()->assign(array('contractDetails' => $contractDetails));
    }

    /**
     * Authorize action method
     * Authorizes payment for direct debit or installment payment method
     */
    public function authorizeAction()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $paymentName = $this->getPaymentShortName();
        if (!in_array($paymentName, array("colo_afterpay_dd", "colo_afterpay_installment")) || empty($user) || empty($basket) || empty($basket['content'])) {
            $this->unsetSessionVariables();
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        if (!$this->isAgreementValid()) {
            return $this->forward('confirm', 'checkout', null, ['coloAfterpayMerchantError' => true]);
        }
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->validatePayment($paymentName, $basket['sAmount']);
        $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
        if (!$valid) {
            $message = $snippetManager->get('GeneralRequirementsError');
            $this->container->get('session')->offsetSet('ColoForwardToCancel', 1);
            return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
        }
        if (!$this->Request()->isPost()) {
            $this->unsetSessionVariables();
            $action = ($paymentName === "colo_afterpay_dd") ? "debit" : "installment";
            return $this->redirect(['controller' => 'ColoAfterpay', 'action' => $action]);
        }
        $token = $this->container->get('session')->offsetGet('ColoPaymentTempId');
        $transactionId = $this->container->get('session')->offsetGet('ColoPaymentTransactionId');
        $contractId = $this->container->get('session')->offsetGet('ColoContractId');
        $validAccount = (int)$this->container->get('session')->offsetGet('ColoValidAccount');
        $paymentAvailable = (int)$this->container->get('session')->offsetGet('ColoPaymentAvailable');
        if (!$validAccount || !$paymentAvailable || empty($token)) {
            $this->unsetSessionVariables();
            return $this->redirect(['controller' => 'checkout', 'action' => 'cart']);
        }
        $response = $service->authorizePaymentSecondStep($contractId, $token, $transactionId, $user, $basket, $paymentName);
        $this->finalizeOrder($response);
    }

    /**
     * Cancel action method
     * Forwards to the shippingPayment page of checkout and shows error message
     */
    public function cancelAction()
    {
        $this->unsetSessionVariables();
        $sErrorMessage = $this->Request()->getParam('sErrorMessage');
        $redirectToAddress = $this->Request()->getParam('address');
        if (empty($sErrorMessage)) {
            $sErrorMessage = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index')->get('GeneralError');
        }
        if (isset($redirectToAddress)) {
            $user = Shopware()->Modules()->Admin()->sGetUserData();
            if (is_array($redirectToAddress)) {
                $this->container->get('session')->offsetSet('ColoAfterpayCorrectedAddress', $redirectToAddress);
            }
            if ((int)$user['additional']['user']['accountmode'] === 1) {
                $params = ['controller' => 'checkout', 'action' => 'confirm'];
            } else {
                $params = ['controller' => 'address', 'action' => 'edit', 'id' => $user['billingaddress']['id'], 'sTarget' => 'checkout', 'sTargetAction' => 'confirm'];
            }
        } else {
            $params = ['controller' => 'checkout', 'action' => 'shippingPayment'];
        }
        $this->container->get('session')->offsetSet('ColoAfterpayErrorMessages', array($sErrorMessage));
        $this->redirect($params);
    }

    /**
     * Overrides Payment controller's getUser action
     * We need to get the user data from database instead of the session,
     * because customer can change his age at the last step in account page,
     * and the general requirement check will not work
     *
     * @return array
     */
    public function getUser()
    {
        $session = $this->container->get('session');
        $sAdmin = Shopware()->Modules()->Admin();
        $orderVariables = $session->offsetGet('sOrderVariables');
        if (!empty($orderVariables) && !empty($orderVariables['sUserData'])) {
            $user = $orderVariables['sUserData'];
        } else {
            $user = $sAdmin->sGetUserData();
        }
        $sessionPaymentID = $session->offsetGet('sPaymentID');
        if (!empty($user['additional']['payment'])) {
            $payment = $user['additional']['payment'];
        } elseif (!empty($sessionPaymentID)) {
            $payment = $sAdmin->sGetPaymentMeanById($sessionPaymentID, $user);
        }

        if ($payment && !in_array($payment['name'], array('colo_afterpay_invoice', 'colo_afterpay_dd'))) {
            $payment = null;
        }

        $paymentClass = $sAdmin->sInitiatePaymentClass($payment);
        if ($payment && $paymentClass instanceof \ShopwarePlugin\PaymentMethods\Components\BasePaymentMethod) {
            $data = $paymentClass->getCurrentPaymentDataAsArray($user['additional']['user']['id']);
            $payment['validation'] = $paymentClass->validate($data);
            if (!empty($data)) {
                $payment['data'] = $data;
            }
        }
        if (!empty($payment)) {
            $user['additional']['payment'] = $payment;
        }
        return $user;
    }

    /**
     * Internal method which finalizes order either by finishing it or forwarding to cancelAction
     * in case there was an error in checkout process
     *
     * @param array $response
     */
    private function finalizeOrder($response)
    {
        if ($response['success'] && !empty($response['transactionId'])) {
            $this->unsetSessionVariables(1);
            $token = !empty($response['token']) ? $response['token'] : $this->container->get('session')->get('sessionId');
            $paymentStatus = Constants::PAYMENTSTATUSPAID;
            if (!empty($response['paymentStatus']) || $response['paymentStatus'] === "0" || $response['paymentStatus'] === 0) {
                $paymentStatus = $response['paymentStatus'];
            }
            $ordernumber = $this->saveOrder(
                $response['transactionId'], $token, $paymentStatus
            );
            if (empty($ordernumber)) {
                $message = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index')->get('GeneralError');
                return $this->forward('cancel', null, null, array('sErrorMessage' => $message));
            }
            $this->saveTransactionMode($ordernumber, $response['transactionMode']);
            // only for beblonde
            if (!empty($response['bankAccount'])) {
                $this->saveBankAccount($ordernumber, $response['bankAccount']);
            }
            $this->container->get('session')->offsetSet('ColoPaymentCompleted', 1);
            $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
        } else {
            $this->forward('cancel', null, null, array('sErrorMessage' => $response['message'], 'address' => $response['address']));
        }
    }

    /**
     * Saves transaction mode (public_sandbox, sandbox, prod), for future capturing and refunds
     *
     * @param string $ordernumber
     * @param $mode
     * @throws Zend_Db_Adapter_Exception
     */
    private function saveTransactionMode($ordernumber, $mode)
    {
        $sql = "UPDATE `s_order_attributes` SET `colo_transaction_mode`=? WHERE `orderID`=(SELECT `id` FROM `s_order` WHERE `ordernumber`=?);";
        Shopware()->Db()->query($sql, array($mode, $ordernumber));
    }

    /**
     * Saves individual bank account of customer in order attributes
     *
     * @param string $ordernumber
     * @param string $bankAccount
     * @throws Zend_Db_Adapter_Exception
     */
    private function saveBankAccount($ordernumber, $bankAccount)
    {
        $result = Shopware()->Db()->fetchAll("SHOW COLUMNS FROM `s_order_attributes` LIKE 'colo_bank_account';");
        if (empty($result)) {
            return;
        }
        $sql = "UPDATE `s_order_attributes` SET `colo_bank_account`=? WHERE `orderID` IN (SELECT `id` FROM `s_order` WHERE `ordernumber`=?);";
        Shopware()->Db()->query($sql, array($bankAccount, $ordernumber));
    }

    /**
     * Checks if the agreement checkbox is checked in confirm page
     */
    private function isAgreementValid()
    {
        $session = $this->container->get('session');
        $valid = $session->offsetGet('coloAfterpayMerchantCheck');
        if (empty($valid)) {
            return false;
        }
        return true;
    }

    /**
     * Removes all session variables that were created during checkout process
     */
    private function unsetSessionVariables($finalize = 0)
    {
        $session = $this->container->get('session');
        $session->offsetUnset('ColoValidAccount');
        $session->offsetUnset('ColoPaymentAvailable');
        $session->offsetUnset('ColoPaymentTempId');
        $session->offsetUnset('ColoPaymentTransactionId');
        $session->offsetUnset('ColoContractId');
        $session->offsetUnset('ColoPaymentCompleted');
        $session->offsetUnset('ColoAfterpayCorrectedAddress');
        if ($finalize) {
            $session->offsetUnset('ColoAfterpayInstallmentPlan');
            $session->offsetUnset('coloAfterpayMerchantCheck');
        }
    }

}
