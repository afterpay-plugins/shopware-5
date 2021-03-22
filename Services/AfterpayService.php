<?php

namespace AfterPay\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Components\Model\ModelManager;
use AfterPay\Components\Constants;
use AfterPay\Models\Transactions;
use Shopware\Models\Order\Status as OrderStatus;
use Shopware\Models\Shop\Shop as ShopModel;

class AfterpayService
{

    /**
     * @var ContainerInterface $container
     */
    protected $container;

    /**
     * @var \Shopware_Components_Config $config
     */
    protected $config = null;

    /**
     * @var \Shopware $session
     */
    protected $session = null;

    /**
     * Doctrine entity manager, which used for CRUD operations.
     * @var ModelManager
     */
    protected $entityManager;

    /**
     * @var $db
     */
    protected $db;

    /**
     * @var array $availableCountries
     */
    protected $availableCountries;

    /**
     * @var array $paymentDetails
     */
    protected $paymentDetails;

    /**
     * @var LoggerService $logger
     */
    protected $logger;

    /**
     * @var string $apiUrl
     */
    protected $apiUrl = "";

    /**
     * @var string $apiKey
     */
    protected $apiKey = "";

    /**
     * @var array $apiHeaders
     */
    protected $apiHeaders = array();

    /**
     * @param ContainerInterface $container
     * @param ModelManager $entityManager
     * @param LoggerService $loggerService
     */
    public function __construct(
        ContainerInterface $container, ModelManager $entityManager, LoggerService $loggerService
    )
    {
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->logger = $loggerService;
        if ($this->container->initialized('session') && $this->container->has('session')) {
            $this->session = $this->container->get('session');
        }
        $this->db = Shopware()->Db();
        $this->availableCountries = $this->getAvailableCountries();
        $this->paymentDetails = $this->getPaymentDetails();
    }

    /**
     * @param null $shopId
     * @return array
     */
    public function getApiVersion($shopId = null)
    {
        $modes = [];
        $this->availableCountries = $this->getAvailableCountries($shopId);
        if (empty($this->availableCountries)) {
            $this->logger->log('No countries are activated for Afterpay. Please activate countries to test the APIs!');
            return ['all' => ["public_sandbox" => null, "sandbox" => null, "prod" => null]];
        }
        foreach ($this->availableCountries as $details) {
            $modes[$details['countryname']] = ["public_sandbox" => null, "sandbox" => null, "prod" => null];
            foreach ($modes[$details['countryname']] as $mode => $value) {
                unset($this->config, $this->apiHeaders, $this->apiUrl, $this->apiKey); // Fix for initialization of another API context after initial initialization 
                $this->initApi($details['countryiso'], $shopId, $mode);
                $url = $this->apiUrl . "version";
                $this->logger->log('Test API request for country ' . $details['countryname'] . ' started.');
                if (empty($this->apiKey)) {
                    $this->logger->log('No API-Key provided for country ' . $details['countryname'] . ' (Mode: ' . $mode . ').', 'error');
                } else {
                    $curlResponse = $this->curl($url);
                    if (!empty($curlResponse) && !empty($curlResponse['version'])) {
                        $this->logger->log('Test API request for country ' . $details['countryname'] . ' completed (Mode: ' . $mode . ').');
                        $modes[$details['countryname']][$mode] = $curlResponse['version'];
                    } else if (!empty($curlResponse)) {
                        $this->logger->log('Test API request for country ' . $details['countryname'] . ' completed with error (Mode: ' . $mode . ').', 'error');
                        $this->logger->log('Error message: ' . $curlResponse['message'], 'error');
                    } else {
                        $this->logger->log('Test API request for country ' . $details['countryname'] . ' completed with error (Mode: ' . $mode . ').', 'error');
                        $this->logger->log('Error message: ' . 'Unknown error, please contact plugin support.', 'error');
                    }
                }
            }
        }
        return $modes;
    }

    /**
     * Validates user and basket data
     * - Customer is 18+ years old.
     * - Customer is not a company.
     * - Country is active.
     *
     * @param array $user
     * @return boolean
     */
    public function checkGeneralRequirements($user)
    {
        if (empty($user) || empty($user['additional']['user'])) {
            return false;
        }
        if (empty($this->availableCountries) || empty($this->paymentDetails)) {
            return false;
        }
        $countryIsos = array_column($this->availableCountries, 'countryiso');
        if (!in_array($user['additional']['country']['countryiso'], $countryIsos)) {
            return false;
        }
        if (!empty($user['billingaddress']['company'])) {
            return false;
        }
        $this->initApi($user['additional']['country']['countryiso']);
        if (!$this->config['colo_afterpay_addresses'] && $user['shippingaddress']['id'] !== $user['billingaddress']['id']) {
            return false;
        }

        return true;
    }

    /**
     * Validates afterpay payment details. In case requirements are not met, payment method is disabled.
     *
     * @param $paymentName
     * @param $amount
     * @return bool
     */
    public function validatePayment($paymentName, $amount)
    {
        if (empty($paymentName)) {
            return false;
        }
        $payment = null;
        foreach ($this->paymentDetails as $paymentDetails) {
            if ($paymentName === $paymentDetails['name']) {
                $payment = $paymentDetails;
                break;
            }
        }
        if (empty($payment)) {
            return false;
        }
        if ($amount > (float)$payment['colo_afterpay_max_basket_value'] || $amount < (float)$payment['colo_afterpay_min_basket_value']) {
            return false;
        }
        if ($paymentName === "colo_afterpay_campaigns" && empty($this->config['colo_afterpay_campaign'])) {
            return false;
        }

        return true;
    }

    /**
     * Called from captureAction method of ColoAfterpay backend controller
     * Calls afterpay /api/v3/orders/{orderNumber}/captures API method
     * Checks if order can be captured, and captures it
     *
     * @param string $ordernumber
     * @return array
     */
    public function capturePayment($ordernumber)
    {
        $response = array("success" => false);
        try {
            $order = $this->entityManager->getRepository('Shopware\Models\Order\Order')->findOneBy(array('number' => $ordernumber));
            if (empty($order)) {
                $this->logger->log("Order does not exist with ordernumber: " . $ordernumber, "error");

                return $response;
            }
            $billingInfo = $order->getBilling();
            $shop = $order->getShop();
            if (empty($shop) || empty($shop->getId()) || empty($billingInfo) || empty($billingInfo->getCountry()) || empty($billingInfo->getCountry()->getIso())) {
                $this->logger->log("Order details are not complete for ordernumber: " . $ordernumber, "error");

                return $response;
            }
            $transactionMode = $order->getAttribute()->getColoTransactionMode();
            $countryiso = $billingInfo->getCountry()->getIso();
            $shopId = $shop->getId();
            $this->initApi($countryiso, $shopId, $transactionMode);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            if (!$this->isOrderCapturable($order)) {
                $this->logger->log("Order with ordernumber \"" . $ordernumber . "\" is not capturable.", "error");

                return $response;
            }
            $data = $this->prepareCaptureData($order);
            $url = $this->apiUrl . "orders/" . $order->getTransactionId() . "/captures";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");

            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response, true);
            if ($response['success']) {
                if (!empty($response['captureNumber'])) {
                    $sql = "UPDATE `s_order_attributes` SET `colo_captured`=1, `colo_capture_number`=? WHERE `orderID`=?;";
                    $this->db->query($sql, array($response['captureNumber'], $order->getId()));

                    $this->saveTransaction($order->getId(), $response['captureNumber'], $data['orderDetails']['totalGrossAmount']);
                }
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");

            return $response;
        }

        return $response;
    }

    /**
     * Called from refundAction method of ColoAfterpay backend controller
     * Calls afterpay /api/v3/orders/{orderNumber}/refunds API method
     * Refunds the specified amount
     *
     * @param $ordernumber
     * @param $amount
     * @param $creditNoteNumber
     * @return array
     */
    public function refundPayment($ordernumber, $amount, $creditNoteNumber)
    {
        $response = array("success" => false);
        try {
            $order = $this->entityManager->getRepository('Shopware\Models\Order\Order')->findOneBy(array('number' => $ordernumber));
            if (empty($order)) {
                $this->logger->log("Order does not exist with ordernumber: " . $ordernumber, "error");

                return $response;
            }
            $billingInfo = $order->getBilling();
            $shop = $order->getShop();
            if (empty($shop) || empty($shop->getId()) || empty($billingInfo) || empty($billingInfo->getCountry()) || empty($billingInfo->getCountry()->getIso())) {
                $this->logger->log("Order details are not complete for ordernumber: " . $ordernumber, "error");

                return $response;
            }
            $transactionMode = $order->getAttribute()->getColoTransactionMode();
            $countryiso = $billingInfo->getCountry()->getIso();
            $shopId = $shop->getId();
            $this->initApi($countryiso, $shopId, $transactionMode);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            if (!$this->isOrderCaptured($order)) {
                $this->logger->log("Order with ordernumber \"" . $ordernumber . "\" is not captured.", "error");

                return $response;
            }
            $data = $this->prepareRefundData($order, $amount, $creditNoteNumber);
            $url = $this->apiUrl . "orders/" . $order->getTransactionId() . "/refunds";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");

            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response, true);
            if ($response['success']) {
                if (!empty($response['refundNumbers'])) {
                    foreach ($response['refundNumbers'] as $refundNumber) {
                        $this->saveTransaction($order->getId(), $refundNumber, (0 - $amount));
                    }
                }
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");

            return $response;
        }

        return $response;
    }

    /**
     * Called from invoiceAction method of ColoAfterpay controller
     * Calls afterpay /api/v3/checkout/authorize API method
     * and returns transaction id and temporary id for the order
     *
     * @param array $user
     * @param array $basket
     * @return array
     */
    public function authorizePayment($user, $basket, $paymentName, $campaign = 0)
    {
        $response = array("success" => false);
        try {
            $this->initApi($user['additional']['country']['countryiso']);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            if (!empty($paymentName) && $paymentName === "colo_afterpay_dd") {
                $iban = trim($user['additional']['payment']['data']['sSepaIban']);
                $bic = trim($user['additional']['payment']['data']['sSepaBic']);
                $data = array(
                    'payment' => array(
                        'type' => 'Invoice',
                        'directDebit' => array(
                            'bankCode' => $bic,
                            'bankAccount' => $iban
                        )
                    )
                );
            } else if (!empty($paymentName) && $paymentName === "colo_afterpay_installment") {
                $installmentPlan = $this->session->offsetGet('ColoAfterpayInstallmentPlan');
                if (empty($installmentPlan)) {
                    $this->logger->log("Installment not selected", "error");

                    return $response;
                }
                $installment = $this->getInstallment($installmentPlan, $user, $basket);
                if (empty($installment)) {
                    $this->logger->log("Selected installment not valid", "error");

                    return $response;
                }
                $iban = trim($user['additional']['payment']['data']['sSepaIban']);
                $bic = trim($user['additional']['payment']['data']['sSepaBic']);
                $data = array(
                    'payment' => array(
                        'type' => 'Installment',
                        'installment' => array(
                            'profileNo' => $installment['installmentProfileNumber'],
                            'customerInterestRate' => $installment['interestRate'],
                            'numberOfInstallments' => $installment['numberOfInstallments']
                        ),
                        'directDebit' => array(
                            'bankCode' => $bic,
                            'bankAccount' => $iban
                        )
                    )
                );
            } else {
                $data = array(
                    'payment' => array(
                        'type' => 'Invoice'
                    )
                );
            }
            if ($campaign && !empty($this->config['colo_afterpay_campaign'])) {
                $data['payment']['campaign']['campaignNumber'] = $this->config['colo_afterpay_campaign'];
            } else if ($campaign && empty($this->config['colo_afterpay_campaign'])) {
                return $response;
            }
            $data = $this->prepareData($data, $user, $basket);
            $url = $this->apiUrl . "checkout/authorize";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");

            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response, true);
            if ($response['success']) {
                $response['token'] = $curlResponse['checkoutId'];
                $response['transactionId'] = $data['order']['number'];
                $response['paymentStatus'] = $this->getPaymentStatus($paymentName);
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");
        }

        return $response;
    }

    /**
     * Called from debitAction and installmentAction methods of ColoAfterpay controller
     * Gets all available payment methods and checks if payment method is available
     *
     * @param array $user
     * @param array $basket
     * @param array $paymentName (directDebit. installment)
     * @return array
     */
    public function isPaymentAvailable($user, $basket, $paymentName)
    {
        $isAvailable = false;
        if (empty($this->session)) {
            return $isAvailable;
        }
        if (empty($paymentName) || !in_array($paymentName, array('account', 'campaigns', 'directDebit', 'installment'))) {
            $message = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index')->get('GeneralError');
            $this->session->offsetSet('ColoAfterpayErrorMessages', $message);

            return $isAvailable;
        }
        $response = $this->getAvailablePayments($user, $basket);
        if ($response['success'] && !empty($response['paymentMethods']) && !empty($response['token']) && !empty($response['transactionId'])) {
            foreach ($response['paymentMethods'] as $paymentMethod) {
                if (isset($paymentMethod[$paymentName]) && $paymentMethod[$paymentName]['available']) {
                    $isAvailable = true;
                    $this->session->offsetSet('ColoPaymentAvailable', 1);
                    $this->session->offsetSet('ColoPaymentTempId', $response['token']);
                    $this->session->offsetSet('ColoPaymentTransactionId', $response['transactionId']);
                    break;
                }
            }
        } else {
            $this->session->offsetSet('ColoAfterpayErrorMessages', $response['message']);
        }

        return $isAvailable;
    }

    /**
     * Checks if selected installment plan is valid
     *
     * @param string $plan
     * @param array $user
     * @param array $basket
     * @return bool
     */
    public function isInstallmentPlanValid($plan, $user, $basket)
    {
        return empty($this->getInstallment($plan, $user, $basket)) ? false : true;
    }

    /**
     * Called from debitAction method of ColoAfterpay controller
     * Calls afterpay /api/v3/validate/bank-account API method and
     * validates customer's bank account
     *
     * @param string $iban
     * @param string $bic
     * @return array
     */
    public function validateBankAccount($iban, $bic, $user)
    {
        $response = array("success" => false);
        if (empty($this->session)) {
            return $response;
        }
        try {
            $this->initApi($user['additional']['country']['countryiso']);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            $data = array(
                'bankAccount' => $iban,
                'bankCode' => $bic
            );
            $url = $this->apiUrl . "validate/bank-account";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");
            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response);
            if ($response['success']) {
                $this->session->offsetSet('ColoValidAccount', 1);
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");
        }

        return $response;
    }

    /**
     * Called from debitAction method of ColoAfterpay controller
     * Calls afterpay /api/v3/checkout/{$token}/contract API method and
     * returns contract details to controller which shows it to customer
     *
     * @param string $token
     * @param string $iban
     * @param string $bic
     * @return array
     */
    public function createContract($token, $iban, $bic, $user, $basket, $paymentName = "")
    {
        $response = array("success" => false);
        if (empty($this->session)) {
            return $response;
        }
        try {
            $this->initApi($user['additional']['country']['countryiso']);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            if (empty($token)) {
                $this->logger->log("Token is missing", "error");

                return $response;
            }
            if (empty($iban)) {
                $this->logger->log("IBAN is missing", "error");

                return $response;
            }
            if (empty($bic)) {
                $this->logger->log("BIC is missing", "error");

                return $response;
            }
            $data = array(
                "paymentInfo" => array(
                    "type" => "Invoice",
                    "directDebit" => array(
                        "bankCode" => $bic,
                        "bankAccount" => $iban
                    )
                )
            );
            if (!empty($paymentName) && $paymentName === "colo_afterpay_installment") {
                $installmentPlan = $this->session->offsetGet('ColoAfterpayInstallmentPlan');
                if (empty($installmentPlan)) {
                    $this->logger->log("Installment not selected", "error");

                    return $response;
                }
                $installment = $this->getInstallment($installmentPlan, $user, $basket);
                if (empty($installment)) {
                    $this->logger->log("Selected installment not valid", "error");

                    return $response;
                }
                $data['paymentInfo']['installment'] = array(
                    'profileNo' => $installment['installmentProfileNumber'],
                    'customerInterestRate' => $installment['interestRate'],
                    'numberOfInstallments' => $installment['numberOfInstallments']
                );
            }
            $url = $this->apiUrl . "checkout/{$token}/contract";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");
            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response);
            if ($response['success']) {
                $response['token'] = $token;
                $response['contractId'] = $curlResponse['contractId'];
                $response['contractNumber'] = $curlResponse['contractList'][0]['contractNumber'];
                $response['contract'] = $curlResponse['contractList'][0]['contractContent'];
                $this->session->offsetSet('ColoContractId', $curlResponse['contractId']);
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");
        }

        return $response;
    }

    /**
     * Makes the authorize request by sending contact id
     *
     * @param string $contractId
     * @param string $token
     * @param string $transactionId
     * @param array $user
     * @param string $paymentName
     * @return array
     */
    public function authorizePaymentSecondStep($contractId, $token, $transactionId, $user, $basket, $paymentName)
    {
        $response = array("success" => false);
        try {
            $this->initApi($user['additional']['country']['countryiso']);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            if (empty($contractId)) {
                $this->logger->log("Contract id is missing", "error");

                return $response;
            }
            if (empty($token)) {
                $this->logger->log("Token is missing", "error");

                return $response;
            }
            if (empty($transactionId)) {
                $this->logger->log("TransactionID is missing", "error");

                return $response;
            }
            $data = array(
                'payment' => array(
                    'type' => "Invoice"
                ),
                'checkoutId' => $token,
                'contractId' => $contractId
            );
            if (!empty($paymentName) && $paymentName === "colo_afterpay_installment") {
                $installmentPlan = $this->session->offsetGet('ColoAfterpayInstallmentPlan');
                if (empty($installmentPlan)) {
                    $this->logger->log("Installment not selected", "error");

                    return $response;
                }
                $installment = $this->getInstallment($installmentPlan, $user, $basket);
                if (empty($installment)) {
                    $this->logger->log("Selected installment not valid", "error");

                    return $response;
                }
                $data['payment']['installment'] = array(
                    'profileNo' => $installment['installmentProfileNumber'],
                    'customerInterestRate' => $installment['interestRate'],
                    'numberOfInstallments' => $installment['numberOfInstallments']
                );
            }
            $data = $this->prepareRiskData($data, $user);
            $url = $this->apiUrl . "checkout/authorize";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");
            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response, true);
            if ($response['success']) {
                $response['token'] = $curlResponse['checkoutId'];
                $response['transactionId'] = $transactionId;
                $response['paymentStatus'] = $this->getPaymentStatus($paymentName);
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");
        }

        return $response;
    }

    /**
     * Checks if payment process is completed
     */
    public function isPaymentProcessCompleted()
    {
        $paymentCompleted = (int)$this->container->get('session')->offsetGet('ColoPaymentCompleted');

        return $paymentCompleted === 1 ? true : false;
    }

    /**
     * Called from shippingPayment page of Checkout controller
     * Calls afterpay /api/v3/lookup/installment-plans API method
     *
     * @param string $countryiso
     * @param float $amount
     * @param string $currency
     * @return array
     */
    public function getAvailableInstallments($countryiso, $amount, $currency)
    {
        $response = array();
        try {
            $this->initApi($countryiso);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            $data = array(
                'amount' => $amount,
                'countryCode' => $countryiso,
                'currency' => $currency
            );
            $url = $this->apiUrl . "lookup/installment-plans";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");
            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response);
            if ($response['success']) {
                return $response['availableInstallmentPlans'];
            } else {
                return array();
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");
        }

        return $response;
    }

    /**
     * Returns ordernumbers of orders which should be captured
     * Used from cronjob
     *
     * @return array
     */
    public function getCapturableOrders()
    {
        $ordernumbers = array();
        $paymentMethods = Constants::PAYMENT_METHODS;
        $sql = "SELECT 
            `s_order`.*,
            `s_core_countries`.`countryiso` 
          FROM
            `s_order` 
            INNER JOIN `s_order_attributes` 
              ON `s_order`.`id` = `s_order_attributes`.`orderID` 
            INNER JOIN `s_core_paymentmeans` 
              ON `s_order`.`paymentID` = `s_core_paymentmeans`.`id` 
            INNER JOIN `s_order_billingaddress` 
              ON `s_order_billingaddress`.`orderID` = `s_order`.`id` 
            INNER JOIN `s_core_countries` 
              ON `s_core_countries`.`id` = `s_order_billingaddress`.`countryID` 
          WHERE (
              `s_order_attributes`.`colo_captured` IS NULL 
              OR `s_order_attributes`.`colo_captured` = 0
            ) 
            AND `s_order`.`transactionID` IS NOT NULL 
            AND `s_order`.`transactionID` <> '' 
            AND `s_core_paymentmeans`.`name` IN (" . str_repeat("?,", count($paymentMethods) - 1) . "?) ;";
        $orders = $this->db->fetchAll($sql, $paymentMethods);
        if (empty($orders)) {
            return $ordernumbers;
        }
        $configs = array();
        foreach ($orders as $order) {
            $shopId = $order['subshopID'];
            $countryiso = $order['countryiso'];
            if (empty($configs[$shopId])) {
                $this->config = null;
                $this->initApi($countryiso, $shopId);
                $configs[$shopId] = $this->config;
            }
            if ((int)$order['status'] === (int)$configs[$shopId]['colo_afterpay_capture_status']) {
                $ordernumbers[] = $order['ordernumber'];
            }
        }
        $this->config = null;

        return $ordernumbers;
    }

    /**
     * @param $shop
     * @param $countryID
     * @return null|string
     */
    public function getMerchantId($shop, $countryID)
    {
        $coloAfterpayMerchantID = null;

        $config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        if ($config['colo_afterpay_tos_checkbox']) {
            foreach ($this->availableCountries as $availableCountry) {
                if ($availableCountry['id'] == $countryID) {
                    $coloAfterpayMerchantID = $availableCountry['colo_afterpay_merchant_id'];
                    break;
                }
            }
            if (empty($coloAfterpayMerchantID)) {
                $coloAfterpayMerchantID = "default";
            }
        }

        return $coloAfterpayMerchantID;
    }

    /**
     * Called from internal isDebitAvailable method
     * Calls afterpay /api/v3/checkout/payment-methods API method and
     * returns all available payment methods and temporary id for the order
     *
     * @param array $user
     * @param array $basket
     * @return array
     */
    protected function getAvailablePayments($user, $basket)
    {
        $response = array("success" => false);
        try {
            $this->initApi($user['additional']['country']['countryiso']);
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                $this->logger->log("Wrong plugin configuration", "error");

                return $response;
            }
            $data = $this->prepareData(array(), $user, $basket);
            $url = $this->apiUrl . "checkout/payment-methods";
            $this->logger->log($url, "info");
            $this->logger->log("\r\n" . print_r($data, true), "info");
            $curlResponse = $this->curl($url, json_encode($data));
            $response = $this->handleResponse($curlResponse, $response);
            if ($response['success']) {
                $response['token'] = $curlResponse['checkoutId'];
                $response['transactionId'] = $data['order']['number'];
                $response['paymentMethods'] = $curlResponse['paymentMethods'];
            }
        } catch (\Exception $ex) {
            $this->logger->log($ex->getMessage(), "error");
        }

        return $response;
    }

    /**
     * Checks if order can be captured
     *
     * @param $order
     * @return bool
     */
    private function isOrderCapturable($order)
    {
        $paymentName = $order->getPayment()->getName();
        if (!in_array($paymentName, Constants::PAYMENT_METHODS)) {
            return false;
        }
        $captured = $order->getAttribute()->getColoCaptured();
        if (!empty($captured)) {
            return false;
        }
        $afterpayOrdernumber = $order->getTransactionId();
        if (empty($afterpayOrdernumber)) {
            return false;
        }
        $orderStatus = $order->getOrderStatus()->getId();
        if ($orderStatus != $this->config['colo_afterpay_capture_status']) {
            return false;
        }

        return true;
    }

    /**
     * Checks if order can be captured
     *
     * @param $order
     * @return bool
     */
    private function isOrderCaptured($order)
    {
        $captured = $order->getAttribute()->getColoCaptured();

        return empty($captured) ? false : true;
    }

    /**
     * Internal method which prepares customer and order data for API requests
     *
     * @param $data
     * @param $user
     * @param $basket
     * @return array
     */
    private function prepareData($data, $user, $basket)
    {
        $uniqueOrdernumber = $this->generateUniqueOrdernumber();
        $data = $this->prepareCustomerData($data, $user);
        $data = $this->prepareOrderData($data, $uniqueOrdernumber, $basket);

        return $data;
    }

    /**
     * Internal method which prepares customer data for API requests
     *
     * @param array $data
     * @param string $uniqueOrdernumber
     * @param array $basket
     * @return array
     */
    private function prepareCustomerData($data, $user)
    {
        $addresses = array('billingaddress' => 'customer');
        if ($user['billingaddress']['id'] !== $user['shippingaddress']['id']) {
            $addresses['shippingaddress'] = 'deliveryCustomer';
        }
        foreach ($addresses as $address => $key) {
            $countrySelector = "country";
            if ($address === "shippingaddress") {
                $countrySelector = "countryShipping";
            }
            $data[$key] = array(
                "customerNumber" => $user['additional']['user']['customernumber'],
                "salutation" => $user[$address]['salutation'] === "mr" ? "Mr" : "Mrs",
                "firstName" => $user[$address]['firstname'],
                "lastName" => $user[$address]['lastname'],
                "email" => $user['additional']['user']['email'],
                "birthDate" => $user['additional']['user']['birthday'],
                "customerCategory" => "Person",
                "address" => array(
                    "street" => $user[$address]['street'],
                    "streetNumber" => "",
                    "postalCode" => $user[$address]['zipcode'],
                    "postalPlace" => $user[$address]['city'],
                    "countryCode" => $user['additional'][$countrySelector]['countryiso'],
                    "careOf" => !empty($user[$address]['additional_address_line1']) ? $user[$address]['additional_address_line1'] : ""
                ),
                //"conversationLanguage" => $user['additional'][$countrySelector]['countryiso'] // Needs fix as the language has to be used. Not the country (e.g. AT lang = DE)
            );
            if ($address === "billingaddress") {
                $data = $this->prepareRiskData($data, $user);
            }
            if (!empty($user[$address]['phone']) && ($user['additional'][$countrySelector]['countryiso'] == "NL" OR $user['additional'][$countrySelector]['countryiso'] == "BE")) {
                $data[$key]["mobilePhone"] = $user[$address]['phone'];
            }
        }

        return $data;
    }

    /**
     * @param $data
     * @param $user
     * @return mixed
     */
    private function prepareRiskData($data, $user)
    {
        $request = $this->container->get('front')->Request();
        $ipAddress = $request->getServer('REMOTE_ADDR', '');
        $orders = $this->getUserOrders($user['additional']['user']['id']);
        $data['customer']['riskData'] = [
            'ipAddress' => $ipAddress,
            'existingCustomer' => empty($orders) ? false : true,
            'numberOfTransactions' => count($orders),
            'customerSince' => $user['additional']['user']['firstlogin'],
            'profileTrackingId' => $this->container->get('session')->offsetGet('sessionId')
        ];

        return $data;
    }

    /**
     * Internal method which prepares order data for API requests
     *
     * @param array $data
     * @param string $uniqueOrdernumber
     * @param array $basket
     * @return array
     */
    private function prepareOrderData($data, $uniqueOrdernumber, $basket)
    {
        $mediaService = $this->container->get('shopware_media.media_service');
        $items = array();
        foreach ($basket['content'] as $index => $basketItem) {
            $netPrice = round((float)$basketItem['netprice'], 2);
            $grossPrice = round((float)$basketItem['priceNumeric'], 2);
            $item = array(
                "productId" => $basketItem['ordernumber'],
                "description" => $basketItem['articlename'],
                "netUnitPrice" => $netPrice,
                "grossUnitPrice" => $grossPrice,
                "quantity" => (int)$basketItem['quantity'],
                "vatPercent" => (float)$basketItem['tax_rate'],
                "vatAmount" => $grossPrice - $netPrice,
                "lineNumber" => $index + 1
            );
            if (!empty($basketItem['image'])) {
                $extension = pathinfo($basketItem['image']['source'], \PATHINFO_EXTENSION);
                if (in_array($extension, array("png", "jpg", "jpeg"))) {
                    $item['imageUrl'] = $mediaService->getUrl($mediaService->normalize($basketItem['image']['source']));
                    if (substr($item['imageUrl'], 0, 2) === '//') $item['imageUrl'] = 'https:' . $item['imageUrl']; // Fix for CDN URLs
                }
            }
            $items[] = $item;
        }
        if ($basket['sShippingcosts'] > 0) {
            $netPrice = round((float)$basket['sShippingcostsNet'], 2);
            $grossPrice = round((float)$basket['sShippingcostsWithTax'], 2);
            $lineNumber = count($items) + 1;

            $dispatch = $this->getSelectedDispatch();
            if (empty($dispatch)) {
                $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
                $productDescription = $snippetManager->get('ShippingCostLineName', 'Versandkosten', true);
            } else {
                $productDescription = $dispatch['name'];
            }
            $items[] = array(
                "productId" => 'SHIPPINGCOST',
                "description" => $productDescription,
                "netUnitPrice" => $netPrice,
                "grossUnitPrice" => $grossPrice,
                "quantity" => 1,
                "vatPercent" => (float)$basket['sShippingcostsTax'],
                "vatAmount" => $grossPrice - $netPrice,
                "lineNumber" => $lineNumber
            );
        }
        $data['order'] = array(
            "number" => $uniqueOrdernumber,
            "totalNetAmount" => $basket['AmountNetNumeric'],
            "totalGrossAmount" => $basket['AmountNumeric'],
            "currency" => $basket['sCurrencyName'],
            "items" => $items
        );

        return $data;
    }

    /**
     * Internal method which prepares capture data
     *
     * @param \Shopware\Models\Order\Order $order
     * @return array
     */
    private function prepareCaptureData($order)
    {
        $items = array();
        $maxTax = 0;
        foreach ($order->getDetails() as $index => $detail) {
            if ($detail->getQuantity() < 1) continue; // Fix for items with no quantity. AfterPay does not like them.
            $taxRate = (float)$detail->getTaxRate();
            $grossPrice = round((float)$detail->getPrice(), 2);
            $netPrice = round(($grossPrice / ((100 + $taxRate) / 100)), 2);
            $item = array(
                "productId" => $detail->getArticleNumber(),
                "description" => $detail->getArticleName(),
                "netUnitPrice" => $netPrice,
                "grossUnitPrice" => $grossPrice,
                "quantity" => (int)$detail->getQuantity(),
                "vatPercent" => $taxRate,
                "vatAmount" => $grossPrice - $netPrice,
                "lineNumber" => $index + 1
            );
            $items[] = $item;
            if ($maxTax < $taxRate) {
                $maxTax = $taxRate;
            }
        }
        $shippingCost = (float)$order->getInvoiceShipping();
        if ($shippingCost > 0) {
            $netPrice = round((float)$order->getInvoiceShippingNet(), 2);
            $grossPrice = round($shippingCost, 2);
            $lineNumber = count($items) + 1;

            $dispatch = $order->getDispatch();
            if (empty($dispatch)) {
                $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
                $productDescription = $snippetManager->get('ShippingCostLineName', 'Versandkosten', true);
            } else {
                $productDescription = $dispatch->getName();
            }
            $items[] = array(
                "productId" => 'SHIPPINGCOST',
                "description" => $productDescription,
                "netUnitPrice" => $netPrice,
                "grossUnitPrice" => $grossPrice,
                "quantity" => 1,
                "vatPercent" => $maxTax,
                "vatAmount" => $grossPrice - $netPrice,
                "lineNumber" => $lineNumber
            );
        }
        $data = array(
            'orderDetails' => array(
                'totalGrossAmount' => round($order->getInvoiceAmount(), 2),
                'totalNetAmount' => round($order->getInvoiceAmountNet(), 2),
                'currency' => $order->getCurrency(),
                'items' => $items
            ),
            'references' => array(
                'yourReference' => $order->getNumber(),
                'contractDate' => $order->getOrderTime()->format('Y-m-d H:i:s'),
            ),
            'invoiceNumber' => $order->getNumber(),
            'parentTransactionReference' => $order->getNumber()
        );
        $invoiceNumber = $invoiceDate = null;
        $documents = $order->getDocuments();
        if (!empty($documents)) {
            foreach ($documents as $document) {
                if ((int)$document->getTypeId() === 1) {
                    $invoiceDate = $document->getDate()->format("Y-m-d H:i:s");
                    $invoiceNumber = $document->getDocumentId();
                    break;
                }
            }
        }
        if (!empty($invoiceDate) && !empty($invoiceNumber)) {
            $data['invoiceNumber'] = $invoiceNumber;
            $data['references']['invoiceDate'] = $invoiceDate;
        }

        /*
        Afterpay rejects capture when the shipping company is not provided. So we don't use this field for the moment
        if (!empty($order->getTrackingCode())) {
            $data['shippingDetails'] = array(
                array(
                    'type' => 'Shipment',
                    'trackingId' => $order->getTrackingCode()
                )
            );
        }
        */

        return $data;
    }

    /**
     * @param $order
     * @param $amount
     * @param $creditNoteNumber
     * @return array
     */
    private function prepareRefundData($order, $amount, $creditNoteNumber)
    {
        $captureNumber = $order->getAttribute()->getColoCaptureNumber();
        $data = array(
            'captureNumber' => $captureNumber
        );
        if (!empty($creditNoteNumber)) {
            $data['creditNoteNumber'] = $creditNoteNumber;
        }
        $data['orderItems'] = array(
            array(
                'refundType' => 'Return',
                'productId' => '1',
                'description' => 'Refund',
                'quantity' => '1',
                'grossUnitPrice' => $amount
            )
        );

        return $data;
    }

    /**
     * @param $userId
     * @return array
     */
    private function getUserOrders($userId)
    {
        $sql = "SELECT * FROM `s_order` WHERE `userID`=? AND `status` NOT IN (?, ?) AND `cleared`=?;";
        $orders = Shopware()->Db()->fetchAll($sql, [$userId, OrderStatus::ORDER_STATE_CANCELLED_REJECTED, OrderStatus::ORDER_STATE_CANCELLED, OrderStatus::PAYMENT_STATE_COMPLETELY_PAID]);

        return $orders;
    }

    /**
     * Returns payment status id that should be used after order is completed
     *
     * @param $paymentName
     * @return int
     */
    private function getPaymentStatus($paymentName)
    {
        $status = Constants::PAYMENTSTATUSPAID;
        foreach ($this->paymentDetails as $paymentDetails) {
            if ($paymentDetails['name'] === $paymentName) {
                $status = $paymentDetails['colo_afterpay_payment_status_id'];
                break;
            }
        }

        return $status;
    }

    /**
     * Get countries information for which afterpay payments are allowed
     * @param null $shopId
     * @return array
     */
    private function getAvailableCountries($shopId = null)
    {
        /** @var \Shopware\Models\Shop\Repository $shopRepo */
        $shopRepo = $this->entityManager->getRepository(ShopModel::class);
        /** @var ShopModel $shop */
        if (!empty($shopId)) {
            $shop = $shopRepo->find($shopId);
        } else if ($this->container->initialized('shop') && $this->container->has('shop')) {
            $shop = $this->container->get('shop');
        }
        if (empty($shop)) {
            $shop = $shopRepo->getActiveDefault();
        }
        $countries = $this->getAvailableCountriesForDefaultShop();
        $defaultShop = $shop->getDefault();
        if (!$defaultShop) {
            $shopId = $shop->getId();
            $countries = $this->getAvailableCountriesForShop($shopId, $countries);
        }

        return $countries;
    }

    /**
     * @param $shopId
     * @param array $countries
     * @return array
     */
    private function getAvailableCountriesForShop($shopId, $countries = [])
    {
        $countriesById = [];
        foreach ($countries as $country) {
            $countriesById[$country['id']] = $country;
        }
        $sql = "SELECT
                  `s_core_countries`.`id`,
                  `s_core_countries`.`countryiso`,
                  `s_core_countries`.`countryname`,
                  `s_core_translations`.`objectdata`
                FROM
                  `s_core_translations`
                  INNER JOIN `s_core_countries`
                    ON `s_core_translations`.`objectkey` = `s_core_countries`.`id`
                WHERE `objecttype` = 's_core_countries_attributes'
                  AND `objectlanguage` = ?;";
        $data = $this->db->fetchAll($sql, [$shopId]);
        if (!empty($data)) {
            foreach ($data as $countryData) {
                $countryId = $countryData['id'];
				if (empty($countryData['objectdata'])) {
					continue;
				}
                $objectdata = unserialize($countryData['objectdata'], ['allowed_classes' => false]);
                if (isset($objectdata['__attribute_colo_afterpay_active']) && (int)$objectdata['__attribute_colo_afterpay_active'] === 1) {
                    if (!isset($countriesById[$countryId])) {
                        $countriesById[$countryId] = [
                            'id' => $countryId,
                            'countryiso' => $countryData['countryiso'],
                            'countryname' => $countryData['countryname'],
                        ];
                    }
                    if (!empty($objectdata['__attribute_colo_afterpay_api_key'])) {
                        $countriesById[$countryId]['colo_afterpay_api_key'] = $objectdata['__attribute_colo_afterpay_api_key'];
                    }
                    if (!empty($objectdata['__attribute_colo_afterpay_sandbox_api_key'])) {
                        $countriesById[$countryId]['colo_afterpay_sandbox_api_key'] = $objectdata['__attribute_colo_afterpay_sandbox_api_key'];
                    }
                    if (!empty($objectdata['__attribute_colo_afterpay_public_sandbox_api_key'])) {
                        $countriesById[$countryId]['colo_afterpay_public_sandbox_api_key'] = $objectdata['__attribute_colo_afterpay_public_sandbox_api_key'];
                    }
                    if (!empty($objectdata['__attribute_colo_afterpay_merchant_id'])) {
                        $countriesById[$countryId]['colo_afterpay_merchant_id'] = $objectdata['__attribute_colo_afterpay_merchant_id'];
                    }
                } else if (isset($objectdata['__attribute_colo_afterpay_active']) && (int)$objectdata['__attribute_colo_afterpay_active'] === 0 && isset($countriesById[$countryId])) {
                    unset($countriesById[$countryId]);
                }
            }
        }

        return array_values($countriesById);
    }

    /**
     * @return array
     */
    private function getAvailableCountriesForDefaultShop()
    {
        $sql = "SELECT 
                `s_core_countries`.`id`,
                `s_core_countries`.`countryiso`,
                `s_core_countries`.`countryname`,
                `s_core_countries_attributes`.`colo_afterpay_api_key`,
                `s_core_countries_attributes`.`colo_afterpay_sandbox_api_key`,
                `s_core_countries_attributes`.`colo_afterpay_public_sandbox_api_key`,
                `s_core_countries_attributes`.`colo_afterpay_merchant_id`
              FROM
                `s_core_countries` 
                INNER JOIN `s_core_countries_attributes` 
                  ON `s_core_countries`.`id` = `s_core_countries_attributes`.`countryID` 
              WHERE `s_core_countries_attributes`.`colo_afterpay_active` = 1 ;";

        return $this->db->fetchAll($sql);
    }

    /**
     * @return array
     */
    private function getPaymentDetails()
    {
        $paymentNames = Constants::PAYMENT_METHODS;
        $sql = "SELECT 
            `s_core_paymentmeans`.*,
            `s_core_paymentmeans_attributes`.`colo_afterpay_max_basket_value`,
            `s_core_paymentmeans_attributes`.`colo_afterpay_min_basket_value`,
            `s_core_paymentmeans_attributes`.`colo_afterpay_payment_status_id` 
          FROM
            `s_core_paymentmeans` 
            LEFT JOIN `s_core_paymentmeans_attributes` 
              ON `s_core_paymentmeans`.`id` = `s_core_paymentmeans_attributes`.`paymentmeanID` 
          WHERE `s_core_paymentmeans`.`name` IN (?" . str_repeat(",?", count($paymentNames) - 1) . ") ;";

        return $this->db->fetchAll($sql, $paymentNames);
    }

    /**
     * Get selected dispatch or select a default dispatch
     *
     * @return bool|array
     */
    public function getSelectedDispatch()
    {
        if (empty($this->session['sCountry'])) {
            return false;
        }

        $dispatches = Shopware()->Modules()->Admin()->sGetPremiumDispatches($this->session['sCountry'], null, $this->session['sState']);
        if (empty($dispatches)) {
            unset($this->session['sDispatch']);

            return false;
        }

        foreach ($dispatches as $dispatch) {
            if ($dispatch['id'] == $this->session['sDispatch']) {
                return $dispatch;
            }
        }
        $dispatch = reset($dispatches);
        $this->session['sDispatch'] = (int)$dispatch['id'];

        return $dispatch;
    }

    /**
     * Save captured amount in database
     *
     * @param $orderId
     * @param $captureNumber
     * @param $amount
     * @param int $status
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function saveTransaction($orderId, $captureNumber, $amount, $status = 0)
    {
        $transaction = new Transactions();
        $transaction->setOrderId($orderId);
        $transaction->setNumber($captureNumber);
        $transaction->setAmount($amount);
        $transaction->setStatus($status);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }

    /**
     * Returns installment by profile number
     *
     * @return array
     */
    private function getInstallment($plan, $user, $basket)
    {
        $installments = $this->getAvailableInstallments($user['additional']['country']['countryiso'], $basket['sAmount'], $basket['sCurrencyName']);
        if (empty($installments)) {
            return array();
        }
        foreach ($installments as $installment) {
            if ($installment['installmentProfileNumber'] == $plan) {
                return $installment;
            }
        }

        return array();
    }

    /**
     * Handles response from Afterpay API
     * @param array $curlResponse
     * @param array $response
     * @param boolean $fromAuthorize
     * @return array
     */
    private function handleResponse($curlResponse, $response, $fromAuthorize = false)
    {
        $response['transactionMode'] = $this->config['colo_afterpay_mode'];
        if ((!empty($curlResponse) && $curlResponse['outcome'] === 'Accepted') ||
            (!empty($curlResponse) && $curlResponse['isValid']) ||
            (!empty($curlResponse) &&
                !empty($curlResponse['contractId']) &&
                !empty($curlResponse['contractList']) &&
                $curlResponse['contractId'] !== "00000000-0000-0000-0000-000000000000") ||
            (!empty($curlResponse) && !empty($curlResponse['captureNumber']) ||
                (!empty($curlResponse) && !empty($curlResponse['availableInstallmentPlans']))) ||
            (!empty($curlResponse) && !empty($curlResponse['refundNumbers']))) {
            $this->logger->log("\r\n" . print_r($curlResponse, true), "success");

            if (!empty($curlResponse['captureNumber'])) {
                $response['captureNumber'] = $curlResponse['captureNumber'];
            }
            if (!empty($curlResponse['availableInstallmentPlans'])) {
                $response['availableInstallmentPlans'] = $curlResponse['availableInstallmentPlans'];
            }
            if (!empty($curlResponse['refundNumbers'])) {
                $response['refundNumbers'] = $curlResponse['refundNumbers'];
                $response['totalAuthorizedAmount'] = $curlResponse['totalAuthorizedAmount'];
                $response['totalCapturedAmount'] = $curlResponse['totalCapturedAmount'];
            }
            $response['success'] = true;

            $response = $this->container->get('events')->filter('AfterPay_AfterpayService_handleResponse_Success', $response, [
                'subject' => $this,
                'curlResponse' => $curlResponse,
                'fromAuthorize' => $fromAuthorize
            ]);
        } else {
            $this->logger->log("\r\n" . print_r($curlResponse, true), "error");
            if (!empty($curlResponse['riskCheckMessages'])) {
                $snippetManager = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index');
                $response['message'] = "";
                foreach ($curlResponse['riskCheckMessages'] as $riskMessage) {
                    if (!empty($riskMessage['customerFacingMessage'])) {
                        $response['message'] .= $riskMessage['customerFacingMessage'] . "<br/>";
                    } else {
                        if ($riskMessage['actionCode'] === "AskConsumerToReEnterData") {
                            $message = $snippetManager->get('AskConsumerToReEnterDataError');
                            $response['message'] .= $message . "<br/>";
                        } else if ($riskMessage['actionCode'] === "AskConsumerToConfirm") {
                            $message = $snippetManager->get('AskConsumerToConfirmError');
                            $response['message'] .= $message . "<br/>";
                        } else if ($riskMessage['actionCode'] === "OfferSecurePaymentMethods") {
                            $message = $snippetManager->get('OfferSecurePaymentMethodsError');
                            $response['message'] .= $message . "<br/>";
                        }
                    }
                    if (($riskMessage['actionCode'] === "AskConsumerToReEnterData" || $riskMessage['actionCode'] === "AskConsumerToConfirm") && !isset($response['address'])) {
                        if (!empty($curlResponse['customer']) && !empty($curlResponse['customer']['addressList'])) {
                            $address = $curlResponse['customer']['addressList'][0];
                            $street = $address['street'];
                            if (!empty($address['streetNumber'])) {
                                $street .= " " . $address['streetNumber'];
                            }
                            $response['address'] = array(
                                'street' => $street,
                                'zipcode' => $address['postalCode'],
                                'city' => $address['postalPlace'],
                                'country' => $address['countryCode'],
                            );
                        } else {
                            $response['address'] = 1;
                        }
                    }
                }
                if (!empty($response['message'])) {
                    $response['message'] = substr($response['message'], 0, -5);
                }
            } else if (!empty($curlResponse[0]) && !empty($curlResponse[0]['customerFacingMessage'])) {
                $response['message'] = $curlResponse[0]['customerFacingMessage'];
            } else if (!empty($curlResponse) && !empty($curlResponse['customerFacingMessage'])) {
                $response['message'] = $curlResponse['customerFacingMessage'];
            }
            $response = $this->container->get('events')->filter('AfterPay_AfterpayService_handleResponse_Failure', $response, [
                'subject' => $this,
                'curlResponse' => $curlResponse,
                'fromAuthorize' => $fromAuthorize
            ]);
        }

        return $response;
    }

    /**
     * Initializes API details
     */
    private function initApi($countryiso, $shopId = null, $mode = null)
    {

        if (!isset($this->config) && !empty($countryiso)) {
            $shopRepo = $this->entityManager->getRepository(ShopModel::class);
            if ($this->container->initialized('shop') && $this->container->has('shop')) {
                $shop = $this->container->get('shop');
            } else if (!empty($shopId)) {
                $shop = $shopRepo->find($shopId);
            }
            if (empty($shop)) {
                $shop = $shopRepo->getActiveDefault();
            }
            $this->config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);

            $country = null;
            foreach ($this->availableCountries as $details) {
                if ($details['countryiso'] === $countryiso) {
                    $country = $details;
                    break;
                }
            }
            if (empty($country)) {
                $this->logger->log('API keys are not set');
                return false;
            }

            if (empty($mode)) {
                $mode = $this->config['colo_afterpay_mode'];
            }
            $apiKey = '';
            $apiUrl = '';

            switch ($mode) {
                case "public_sandbox":
                    $apiKey = $country['colo_afterpay_public_sandbox_api_key'];
                    $apiUrl = Constants::PUBLIC_SANDBOX_API_URL;
                    $this->config['colo_afterpay_mode'] = $mode;
                    break;
                case "sandbox":
                    $apiKey = $country['colo_afterpay_sandbox_api_key'];
                    $apiUrl = Constants::SANDBOX_API_URL;
                    $this->config['colo_afterpay_mode'] = $mode;
                    break;
                case "prod":
                    $apiKey = $country['colo_afterpay_api_key'];
                    $apiUrl = Constants::API_URL;
                    $this->config['colo_afterpay_mode'] = $mode;
                    break;
                default:
                    break;
            }
            if (empty($apiKey) || empty($apiUrl)) {

                return false;
            }
            $this->apiHeaders = array(
                "cache-control: no-cache",
                "content-type: application/json",
                "x-auth-key: {$apiKey}"
            );
            $this->apiKey = $apiKey;
            $this->apiUrl = $apiUrl;
        }
    }

    /**
     * Internal method for generating unique ordernumber
     *
     * @param array $user
     * @param array $basket
     * @return array
     */
    private function generateUniqueOrdernumber()
    {
		$length = 8; // Will give a 16 digit string in combination with bin2hex
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes($length));
		}
		if (function_exists('mcrypt_create_iv')) {
			return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
		}
		if (function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes($length));
		}
        return uniqid("", true);
    }

    /**
     * Utility method for sending cURL to afterpay API
     *
     * @param string $url
     * @param string $data
     * @param array $headers
     * @param boolean $json
     * @return array
     */
    private function curl($url, $data = "", $headers = array(), $json = true)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $apiHeaders = array_merge($this->apiHeaders, $headers);
        if (!empty($apiHeaders)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $apiHeaders);
        }
        $response = curl_exec($curl);
        if ($json) {
            $response = json_decode($response, true);
        }
        curl_close($curl);

        return $response;
    }

}
