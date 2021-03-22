<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Less Subscriber class
 */
class Less implements SubscriberInterface
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
            'Theme_Compiler_Collect_Plugin_Less' => 'addLessFiles',
            'Theme_Compiler_Collect_Less_Definitions_FilterResult' => 'filterLessFiles'
        );
    }

    /**
     * @param string $viewDir
     * @param ContainerInterface $container
     */
    public function __construct($viewDir, ContainerInterface $container)
    {
        $this->viewDir = $viewDir;
        $this->container = $container;
    }

    /**
     * Provide the needed less files
     *
     * @param \Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function addLessFiles(\Enlight_Event_EventArgs $args)
    {
        $less = new \Shopware\Components\Theme\LessDefinition(
        //configuration
            array(),
            //less files to compile
            array(
                $this->viewDir . '/../frontend/less/all.less'
            ),
            //import directory
            $this->viewDir . '/../frontend/less/'
        );
        return new ArrayCollection(array($less));
    }

    /**
     * If shopware version is >= 5.2.13, less files are added automatically
     * So we need to remove our file
     *
     * @param \Enlight_Event_EventArgs $args
     * @return array
     */
    public function filterLessFiles(\Enlight_Event_EventArgs $args)
    {
        $lessFiles = $args->getReturn();
        $newLessFiles = array();
        $swVersion = Shopware()->Config()->version;
        if (version_compare($swVersion, "5.2.13", ">=")) {
            foreach ($lessFiles as $lessFile) {
                if ($lessFile === $this->viewDir . '/../frontend/less/all.less') {
                    continue;
                }
                $newLessFiles[] = $lessFile;
            }
        }
        return $newLessFiles;
    }

}
