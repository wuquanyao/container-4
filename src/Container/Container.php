<?php

namespace Njasm\Container;

use Njasm\Container\Config\ConfigLoader;
use Njasm\Container\Exception\NotFoundException;
use Njasm\Container\Definition\DefinitionMap;
use Njasm\Container\Definition\Service\DefinitionService;
use Njasm\Container\Definition\Service\Request;

class Container implements ServicesProviderInterface
{
    /**
     * @var \Njasm\Container\Definition\DefinitionMap
     */
    protected $definitionMap;

    /**
     * @var array
     */
    protected $providers;

    /**
     * @var array
     */
    protected $registry;

    /**
     * @var array
     */
    protected $singletons;

    /**
     * @var \Njasm\Container\Definition\Service\DefinitionService
     */
    protected $service;

    public function __construct(ConfigLoader $configLoader = null)
    {
        $this->initialize();
        if (!is_null($configLoader)) {
            $configLoader->setConfig($this);
        }
    }

    /**
     * Initializes the Container.
     * 
     * @return void
     */
    protected function initialize()
    {
        $this->providers = array();
        $this->definitionMap = new DefinitionMap();
        $this->registry = array();
        $this->singletons = array();
        
        $this->service = new DefinitionService();
        
        // register Container
        $this->set('Njasm\Container\Container', $this);
        $this->alias('Container', 'Njasm\Container\Container');
    }
    
    /**
     * Check if service is registered.
     * 
     * @param   string      $key
     * @return  boolean
     */    
    public function has($key)
    {
        return $this->service->has($this->getRequestObject($key));
    }
    
    /**
     * Register a new service in the container.
     * 
     * @param   string      $key
     * @param   mixed       $concrete
     * @return  Container
     */
    public function set($key, $concrete)
    {
        $definition = $this->service->assemble($key, $concrete);
        $this->definitionMap->add($definition);
        
        $definitionType = $definition->getType();
        
        if (
            $definitionType === Definition\DefinitionType::OBJECT 
            || $definitionType === Definition\DefinitionType::PRIMITIVE
        ) {
            $this->registerSingleton($key);
        }
        
        return $this;
    }

    /**
     * Register an alias to a service key.
     * 
     * @param   string      $alias
     * @param   string      $key
     * @return  Container
     */    
    public function alias($alias, $key)
    {
        $definition = $this->service->assembleAliasDefinition($alias, $key);
        $this->definitionMap->add($definition);
        
        return $this;
    }
    
    /**
     * Registers service as a singleton instance in the container.
     * 
     * @param   string      $key
     * @param   mixed       $concrete
     * @return  Container
     */
    public function singleton($key, $concrete)
    {
        $this->set($key, $concrete);
        
        return $this->registerSingleton($key);
    }
    
    /**
     * Register a service key as singleton.
     * 
     * @param   string      $key
     * @return  Container
     */
    protected function registerSingleton($key)
    {
        $this->singletons[$key] = true;
        
        return $this;
    }
    
    /**
     * Registers another services provider container.
     * 
     * @param   ServicesProviderInterface   $provider
     * @return  Container
     */
    public function provider(ServicesProviderInterface $provider)
    {
        $this->providers[] = $provider;

        return $this;        
    }   

    /**
     * Returns the service.
     * 
     * @param   string  $key
     * @return  mixed
     * 
     * @throws  NotFoundException
     */
    public function get($key)
    {
        if (isset($this->registry[$key])) {
            return $this->registry[$key];
        }
        
        $request = $this->getRequestObject($key);
        $returnValue = $this->service->build($request);

        return $this->isSingleton($key) ? $this->registry[$key] = $returnValue : $returnValue;        
    }
    
    /**
     * Removes a service from the container. 
     * This will NOT remove services from other nested providers.
     * 
     * @param   string  $key
     * @return  boolean
     */
    public function remove($key)
    {
        if(isset($this->definitionMap[$key])) {
            unset($this->definitionMap[$key]);
            unset($this->registry[$key]);
            unset($this->singletons[$key]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Reset container settings.
     * 
     * @return  void
     */ 
    public function reset()
    {
        $this->initialize();
    }
    
    /**
     * Check if service is registered as a singleton.
     * 
     * @param   string  $key
     * @return  boolean
     */
    protected function isSingleton($key)
    {
        return isset($this->singletons[$key]);
    }
    
    /**
     * Build a new Request value object.
     * 
     * @param   string      $key
     * @return  Request
     */
    protected function getRequestObject($key)
    {
        return new Request($key, $this->definitionMap, $this->providers, $this);
    }
}
