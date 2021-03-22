<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Common\Collections\ArrayCollection;

class Javascript implements SubscriberInterface
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
     * Subscribed events
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Theme_Compiler_Collect_Plugin_Javascript' => 'addJsFiles',
            'Theme_Compiler_Collect_Javascript_Files_FilterResult' => 'filterJsFiles'
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
     * Provide the file collection for js files
     *
     * @param \Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function addJsFiles(\Enlight_Event_EventArgs $args)
    {
        $jsFiles = array(
            $this->viewDir . '/../frontend/js/script.js'
        );
        return new ArrayCollection($jsFiles);
    }

    /**
     * If shopware version is >= 5.2.13, js files are added automatically
     * So we need to remove our file
     *
     * @param \Enlight_Event_EventArgs $args
     * @return array
     */
    public function filterJsFiles(\Enlight_Event_EventArgs $args)
    {
        $jsFiles = $args->getReturn();
        $newJsFiles = array();
        $swVersion = Shopware()->Config()->version;
        if (version_compare($swVersion, "5.2.13", ">=")) {
            foreach ($jsFiles as $jsFile) {
                if ($jsFile === $this->viewDir . '/../frontend/js/script.js') {
                    continue;
                }
                $newJsFiles[] = $jsFile;
            }
        }
        return $newJsFiles;
    }

}
