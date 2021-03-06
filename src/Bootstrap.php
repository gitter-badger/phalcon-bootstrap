<?php
namespace GetSky\Phalcon\Bootstrap;

use GetSky\Phalcon\AutoloadServices\Registrant;
use GetSky\Phalcon\ConfigLoader\ConfigLoader;
use Phalcon\Config;
use Phalcon\DI;
use Phalcon\Loader;
use Phalcon\Mvc\Application;

/**
 * Class Bootstrap
 * @package GetSky\Phalcon\Bootstrap
 */
class Bootstrap extends Application
{

    /**
     * Name for apc cache
     * @var string
     */
    const CONFIG = 'pherlin_config';

    /**
     * The path to the application configuration file
     * @var string
     */
    private $pathConfig = '../app/config/config_%environment%.yml';
    /**
     * The variable indicates the application environment
     * @var string
     */
    private $environment = 'dev';
    /**
     * Application configuration
     * @var Config|null
     */
    private $config;
    /**
     * The loader of namespace
     * @var Loader
     */
    private $loader;
    /**
     * Use or not use the cache for application settings and configuration of services
     * @var bool
     */
    private $cacheable = false;

    /**
     * @param DI $di
     * @param null|string $environment
     */
    public function __construct(DI $di, $environment = null)
    {
        parent::__construct($di);
        $this->loader = new Loader();
        if ($environment !== null) {
            $this->environment = $environment;
        }
        if (extension_loaded('apc') || extension_loaded('apcu')) {
            $this->cacheable = true;
        }
    }

    /**
     * Running the application
     * @param bool $hide
     * @return string
     */
    public function run($hide = false)
    {
        $this->boot();
        $this->initNamespace();
        $this->initModules();
        $this->initServices();
        if ($hide === false) {
            return $this->handle()->getContent();
        } else {
            return 'true';
        }
    }

    /**
     * Loads the settings option and a list of services for the application
     */
    protected function boot()
    {
        $configLoader = new ConfigLoader($this->environment);
        $this->di->setShared('config-loader', $configLoader);

        $cache = null;
        if ($this->cacheable === true) {
            $cache = apc_fetch(Bootstrap::CONFIG . $this->environment);
        }

        if ($cache === false || $cache === null) {
            $this->config = $configLoader->create($this->pathConfig);

            $this->config->merge(
                new Config(['environment' => $this->environment])
            );
            if ($cache === false) {
                apc_add(Bootstrap::CONFIG . $this->environment, $this->config);
            }
        } else {
            $this->config = $cache;
        }
    }

    /**
     * The method sets a new path configuration file
     * @param string $pathConfig
     */
    public function setPathConfig($pathConfig)
    {
        $this->pathConfig = $pathConfig;
    }

    /**
     * The method get a path configuration file
     * @return string
     */
    public function getPathConfig()
    {
        return $this->pathConfig;
    }

    /**
     * @return boolean
     */
    public function isCacheable()
    {
        return $this->cacheable;
    }

    /**
     * @param boolean $cacheable
     */
    public function setCacheable($cacheable)
    {
        $this->cacheable = $cacheable;
    }


    /**
     * Initializing modules
     */
    protected function initModules()
    {
        $modules = null;

        if ($this->config !== null) {
            $modules = $this->config->get('modules', null);
        }

        if ($modules !== null) {
            $pathFile = $this->config->get('bootstrap')->get('path');
            $moduleName = $this->config->get('bootstrap')->get('module');
            $arrayModules = [];

            /**
             * @var $module Config
             */
            foreach ($modules as $name => $module) {
                $namespace = $module->get('namespace');
                $path = $pathFile . str_replace('\\', '/', $namespace);
                $arrayModules[$name] = [
                    'className' => $namespace . '\\' . substr($moduleName, 0, -4),
                    'path' => $path . '/' . $moduleName
                ];
            }

            $this->registerModules($arrayModules);
        }
    }

    /**
     * Initializing namespace of application
     */
    protected function initNamespace()
    {
        $namespaces = $this->config->get('namespaces', null);

        if ($namespaces !== null) {
            foreach ($namespaces as $namespace => $path) {
                $this->loader->registerNamespaces([$namespace => $path], true);
            }
            $this->loader->register();
            $this->di->set('loader', $this->loader);
        }
    }

    /**
     * Initializing services in dependency injection
     */
    protected function initServices()
    {
        $dependencies = $this->config->get('dependencies', null);
        $this->getDI()->setShared('registrant', new Registrant($dependencies));
        $this->getDI()->setShared('config', $this->config);
        $this->getDI()->get('registrant')->registration();
    }

    /**
     * @return null|Loader
     */
    public function getLoader()
    {
        return $this->loader;
    }
}
