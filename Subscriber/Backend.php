<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use AfterPay\Components\Constants;

class Backend implements SubscriberInterface
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
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onPostDispatchOrder'
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
     * Adds capture payment icon to the order list
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchOrder(\Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();
        $actionName = strtolower($request->getActionName());

        if ($actionName === "load") {
            $view->extendsTemplate('backend/colo_afterpay/order/controller/list.js');
            $view->extendsTemplate('backend/colo_afterpay/order/model/order.js');
            $view->extendsTemplate('backend/colo_afterpay/order/view/list/list.js');
            $view->extendsTemplate('backend/colo_afterpay/order/view/detail/window.js');
        } else if ($actionName === "getlist") {
            $orders = $view->getAssign('data');
            if (empty($orders)) {
                return;
            }
            $pluginConfig = array();
            $shopIds = array_unique(array_column($orders, "shopId"));
            $shopRepo = $this->container->get('models')->getRepository('Shopware\Models\Shop\Shop');
            foreach ($shopIds as $shopId) {
                $shop = $shopRepo->find($shopId);
                if (empty($shop)) {
                    $shop = $shopRepo->getActiveDefault();
                    $shopId = $shop->getId();
                }
                if (!isset($pluginConfig[$shopId])) {
                    $pluginConfig[$shopId] = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
                }
            }
            $orderIds = array_column($orders, "id");
            $sql = "SELECT * FROM `s_order_attributes` WHERE `orderID` IN (?" . str_repeat(",?", count($orderIds) - 1) . ")";
            $results = Shopware()->Db()->fetchAll($sql, $orderIds);
            $attributes = array();
            foreach ($results as $attribute) {
                $attribute['colo_captured'] = (int)$attribute['colo_captured'] === 1 ? true : false;
                $attributes[$attribute['orderID']] = $attribute;
            }
            foreach ($orders as $index => $order) {
                $orders[$index]['colo_capture_status'] = $pluginConfig[$order['shopId']]['colo_afterpay_capture_status'];
                $orders[$index]['colo_captured'] = $attributes[$order['id']]['colo_captured'];
                $orders[$index]['colo_transaction_mode'] = $attributes[$order['id']]['colo_transaction_mode'];
                $orders[$index]['colo_afterpay_payments'] = Constants::PAYMENT_METHODS;
            }
            $view->assign(array('data' => $orders));
        }
    }

}
