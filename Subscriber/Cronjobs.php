<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Cronjobs implements SubscriberInterface
{

    /**
     * @var string
     */
    private $viewDir;

    /**
     * @var ContainerInterface
     */
    private $container;

    public static function getSubscribedEvents()
    {
        return array(
            'Shopware_CronJob_ColoAfterpayCapture' => 'onColoAfterpayCapture'
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
     * Captures payments
     *
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function onColoAfterpayCapture(\Shopware_Components_Cron_CronJob $job)
    {
        $shopRepo = $this->container->get('models')->getRepository('Shopware\Models\Shop\Shop');
        $shop = $shopRepo->getActiveDefault();
        $pluginConfig = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        if (!$pluginConfig['colo_afterpay_auto_capture']) {
            return 'Auto capture is disabled from plugin configs.';
        }
        $logger = $this->container->get('colo_afterpay.services.logger_service');
        $logger->log('Capture payment cronjob starting', 'info');

        $service = $this->container->get('colo_afterpay.services.afterpay_service');
        $ordernumbers = $service->getCapturableOrders();
        $logger->log('In total ' . count($ordernumbers) . ' orders should be captured.', 'info');
        if (!empty($ordernumbers)) {
            $failures = $successfuls = 0;
            foreach ($ordernumbers as $ordernumber) {
                try {
                    $logger->log('Capturing order ' . $ordernumber, 'info');
                    $response = $service->capturePayment($ordernumber);
                    if ($response['success']) {
                        $logger->log($ordernumber, 'success');
                        $successfuls++;
                    } else {
                        $logger->log($ordernumber, 'error');
                        $failures++;
                    }
                } catch (\Exception $ex) {
                    $failures++;
                    $logger->log($ex->getMessage(), "error");
                    continue;
                }
            }
            $logger->log($successfuls . ' orders have been captured.', 'info');
            $logger->log($failures . ' orders have not been captured.', 'info');
        }
        $logger->log('Capture payment cronjob finishing', 'info');
        return true;
    }

}
