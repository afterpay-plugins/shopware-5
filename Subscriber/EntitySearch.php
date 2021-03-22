<?php

namespace AfterPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntitySearch implements SubscriberInterface
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PreDispatch_Backend_EntitySearch' => 'onPreDispatchEntitySearch'
        );
    }

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Filters payment statuses from the entity
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPreDispatchEntitySearch(\Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $request = $controller->Request();
        $model = $request->getParam('model');

        $modelParts = explode("|", $model);
        if (count($modelParts) === 2) {
            $request->setParam('model', $modelParts[0]);
            $filterOptions = explode(":", $modelParts[1]);
            if (count($filterOptions) !== 2) {
                return;
            }
            $filters = $request->getParam('filter', array());
            $filters[] = array('property' => $filterOptions[0], 'value' => $filterOptions[1], 'expression' => '=');
            $request->setParam('filter', $filters);
        }
    }

}
