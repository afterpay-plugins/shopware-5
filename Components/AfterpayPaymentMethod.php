<?php

namespace ShopwarePlugin\PaymentMethods\Components;

use ShopwarePlugin\PaymentMethods\Components\GenericPaymentMethod;
use Doctrine\ORM\AbstractQuery;

/**
 * Class AfterpayPaymentMethod
 * Used for afterpay directDebit and installment payment methods
 *
 * @package ShopwarePlugin\PaymentMethods\Components
 */
class AfterpayPaymentMethod extends GenericPaymentMethod
{

    protected $paymentName = "";

    /**
     * @inheritdoc
     */
    public function validate($paymentData)
    {
        $sErrorFlag = array();
        $fields = array();

        $container = Shopware()->Container();
        $shop = $container->get('shop');
        $pluginConfig = $container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        if ($pluginConfig['colo_afterpay_birthday_check']) {
            $fields[] = 'sBirthday';
        }
        if (in_array($this->paymentName, array("colo_afterpay_dd", "colo_afterpay_installment"))) {
            $fields[] = 'sSepaIban';
            if ($this->paymentName === "colo_afterpay_installment") {
                $fields[] = 'plan';
            }
        }
        if (!empty($paymentData['colo_afterpay_payment'])) {
            $paymentData = $paymentData['colo_afterpay_payment'][$this->paymentName];
        }
        foreach ($fields as $field) {
            $value = $paymentData[$field] ?: '';
            if ($field === "sBirthday") {
                if (empty($value['day']) || empty($value['month']) || empty($value['year']) || !checkdate($value['month'], $value['day'], $value['year'])) {
                    $sErrorFlag[$field]['day'] = true;
                    $sErrorFlag[$field]['month'] = true;
                    $sErrorFlag[$field]['year'] = true;
                } else {
                    $age = \DateTime::createFromFormat('Y-m-d', $value['year'] . "-" . $value['month'] . "-" . $value['day'])->diff(new \DateTime('tomorrow'))->y;
                    if ($age < 18) {
                        $sErrorFlag[$field]['day'] = true;
                        $sErrorFlag[$field]['month'] = true;
                        $sErrorFlag[$field]['year'] = true;

                        $sErrorMessages[] = Shopware()->Snippets()->getNamespace('frontend/colo_afterpay/index')->get('AgeRestricationError', '', true);
                    }
                }
                continue;
            } else {
                $value = trim($value);
            }

            if (empty($value)) {
                $sErrorFlag[$field] = true;
            }
        }

        if (count($sErrorFlag)) {
            if (count($sErrorFlag) === 1 && $sErrorFlag['sSepaIban'] && (int)$paymentData['sMaskedIban'] === 1) {
                return array();
            }
            if (empty($sErrorMessages)) {
                $sErrorMessages[] = Shopware()->Snippets()->getNamespace('frontend/account/internalMessages')
                    ->get('ErrorFillIn', 'Please fill in all red fields');
            }
            return array(
                "sErrorFlag" => $sErrorFlag,
                "sErrorMessages" => $sErrorMessages
            );
        } else {
            return array();
        }
    }

    /**
     * @inheritdoc
     */
    public function savePaymentData($userId, \Enlight_Controller_Request_Request $request)
    {
        $lastPayment = $this->getCurrentPaymentDataAsArray($userId);

        $paymentMean = Shopware()->Models()->getRepository('\Shopware\Models\Payment\Payment')->
        getActivePaymentsQuery(array('name' => $this->paymentName))->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        $paymentData = $request->getParams();
        if (in_array($this->paymentName, array("colo_afterpay_dd", "colo_afterpay_installment"))) {
            $iban = $this->removeAllWhitespaces($paymentData['colo_afterpay_payment'][$this->paymentName]['sSepaIban']);
            $maskedIban = (int)$paymentData['colo_afterpay_payment'][$this->paymentName]["sMaskedIban"];
            if ($this->paymentName === "colo_afterpay_installment") {
                $container = Shopware()->Container();
                $installmentPlan = trim($paymentData['colo_afterpay_payment'][$this->paymentName]['plan']);
                if (!empty($installmentPlan) && $container->initialized('session') && $container->has('session')) {
                    $container->get('session')->offsetSet('ColoAfterpayInstallmentPlan', $installmentPlan);
                }
            }

            $data = array();
            if (!(empty($iban) && $maskedIban === 1)) {
                $data['iban'] = $iban;
            }

            if (empty($lastPayment) || !isset($lastPayment['sSepaIban'])) {
                $date = new \DateTime();
                $data['created_at'] = $date->format('Y-m-d');
                $data['payment_mean_id'] = $paymentMean['id'];
                $data['user_id'] = $userId;
                Shopware()->Db()->insert("s_core_payment_data", $data);
            } else {
                $where = array(
                    'payment_mean_id = ?' => $paymentMean['id'],
                    'user_id = ?' => $userId
                );

                Shopware()->Db()->update("s_core_payment_data", $data, $where);
            }
        }
        if (!empty($paymentData['colo_afterpay_payment'][$this->paymentName]['sBirthday'])) {
            $birthday = $paymentData['colo_afterpay_payment'][$this->paymentName]['sBirthday'];
            if (!empty($birthday['day']) && !empty($birthday['month']) && !empty($birthday['year']) && checkdate($birthday['month'], $birthday['day'], $birthday['year'])) {
                $data = array(
                    'birthday' => $birthday['year'] . "-" . $birthday['month'] . "-" . $birthday['day']
                );
                $where = array(
                    'id = ?' => $userId
                );
                Shopware()->Db()->update("s_user", $data, $where);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getCurrentPaymentDataAsArray($userId)
    {
        $arrayData = array();
        if (empty($userId)) {
            return $arrayData;
        }
        $paymentData = Shopware()->Models()->getRepository('\Shopware\Models\Customer\PaymentData')
            ->getCurrentPaymentDataQueryBuilder($userId, $this->paymentName)->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        $container = Shopware()->Container();
        $shop = $container->get('shop');
        $pluginConfig = $container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        if ($pluginConfig['colo_afterpay_birthday_check']) {
            $arrayData['sBirthday'] = array();
            $user = Shopware()->Models()->getRepository('\Shopware\Models\Customer\Customer')->find($userId);
            $birthday = $user->getBirthday();
            if ($birthday instanceof \DateTime) {
                $arrayData['sBirthday'] = array(
                    'day' => $birthday->format("d"),
                    'month' => $birthday->format("m"),
                    'year' => $birthday->format("Y"),
                );
            }
        }
        if (isset($paymentData)) {
            $arrayData["sSepaIban"] = $paymentData['iban'];
            if ($this->paymentName === "colo_afterpay_installment") {
                $container = Shopware()->Container();
                if ($container->initialized('session') && $container->has('session')) {
                    $installmentPlan = $container->get('session')->offsetGet('ColoAfterpayInstallmentPlan');
                    $arrayData['plan'] = $installmentPlan;
                }
            }
        }
        return $arrayData;
    }

    /**
     * @inheritdoc
     */
    public function createPaymentInstance($orderId, $userId, $paymentId)
    {
        $orderAmount = Shopware()->Models()->createQueryBuilder()
            ->select('orders.invoiceAmount')
            ->from('Shopware\Models\Order\Order', 'orders')
            ->where('orders.id = ?1')
            ->setParameter(1, $orderId)
            ->getQuery()
            ->getSingleScalarResult();

        $addressData = Shopware()->Models()->getRepository('Shopware\Models\Customer\Billing')->
        getUserBillingQuery($userId)->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        $paymentData = $this->getCurrentPaymentDataAsArray($userId);

        $date = new \DateTime();
        $data = array(
            'payment_mean_id' => $paymentId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'firstname' => $addressData['firstName'],
            'lastname' => $addressData['lastName'],
            'address' => $addressData['street'],
            'zipcode' => $addressData['zipCode'],
            'city' => $addressData['city'],
            'iban' => $paymentData['sSepaIban'],
            'amount' => $orderAmount,
            'created_at' => $date->format('Y-m-d')
        );

        Shopware()->Db()->insert("s_core_payment_instance", $data);

        return true;
    }

    protected function setPaymentName($paymentName)
    {
        $this->paymentName = $paymentName;
        return $this;
    }

    protected function getPaymentName()
    {
        return $this->paymentName;
    }

    private function removeAllWhitespaces($string)
    {
        return preg_replace('/\s+/', '', $string);
    }

}
