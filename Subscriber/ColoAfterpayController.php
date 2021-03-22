<?php

namespace AfterPay\Subscriber;

use \Enlight\Event\SubscriberInterface;
use \Enlight_Template_Manager;

class ColoAfterpayController implements SubscriberInterface
{

    /**
     * @var string
     */
    private $pluginDir;

    /**
     * @var string
     */
    private $viewDir;

    /**
     * @var Enlight_Template_Manager
     */
    private $template;

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_ColoAfterpay' => 'onColoAfterpayFrontendController',
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_ColoAfterpayCheckout' => 'onColoAfterpayCheckoutFrontendController',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_ColoAfterpay' => 'onColoAfterpayBackendController',
        ];
    }

    /**
     * @param $pluginDir
     * @param $viewDir
     * @param Enlight_Template_Manager $template
     */
    public function __construct(
        $pluginDir, $viewDir, \Enlight_Template_Manager $template
    )
    {
        $this->pluginDir = $pluginDir;
        $this->viewDir = $viewDir;
        $this->template = $template;
    }

    public function onColoAfterpayFrontendController()
    {
        $this->template->addTemplateDir($this->viewDir);

        return $this->pluginDir . '/Controllers/Frontend/ColoAfterpay.php';
    }

    public function onColoAfterpayCheckoutFrontendController()
    {
        $this->template->addTemplateDir($this->viewDir);

        return $this->pluginDir . '/Controllers/Frontend/ColoAfterpayCheckout.php';
    }

    public function onColoAfterpayBackendController()
    {
        $this->template->addTemplateDir($this->viewDir);

        return $this->pluginDir . '/Controllers/Backend/ColoAfterpay.php';
    }

}
