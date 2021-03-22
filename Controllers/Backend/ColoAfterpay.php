<?php

use Shopware\Models\Shop\Shop;

class Shopware_Controllers_Backend_ColoAfterpay extends Shopware_Controllers_Backend_ExtJs
{

    public function captureAction()
    {
        try {
            $container = Shopware()->Container();
            $ordernumber = $this->Request()->getParam('ordernumber');
            $service = $container->get('colo_afterpay.services.afterpay_service');
            $response = $service->capturePayment($ordernumber);
            $this->View()->assign(array('success' => $response['success']));
        } catch (\Exception $e) {
            $this->View()->assign(array('success' => false, 'errorMsg' => $e->getMessage()));
        }
    }

    public function transactionsAction()
    {
        $start = (int)$this->Request()->getParam('start', 0);
        $limit = (int)$this->Request()->getParam('limit', 20);
        $orderId = $this->Request()->getParam('orderId', null);
        if (empty($orderId)) {
            return $this->View()->assign(array('success' => false, 'data' => array(), 'total' => 0));
        }
        $sql = "SELECT DISTINCT SQL_CALC_FOUND_ROWS * FROM `s_plugin_afterpay_transactions` WHERE `order_id`=? LIMIT $start, $limit;";
        $data = Shopware()->Db()->fetchAll($sql, array($orderId));

        $sql = "SELECT FOUND_ROWS() AS `count`;";
        $count = Shopware()->Db()->fetchOne($sql);

        $this->View()->assign(array('success' => true, 'data' => $data, 'total' => $count));
    }

    public function refundAction()
    {
        $ordernumber = $this->Request()->getParam('ordernumber', null);
        $amount = (float)$this->Request()->getParam('amount', 0);
        $creditNoteNumber = $this->Request()->getParam('creditNoteNumber', '');
        if ($amount <= 0 || empty($ordernumber)) {
            return $this->View()->assign(array('success' => false));
        }
        $container = Shopware()->Container();
        $service = $container->get('colo_afterpay.services.afterpay_service');
        $response = $service->refundPayment($ordernumber, $amount, $creditNoteNumber);
        $this->View()->assign(array('success' => $response['success']));
    }

    public function testapiAction()
    {
        $container = Shopware()->Container();
        $service = $container->get('colo_afterpay.services.afterpay_service');
        $snippetNamespace = $container->get('snippets')->getNamespace('backend/colo_afterpay/index');
        $stringCompiler = new \Shopware_Components_StringCompiler(
            $container->get('Template')
        );
        $success = true;

        try {
            $values = $this->Request()->getParam('values', []);
            if (empty($values)) {
                /** @var Shop $shop */
                $shop = Shopware()->Models()->getRepository(Shop::class)->getActiveDefault();
                $shopId = $shop->getId();
            } else {
                $shopId = array_shift(array_keys($values));
            }
            $versions = $service->getApiVersion($shopId);
            $versionsCounter = 1;
            $message = "";
            $error = false;
            foreach ($versions as $country => $modes) {
                $index = 1;
                if ($country !== 'all') {
                    $message .= "<br/><strong>" . $country . "</strong><br/>";
                }
                foreach ($modes as $mode => $version) {
                    if (is_null($version)) {
                        $error = true;
                    }
                    $value = $snippetNamespace->get('AfterpayTestApiMessage' . $index++);
                    $value = $stringCompiler->compileString($value, [
                        'status' => !is_null($version)
                    ]);
                    $message .= $value;
                    if ($index < count($modes) + 1) {
                        $message .= "<br/>";
                    }
                }
                if ($versionsCounter < count($versions)) {
                    $message .= "<br/>";
                }
                $versionsCounter++;
            }
            if ($error) {
                $message .= "<br/><br/>" . $snippetNamespace->get('AfterpayTestApiMessageSeeMore');
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
            $message .= "<br/><br/>" . $snippetNamespace->get('AfterpayTestApiMessageSeeMore');
        }
        $this->View()->assign(['success' => $success, 'message' => $message]);
    }
}
