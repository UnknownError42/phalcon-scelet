<?php
/**
 * Created by Artdevue
 * User: artdevue - services.php
 * Date: 25.02.17
 * Time: 15:39
 * Project: phalcon-blank
 */

/**
 * Services are globally registered in this file
 */

use Phalcon\Cache\Backend\File;
use Phalcon\Cache\Frontend\Output;
use Phalcon\Mvc\Router,
    Phalcon\Mvc\Router\Group as RouterGroup,
    Phalcon\Mvc\Url as UrlResolver,
    Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\View,
    Phalcon\Security,
    Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    Phalcon\Flash\Direct as Flash,
    Phalcon\Crypt,
    Phalcon\Mvc\View\Engine\Volt,
    Phalcon\Mvc\Model\Metadata\Files as MetaDataAdapter,
    Phalcon\Session\Adapter\Files as SessionAdapter;
use Phalcon\Mvc\Router\Annotations as RouterAnnotations;

/**
 * The FactoryDefault Dependency Injector automatically register
 * the right services providing a full stack framework
 */
$di = new FactoryDefault();

/**
 * Register Translation Service
 */
if (!$di->has('trans'))
{
    $di->setShared('trans', function ()
    {
        $trans = new \Library\Trans();

        return $trans->get();
    });
}

/**
 * Registering a router
 */
$di['router'] = function () use ($config, $di)
{

    //$router = new Router(false);
    // Use the annotations router. We're passing false as we don't want the router to add its default patterns
    $router = new RouterAnnotations(false);

    $router->setUriSource(Router::URI_SOURCE_SERVER_REQUEST_URI);
    $router->removeExtraSlashes(false);

    if (!$config->debug)
    {
        $router->setDefaultModule($config->default_module);
        $router->setDefaults(['controller' => 'index', 'action' => 'route404']);
    }

    // Get explode uri
    $url_array = explode("/", $_SERVER['REQUEST_URI']);
    $module    = $config->default_module;

    $prefix_lang         = '';
    $config->lang_active = $config->default_lang;

    // If multilanguage is allowed
    if ($config->multilang)
    {
        $langkey =
            strlen($_SERVER['REQUEST_URI']) == 2 ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 1, 2);

        if (in_array($langkey, array_keys($config->languages->toArray())) && $langkey != $config->default_lang)
        {
            $prefix_lang         = '/' . $langkey;
            $config->lang_active = $langkey;
        }
    }

    // Connecting routes for modules
    foreach ($config->modules as $key => $modul)
    {
        if (file_exists($modul->dir . 'config/routes.php'))
        {
            // Create a group with a backend module and controller
            $route = new RouterGroup([
                "module" => $key
            ]);

            // All the routes start with $prefix_router
            if (!empty($modul->prefix_router))
            {
                $route->setPrefix($prefix_lang . "/" . $modul->prefix_router);
            } else
            {
                if (strlen($prefix_lang) > 1)
                {
                    $route->setPrefix($prefix_lang);
                }

            }

            // Hostname restriction
            if (!empty($modul->host_name))
            {
                $route->setHostName($modul->host_name);
            }

            $home_slesh = '';

            if ($key == $config->default_module && $_SERVER['REQUEST_URI'] == '/')
            {
                $home_slesh = '/';
            }

            require $modul->dir . 'config/routes.php';

            // Add the group to the router
            $router->mount($route);
        }

        if (count($url_array) >= 2 && $url_array[1] == $modul->prefix_router)
        {
            $module = $key;
        }
    }

    $router->notFound([
        "module"     => $module,
        "controller" => "base",
        "action"     => "route404"
    ]);

    return $router;
};

/**
 * Register the global configuration as config
 */
$di->set('config', $config);

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->set('url', function () use ($config)
{
    $url = new UrlResolver();
    $url->setBaseUri($config->base_uri);

    return $url;
});

$di->set('eventsManager', 'Phalcon\Events\Manager', true);
$di->set('assets', 'Phalcon\Assets\Manager', true);

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di['db'] = function () use ($config)
{
    return new DbAdapter([
        'host'     => $config->database->host,
        'port'     => $config->database->port,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname'   => $config->database->dbname,
        'charset'  => $config->database->charset,
        'options'  => [
            PDO::MYSQL_ATTR_INIT_COMMAND => sprintf("SET NAMES '%s'", $config->database->charset),
            PDO::ATTR_CASE               => PDO::CASE_LOWER,
        ]
    ]);
};

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di['modelsMetadata'] = function () use ($config)
{
    return new MetaDataAdapter([
        'metaDataDir' => $config->application->cacheDir . 'metaData/'
    ]);
};

/**
 * Crypt service
 */
$di->set('crypt', function () use ($config)
{
    $crypt = new Crypt();
    $crypt->setKey($config->application->cryptSalt);

    return $crypt;
});

/**
 * Start the session the first time some component request the session service
 */
$di->set('session', function () use ($config)
{
    $session = new SessionAdapter([
        'uniqueId' => $config->prefix_session
    ]);
    $session->start();

    return $session;
});

/**
 * Flash service with custom CSS classes
 */
$di->set('flash', function ()
{
    return new Flash([
        'error'   => 'errorHandler alert alert-danger notification-error',
        'success' => 'errorHandler alert alert-success',
        'notice'  => 'errorHandler alert alert-info',
        'warning' => 'errorHandler alert alert-warning',
    ]);
});

/**
 * This component aids the developer in common security tasks such as password
 * hashing and Cross-Site Request Forgery protection (CSRF).
 */
$di->set('security', function ()
{
    $security = new Security();

    //Set the password hashing factor to 12 rounds
    $security->setWorkFactor(12);

    return $security;
}, true);

/**
 * Registering a Auth component
 */
$di->setShared('auth', 'Library\Auth\Auth');
$di->setShared('slug', 'Library\Slug\Slug');

/**
 * Register View
 */
$di['view'] = function () use ($config, $di)
{
    $module_name = $di->get('router')->getModuleName();

    $view = new View();
    $view->setViewsDir(PROJECT_PATH . 'apps/' . $module_name . '/views/');

    $view->registerEngines([
        '.volt'  => function ($view, $di) use ($config, $module_name)
        {

            $volt = new Volt($view, $di);

            $volt->setOptions([
                'compiledPath'      => PROJECT_PATH . 'cache/volt/' . $module_name . '/',
                'compiledSeparator' => '_'
            ]);

            return $volt;
        },
        '.phtml' => 'Phalcon\Mvc\View\Engine\Php',
        '.php'   => 'Phalcon\Mvc\View\Engine\Php'
    ]);

    return $view;
};

/**
 * View cache
 */
$di['viewCache'] = function ()
{

    //Cache data for one day by default
    $frontCache = new Output([
        "lifetime" => 2592000
    ]);

    /*return new \Phalcon\Cache\Backend\Apc($frontCache, array(
        "prefix" => "cache-"
    ));*/

    return new File($frontCache, [
        "cacheDir" => PROJECT_PATH . "cache/views/",
        "prefix"   => "cache-"
    ]);
};

/**
 * Register Helpers Service
 */
if (!$di->has('helpers'))
{
    $di->setShared('helpers', 'Library\Helpers');
}

/**
 * Register Str Service
 */
if (!$di->has('str'))
{
    $di->setShared('str', 'Library\Str');
}

// Models cache service Register
$di->set('modelsCache', function ()
{
    // By default, the cache data is stored one day
    $frontCache = new \Phalcon\Cache\Frontend\Data([
        "lifetime" => 86400
    ]);

    return new File($frontCache, [
        "cacheDir" => PROJECT_PATH . "cache/sitemap/",
        "prefix"   => "cache-"
    ]);
});
