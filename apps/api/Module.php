<?php namespace Apps\Api;

use Phalcon\Mvc\View;
use Apps\Commons\AbstractModule,
    Phalcon\DiInterface,
    Phalcon\Mvc\Dispatcher;

use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;

/**
 * Created by Artdevue.
 * User: artdevue - Module.php
 * Date: 25.02.17
 * Time: 16:41
 * Project: PhalconScelet
 *
 * Class Module  */
class Module extends AbstractModule
{
    function __construct()
    {
        $this->module    = 'Api';
        $this->namespace = __NAMESPACE__;
        $this->path      = __DIR__;
    }

    /**
     * Registers the module-only services
     */
    public function registerModuleServices(DiInterface $di)
    {
        parent::registerModuleServices($di);
    }

    /**
     * Registers the module-only services
     *
     * @param \Phalcon\DiInterface $di
     */
    public function registerServices(DiInterface $di = null)
    {
        $dispatcher = new Dispatcher();
        $dispatcher->setDI($di);
        $dispatcher->setDefaultNamespace('Apps\Api\Controllers');

        $eventsManager = new \Phalcon\Events\Manager();

        $eventsManager->attach('dispatch:beforeException', function ($event, $dispatcher, $exception) use (&$di) {

            $dispatcher->setParam('exception', $exception);
            $dispatcher->forward(
                [
                    'module'     => 'api',
                    'controller' => 'base',
                    'action'     => 'exception',
                    'error'      => $exception
                ]
            );

            return false;
        });

        $eventsManager->attach('dispatch:beforeNotFoundAction', function ($event, $dispatcher) use (&$di) {

            $dispatcher->forward(
                [
                    'module'     => 'api',
                    'controller' => 'base',
                    'action'     => 'route404'
                ]
            );

            return false;
        });

        // Attach a listener for type "dispatch:afterDispatch"
        $eventsManager->attach("dispatch:afterDispatch", function ($event, $dispatcher) use (&$di) {

            $content  = $dispatcher->getReturnedValue();
            $response = $di->get('response');
            $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setContentType('application/json', 'utf-8');
            $response->setContent(json_encode($content));

            $dispatcher->setReturnedValue($response);
        }
        );

        $dispatcher->setEventsManager($eventsManager);

        $di->set('dispatcher', $dispatcher);

        // This component makes use of adapters to store the logged messages.
        $di->setShared('logger', function () {
            return new FileAdapter(PROJECT_PATH . "storage/logs/api.log");
        });

    }

    /**
     * Registers module View Service
     */
    protected function registerViewService(DiInterface $di)
    {
        //parent::registerViewService($di);

        $this->di['view'] = function () {

            $view = new View();
            // Disable the view to avoid rendering
            $view->disable();

            return $view;
        };

    }
}