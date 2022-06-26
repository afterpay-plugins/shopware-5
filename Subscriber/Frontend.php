<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use AfterPay\Components\Constants;

class Frontend implements SubscriberInterface
{

    /**
     * @var string
     */
    private $viewDir;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckout',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Account' => 'onPostDispatchAccount',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Address' => 'onPostDispatchAddress',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onPostDispatchFrontend',
            'Enlight_Controller_Action_PreDispatch_Frontend_Checkout' => 'onPreDispatchCheckout',
            'Enlight_Controller_Action_PostDispatchSecure' => 'addPaths'
        );
    }

    /**
     * @param ContainerInterface $container
     */
    public function __construct($viewDir, ContainerInterface $container)
    {
        $this->viewDir = $viewDir;
        $this->container = $container;
    }

    /**
     * Disables afterpay payment methods on /account/payment page, if requirements are not met
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchAccount(\Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();
        $actionName = strtolower($request->getActionName());

        if ($actionName !== "payment") {
            return;
        }

        $session = $this->container->get('session');
        $shop = $this->container->get('shop');
        $pluginConfigs = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        $view->assign([
            'ColoAfterpayConfigs' => $pluginConfigs,
            'ColoAfterpayUserSessionId' => $session->offsetGet('sessionId'),
            'ColoAfterpayPaymentMethods' => Constants::PAYMENT_METHODS
        ]);

        $sErrorMessages = $session->offsetGet('ColoAfterpayErrorMessages');
        $session->offsetUnset('ColoAfterpayErrorMessages');
        if (!empty($sErrorMessages)) {
            $view->assign("sErrorMessages", $sErrorMessages);
        }
        $user = $view->getAssign('sUserData');
        $basket = [];
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $valid = $service->checkGeneralRequirements($user);
        if (!$valid) {
            $view->assign("ColoGeneralRequirementsNotMet", 1);
        }
        $view->assign("ColoAfterpayPaymentMethods", Constants::PAYMENT_METHODS);

        $payments = $view->getAssign('sPaymentMeans');
        if (empty($payments)) {
            return;
        }
        $sFormData = $view->getAssign('sFormData');
        $formData = $this->handlePaymentMethods($payments, $user, $basket, $sFormData);
        $view->assign('sFormData', $formData);
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return bool|void
     */
    public function onPostDispatchAddress(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_ActionEventArgs $args */
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();
        $actionName = strtolower($request->getActionName());

        if ($actionName !== "edit") {
            return;
        }
        if ($request->isPost()) {
            $this->container->get('session')->offsetUnset('ColoAfterpayCorrectedAddress');
        } else {
            $sErrorMessages = $this->container->get('session')->offsetGet('ColoAfterpayErrorMessages');
            $this->container->get('session')->offsetUnset('ColoAfterpayErrorMessages');
            if (!empty($sErrorMessages)) {
                $view->assign("error_messages", $sErrorMessages);
            }
            $correctedAddress = $this->container->get('session')->offsetGet('ColoAfterpayCorrectedAddress');
            if (!is_array($correctedAddress)) {
                return false;
            }
            $address = $view->getAssign('formData');
            $address['street'] = $correctedAddress['street'];
            $address['zipcode'] = $correctedAddress['zipcode'];
            $address['city'] = $correctedAddress['city'];
            $view->assign(array("formData" => $address));
        }
    }

    public function onPostDispatchFrontend(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_ActionEventArgs $args */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $view = $controller->View();

        $session = $this->container->get('session');
        $shop = $this->container->get('shop');
        $pluginConfigs = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        $view->assign([
            'ColoAfterpayConfigs' => $pluginConfigs,
            'ColoAfterpayUserSessionId' => $session->offsetGet('sessionId'),
            'ColoUserLoggedIn' => $this->container->get('modules')->Admin()->sCheckUser(),
            'ColoIsLoginRegisterPage' => ($request->getControllerName() === 'register' || ($request->getControllerName() === 'account' && $request->getActionName() === 'login')) ? true : false
        ]);
    }

    /**
     * Assign error messages in shippingPayment page if there are any
     * Assigns iban for direct debit and installment payment methods
     *
     * @param \Enlight_Event_EventArgs $args
     * @throws \Enlight_Exception
     * @throws \ReflectionException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onPostDispatchCheckout(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_ActionEventArgs $args */
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();
        $actionName = strtolower($request->getActionName());

        if ($actionName !== "cart" &&
            $actionName !== "confirm" &&
            $actionName !== "shippingpayment" &&
            $actionName !== "saveshippingpayment" &&
            $actionName !== "finish") {
            return;
        }

        $session = $this->container->get('session');
        $shop = $this->container->get('shop');
        $pluginConfigs = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        $view->assign(array('ColoAfterpayConfigs' => $pluginConfigs, 'ColoAfterpayUserSessionId' => $session->offsetGet('sessionId')));
        if ($actionName === "cart") {
            $userData = Shopware()->Modules()->Admin()->sGetUserData();
            if (!empty($userData['additional']['user'])) {
                $session->offsetSet('sPaymentID', $userData['additional']['payment']['id']);
            }
        } else if ($actionName === "confirm") {
            $paymentMethods = $controller->getPayments();
            if ($request->isPost()) {
                $paymentID = $request->getPost('payment');
                if (!empty($paymentID)) {
                    $isAfterpay = $this->isAfterpayPayment($paymentID, $paymentMethods);
                    if (!$isAfterpay) {
                        return;
                    }
                }
            }
            $isAfterpaySelected = $this->isAfterpayPaymentSelected($controller);
            if (!$isAfterpaySelected) {
                return;
            }
            $userData = $view->getAssign('sUserData');
            if (!$request->isPost()) {
                if ($pluginConfigs['colo_afterpay_birthday_check']) {
                    if (empty($userData['additional']['user']['birthday'])) {
                        return $controller->redirect(['controller' => 'checkout', 'action' => 'shippingPayment']);
                    }
                }
            }

            $correctedAddress = $session->offsetGet('ColoAfterpayCorrectedAddress');
            if (is_array($correctedAddress)) {
                $sErrorMessages = $session->offsetGet('ColoAfterpayErrorMessages');
                $session->offsetUnset('ColoAfterpayCorrectedAddress');
                $session->offsetUnset('ColoAfterpayErrorMessages');
                if (!empty($sErrorMessages)) {
                    $errorMessage = "";
                    foreach ($sErrorMessages as $index => $sErrorMessage) {
                        $errorMessage .= $sErrorMessage;
                        if (count($sErrorMessages) !== $index) {
                            $errorMessage .= "<br/>";
                        }
                    }
                    $view->assign("sBasketInfo", $errorMessage);
                }
                $view->assign("coloAfterpayCorrectedAddress", 1);

                $userData['billingaddress']['street'] = $correctedAddress['street'];
                $userData['billingaddress']['zipcode'] = $correctedAddress['zipcode'];
                $userData['billingaddress']['city'] = $correctedAddress['city'];

                if ($session->offsetExists('sOrderVariables')) {
                    $orderVariables = $session->offsetGet('sOrderVariables');
                    if (!empty($orderVariables['sUserData'])) {
                        $orderVariables['sUserData']['billingaddress']['street'] = $correctedAddress['street'];
                        $orderVariables['sUserData']['billingaddress']['zipcode'] = $correctedAddress['zipcode'];
                        $orderVariables['sUserData']['billingaddress']['city'] = $correctedAddress['city'];
                        $session->offsetSet('sOrderVariables', $orderVariables);
                    }
                }

                $sql = "UPDATE `s_user_addresses` SET `street`=?, `zipcode`=?, `city`=? WHERE `id`=?;";
                Shopware()->Db()->query($sql, array($correctedAddress['street'], $correctedAddress['zipcode'], $correctedAddress['city'], $userData['billingaddress']['id']));

                $view->assign('sUserData', $userData);
            }
            $service = $this->container->get('colo_afterpay.services.afterpay_service');
            $countryID = $userData['billingaddress']['countryId'];
            $coloAfterpayMerchantID = $service->getMerchantId($shop, $countryID);
//            $coloAfterpayLanguageCode = strtolower($shop->getLocale()->getLocale());
            $countryShippingIso = $userData['additional']['countryShipping']['countryiso'];

            switch ($countryShippingIso) {
                case "BE":
                    $coloAfterpayLanguageCode = 'nl_be';
                    break;
                case "NL":
                    $coloAfterpayLanguageCode = 'nl_nl';

                    break;
                case "AT":
                    $coloAfterpayLanguageCode = 'de_at';
                    break;
                case "DE":
                    $coloAfterpayLanguageCode = 'de_de';
                    break;
                default:
                    $coloAfterpayLanguageCode = 'en_gb';

            }


            $coloAfterpayMerchantPaymentMethod = null;
            $paymentName = $userData['additional']['payment']['name'];
            switch ($paymentName) {
                case 'colo_afterpay_invoice':
                case 'colo_afterpay_campaigns':
                    $coloAfterpayMerchantPaymentMethod = "invoice";
                    break;
                case 'colo_afterpay_dd':
                    $coloAfterpayMerchantPaymentMethod = "directdebit";
                    break;
                case 'colo_afterpay_installment':
                    $coloAfterpayMerchantPaymentMethod = "installment";
                    break;
                default:
                    break;
            }
            if (empty($coloAfterpayMerchantPaymentMethod)) {
                return;
            }
            $session->offsetSet('sPaymentID', $userData['additional']['payment']['id']);
            $basket = $controller->getBasket();
            $installments = $service->getAvailableInstallments($userData['additional']['country']['countryiso'], $basket['sAmount'], $basket['sCurrencyName']);
            $view->assign('coloAfterpayInstallments', $installments);
            $installmentPlan = $this->container->get('session')->offsetGet('ColoAfterpayInstallmentPlan');
            if (!empty($installmentPlan)) {
                $view->assign('coloAfterpaySelectedInstallment',$installmentPlan);
            }
            $view->assign(array(
                'coloAfterpayMerchantID' => $coloAfterpayMerchantID,
                'coloAfterpayMerchantPaymentMethod' => $coloAfterpayMerchantPaymentMethod,
                'coloAfterpayLanguageCode' => $coloAfterpayLanguageCode
            ));
            $coloAfterpayMerchantError = $request->getParam('coloAfterpayMerchantError');
            if (!empty($coloAfterpayMerchantError)) {
                $view->assign('coloAfterpayMerchantError', true);
            }
            $agreementChecked = $request->getParam('coloAfterpayMerchantCheck');
            if (!empty($agreementChecked)) {
                $view->assign('coloAfterpayMerchantChecked', true);
            }

            $user = $controller->getUserData();
            $valid = $service->checkGeneralRequirements($user);
            if (!$valid) {
                $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
                $message = $snippetManager->get('GeneralRequirementsError');
                $session->offsetSet('ColoAfterpayErrorMessages', array($message));
                return $controller->redirect(['controller' => 'checkout', 'action' => 'shippingPayment']);
            }
        } else if ($actionName === "finish") {

        } else {
            $sErrorMessages = $session->offsetGet('ColoAfterpayErrorMessages');
            $session->offsetUnset('ColoAfterpayErrorMessages');
            if (!empty($sErrorMessages)) {
                $view->assign("sErrorMessages", $sErrorMessages);
            }
            $user = $controller->getUserData();
            $basket = $controller->getBasket();
            $service = $this->container->get('colo_afterpay.services.afterpay_service');
            $valid = $service->checkGeneralRequirements($user);
            if (!$valid) {
                $view->assign("ColoGeneralRequirementsNotMet", 1);
            }
            $view->assign("ColoAfterpayPaymentMethods", Constants::PAYMENT_METHODS);

            $payments = array();
            if (method_exists($controller, "getPayments")) {
                $reflection = new \ReflectionMethod($controller, "getPayments");
                if ($reflection->isPublic()) {
                    $payments = $controller->getPayments();
                }
            }
            if (empty($payments)) {
                return;
            }
            $sFormData = $view->getAssign('sFormData');
            $formData = $this->handlePaymentMethods($payments, $user, $basket, $sFormData);
            $installments = $service->getAvailableInstallments($user['additional']['country']['countryiso'], $basket['sAmount'], $basket['sCurrencyName']);
            $view->assign('coloAfterpayInstallments', $installments);
            $installmentPlan = $this->container->get('session')->offsetGet('ColoAfterpayInstallmentPlan');
            if (!empty($installmentPlan)) {
                $view->assign('coloAfterpaySelectedInstallment',$installmentPlan);
            }

            $view->assign('sFormData', $formData);
        }
    }

    /**
     * Change payment method to another payment method
     * in case general requirements are not met
     * Redirects to confirm page if afterpay payment method is selected
     * and the payment process is not finished
     *
     * @param \Enlight_Event_EventArgs $args
     * @throws \Enlight_Exception
     */
    public function onPreDispatchCheckout(\Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $request = $controller->Request();
        $actionName = strtolower($request->getActionName());

        if ($actionName == "finish") {
            $userData = Shopware()->Modules()->Admin()->sGetUserData();
            if (!empty($userData['additional']['user'])) {
                $this->container->get('session')->offsetSet('sPaymentID', $userData['additional']['payment']['id']);
            }
            $isAfterpaySelected = $this->isAfterpayPaymentSelected($controller);
            if (!$isAfterpaySelected) {
                return;
            }
            $service = $this->container->get('colo_afterpay.services.afterpay_service');
            $completed = $service->isPaymentProcessCompleted();
            if (!$completed) {
                return $controller->redirect(['controller' => 'checkout', 'action' => 'confirm']);
            }
            $this->container->get('session')->offsetUnset('ColoPaymentCompleted');
        } else if ($actionName === "payment") {
            $userData = Shopware()->Modules()->Admin()->sGetUserData();
            if (!empty($userData['additional']['user'])) {
                $this->container->get('session')->offsetSet('sPaymentID', $userData['additional']['payment']['id']);
            }
            $isAfterpaySelected = $this->isAfterpayPaymentSelected($controller);
            if (!$isAfterpaySelected) {
                return;
            }
            $valid = $this->isAgreementValid();
            if (!$valid) {
                $this->container->get('session')->offsetSet('coloAfterpayMerchantCheck', 0);
                return $controller->forward('confirm', null, null, ['coloAfterpayMerchantError' => true]);
            }
            $this->container->get('session')->offsetSet('coloAfterpayMerchantCheck', 1);
        } else if ($actionName === "shippingpayment" || $actionName === "saveshippingpayment") {
            $controller->preDispatch();
            $paymentMethods = $controller->getPayments();
            if ($request->isPost()) {
                $paymentID = $request->getPost('payment');
                if (!empty($paymentID)) {
                    $isAfterpay = $this->isAfterpayPayment($paymentID, $paymentMethods);
                    if (!$isAfterpay) {
                        return;
                    }
                }
            }
            $isAfterpaySelected = $this->isAfterpayPaymentSelected($controller);
            if (!$isAfterpaySelected) {
                return;
            }
            $user = $controller->getUserData();
            $service = $this->container->get('colo_afterpay.services.afterpay_service');
            $valid = $service->checkGeneralRequirements($user);
            if (!$valid) {
                $paymentID = $this->getNewPaymentId($paymentMethods);
                if ($request->isPost() && $paymentID) {
                    $request->setPost('payment', $paymentID);
                    $request->setPost('sPayment', $paymentID);
                } else if ($actionName === "shippingpayment" && $paymentID) {
                    Shopware()->Modules()->Admin()->sUpdatePayment($paymentID);
                }
            } else if ($actionName === "shippingpayment") {
                $selectedPayment = $controller->getSelectedPayment();
                $basket = $controller->getBasket();
                $paymentName = $selectedPayment['name'];
                $valid = $service->validatePayment($paymentName, $basket['sAmount']);
                if (!$valid) {
                    $paymentID = $this->getNewPaymentId($paymentMethods);
                    if ($paymentID) {
                        Shopware()->Modules()->Admin()->sUpdatePayment($paymentID);
                    }
                }
            }
        }
    }

    /**
     * Add View path to Smarty
     *
     * @param \Enlight_Event_EventArgs $args
     * @return mixed
     */
    public function addPaths(\Enlight_Event_EventArgs $args)
    {
        $view = $args->getSubject()->View();
        $view->addTemplateDir(
            $this->viewDir, 'payment', \Enlight_Template_Manager::POSITION_APPEND
        );
    }

    /**
     * Checks if afterpay payment method is meeting requirements
     * and assigns needed information
     *
     * @param $payments
     * @param $user
     * @param $basket
     * @param array $formData
     * @return array
     * @throws \Enlight_Exception
     */
    private function handlePaymentMethods($payments, $user, $basket, $formData = array())
    {
        $formData['coloAfterpayPaymentDetails'] = array();
        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $admin = Shopware()->Modules()->Admin();
        foreach ($payments as $payment) {
            if (in_array($payment['name'], Constants::PAYMENT_METHODS)) {
                $valid = true;
                if (!empty($basket)) {
                    $valid = $service->validatePayment($payment['name'], $basket['sAmount']);
                }
                $birthday = $user['additional']['user']['birthday'];
                $birthdaySplitted = array(
                    'day' => null,
                    'month' => null,
                    'year' => null
                );
                if (!empty($birthday)) {
                    $birthdayParts = explode("-", $birthday);
                    $birthdaySplitted = array(
                        'day' => $birthdayParts[2],
                        'month' => $birthdayParts[1],
                        'year' => $birthdayParts[0]
                    );
                }
                $formData['coloAfterpayPaymentDetails'][$payment['name']] = array(
                    'disabled' => !$valid,
                    'sBirthday' => $birthdaySplitted
                );
            }
            if (in_array($payment['name'], array("colo_afterpay_dd", "colo_afterpay_installment"))) {
                $getPaymentDetails = $admin->sGetPaymentMeanById($payment['id']);
                $paymentClass = $admin->sInitiatePaymentClass($getPaymentDetails);
                if ($paymentClass instanceof \ShopwarePlugin\PaymentMethods\Components\BasePaymentMethod) {
                    $data = $paymentClass->getCurrentPaymentDataAsArray(Shopware()->Session()->sUserId);
                    if (!empty($data)) {
                        $formData['coloAfterpayPaymentDetails'][$payment['name']]['sSepaIban'] = $data['sSepaIban'];
                    }
                }
            }
        }
        return $formData;
    }

    /**
     * Checks if the agreement checkbox is checked in confirm page
     *
     * @return bool
     */
    private function isAgreementValid()
    {
        $shop = $this->container->get('shop');
        $pluginConfigs = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        if (!$pluginConfigs['colo_afterpay_tos_checkbox']) {
            return true;
        }
        $request = $this->container->get('front')->Request();
        if (!$request->getParam('coloAfterpayMerchantCheck')) {
            return false;
        }
        return true;
    }

    /**
     * Checks if the selected payment is afterpay payment
     * @param object $controller
     * @return boolean
     */
    private function isAfterpayPaymentSelected($controller)
    {
        $payment = $controller->getSelectedPayment();
        if (in_array($payment['name'], Constants::PAYMENT_METHODS)) {
            return true;
        }
        return false;
    }

    /**
     * Checks if the payment method is afterpay payment
     * @param integer $paymentID
     * @return boolean
     */
    private function isAfterpayPayment($paymentID, $paymentMethods)
    {
        $isAfterpay = false;
        foreach ($paymentMethods as $paymentMethod) {
            if ((int)$paymentID === (int)$paymentMethod['id']) {
                if (in_array($paymentMethod['name'], Constants::PAYMENT_METHODS)) {
                    $isAfterpay = true;
                }
                break;
            }
        }
        return $isAfterpay;
    }

    /**
     * Changes to the default payment method
     *
     * @param $paymentMethods
     * @return null
     */
    private function getNewPaymentId($paymentMethods)
    {
        $payment = null;
        $defaultPaymentId = Shopware()->Config()->offsetGet('defaultpayment');
        $paymentIds = $this->getAfterpayPaymentIds($paymentMethods);
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod['id'] == $defaultPaymentId) {
                $payment = $paymentMethod;
                break;
            }
        }
        if ($payment === null || in_array($defaultPaymentId, $paymentIds)) {
            foreach ($paymentMethods as $paymentMethod) {
                if (!in_array($paymentMethod['id'], $paymentIds)) {
                    $payment = $paymentMethod;
                    break;
                }
            }
        }
        if ($payment !== null) {
            return $payment['id'];
        }
        return null;
    }

    /**
     * @param $paymentMethods
     * @return array
     */
    private function getAfterpayPaymentIds($paymentMethods)
    {
        $paymentIds = array();
        foreach ($paymentMethods as $paymentMethod) {
            if (in_array($paymentMethod['name'], Constants::PAYMENT_METHODS)) {
                $paymentIds[] = $paymentMethod['id'];
            }
        }
        return $paymentIds;
    }

}
