<?php

namespace AfterPay\Services;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger as BaseLogger;
use Shopware\Components\Logger;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoggerService
{

    /**
     * @var ContainerInterface $container
     */
    private $container;

    /**
     * @var Logger $pluginLogger
     */
    private $pluginLogger;

    /**
     * @var \Shopware_Components_Config $config
     */
    private $config;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(
        ContainerInterface $container,
        Logger $pluginLogger
    )
    {
        $this->container = $container;
        $this->pluginLogger = $pluginLogger;
    }

    /**
     * Logs message
     *
     * @param string $message
     * @param string $mode
     * @return boolean
     */
    public function log($message, $mode = 'info')
    {
        if (empty($this->config)) {
            if ($this->container->initialized('shop') && $this->container->has('shop')) {
                $shop = $this->container->get('shop');
            } else {
                $shopRepo = $this->container->get('models')->getRepository(Shop::class);
                $shop = $shopRepo->getActiveDefault();
            }
            $this->config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('AfterPay', $shop);
        }
        if ($this->config['colo_afterpay_log'] === 'none') {
            return true;
        }
        $handlerLevels = [];
        /** @var AbstractHandler[] $handlers */
        $handlers = $this->pluginLogger->getHandlers();
        if ($this->config['colo_afterpay_log'] === 'all') {
            foreach ($handlers as $index => $handler) {
                $handlerLevels[$index] = $handler->getLevel();
                $handler->setLevel('info');
            }
        }
        try {
            switch ($mode) {
                case "success":
                    if ($this->config['colo_afterpay_log'] === 'all') {
                        $this->pluginLogger->info($message);
                    }
                    break;
                case "info":
                    if ($this->config['colo_afterpay_log'] === 'all') {
                        $this->pluginLogger->info($message);
                    }
                    break;
                case "error":
                    $this->pluginLogger->error($message);
                    break;
                default:
                    $this->pluginLogger->warn($message);
                    break;
            }
        } catch (\Exception $ex) {
            $this->pluginLogger->error($ex->getMessage());
            return false;
        }
        if ($this->config['colo_afterpay_log'] === 'all') {
            foreach ($handlers as $index => $handler) {
                $handler->setLevel($handlerLevels[$index]);
            }
        }
        return true;
    }

}
