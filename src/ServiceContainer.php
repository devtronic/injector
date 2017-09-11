<?php
/**
 * This file is part of the Devtronic Injector package.
 *
 * Copyright 2017 by Julian Finkler <julian@developer-heaven.de>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Devtronic\Injector;

use Devtronic\Injector\Exception\ParameterNotDefinedException;
use Devtronic\Injector\Exception\ServiceNotFoundException;

/**
 * A Simple Service Container
 *
 * You can register the services with the registerService-Method
 */
class ServiceContainer
{
    /**
     * Holds the available services
     * @var array
     */
    protected $services = [];

    /**
     * Holds the loaded services in memory
     * @var array
     */
    protected $loadedServices = [];

    /**
     * Holds the parameters
     * @var array
     */
    protected $parameters = [];

    /**
     * Register a new service in the container
     *
     * @param string $name The name of the service
     * @param callable $service The service callable
     * @param array $arguments The arguments to create the service
     *
     * @throws \LogicException If a service with the $name already exists
     */
    public function registerService($name, $service, $arguments = [])
    {
        if (isset($this->services[$name])) {
            throw new \LogicException("A service with the name {$name} already exist");
        }

        $this->services[$name] = [
            'service' => $service,
            'arguments' => $arguments
        ];
    }

    /**
     * Unregister a service from the container
     * @param string $name The name of the service
     *
     * @throws ServiceNotFoundException If the service is not registered
     * @throws \LogicException If the service is already loaded
     */
    public function unregisterService($name)
    {
        if (!isset($this->services[$name])) {
            throw new ServiceNotFoundException("A service with the name {$name} does not exist");
        } elseif (isset($this->loadedServices[$name])) {
            throw new \LogicException("The service {$name} can not be unregistered because its already loaded");
        }

        unset($this->services[$name]);
    }

    /**
     * Loads a service and returns the result
     * Dependencies are also loaded
     *
     * @param string $name The name of the Service
     * @return mixed The load result
     *
     * @throws ServiceNotFoundException If the service is not registered
     * @throws \Exception If the service class is not found
     * @throws \LogicException If the service is no instance of string or callable
     * @throws \InvalidArgumentException If the dependency count does not match the argument count of the service
     * @throws ParameterNotDefinedException If a injected parameter is not defined
     */
    public function loadService($name)
    {
        if (!isset($this->services[$name])) {
            throw new ServiceNotFoundException("A service with the name {$name} does not exist");
        }

        if (isset($this->loadedServices[$name])) {
            return $this->loadedServices[$name];
        }

        $service = $this->services[$name]['service'];
        $reflection = null;
        if (is_string($service) && class_exists($service)) {
            $reflectionClass = new \ReflectionClass($service);
            $reflection = $reflectionClass->getConstructor();
            if ($reflection === null) {
                $reflection = new \ReflectionFunction(function () {
                });
            }
        } elseif (is_callable($service)) {
            $reflection = new \ReflectionFunction($service);
        } elseif (is_string($service) && !class_exists($service)) {
            throw new \Exception("Service {$service} not found");
        } else {
            $format = 'The service must be an instance of string, callable, %s given.';
            throw new \LogicException(sprintf($format, gettype($service)));
        }

        $injections = $this->services[$name]['arguments'];
        $parameters = [];

        $numGiven = count($injections);
        $maxArguments = count($reflection->getParameters());
        $minArguments = 0;
        foreach ($reflection->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                $minArguments++;
            }
        }

        if ($numGiven >= $minArguments && $numGiven <= $maxArguments) {
            foreach ($injections as $injection) {
                $parameter = $injection;
                if (is_string($injection) && substr($injection, 0, 1) == '@') {
                    $parameter = $this->loadService(substr($injection, 1));
                } elseif (is_string($injection) && preg_match_all('~%(.*?)%~', $injection, $matches) > 0) {
                    foreach ($matches[0] as $mKey => $match) {
                        $parameter = str_replace($match, $this->getParameter($matches[1][$mKey]), $parameter);
                    }
                } elseif (is_array($parameter)) {
                    $parameter = $this->replaceNestedParameters($parameter);
                }
                $parameters[] = $parameter;
            }
        } else {
            $message = "The Service {$name} expects exact {$minArguments} arguments, {$numGiven} given";
            if ($maxArguments != $minArguments) {
                $message = "The Service {$name} expects min. {$minArguments} and max. {$maxArguments} arguments, {$numGiven} given";
            }
            throw new \InvalidArgumentException($message);
        }

        $loadedService = null;
        if (is_string($service) && class_exists($service)) {
            $reflectionClass = new \ReflectionClass($service);
            $loadedService = $reflectionClass->newInstanceArgs($parameters);
        } elseif (is_callable($service)) {
            $loadedService = call_user_func_array($service, $parameters);
        }
        $this->loadedServices[$name] = &$loadedService;

        return $loadedService;
    }

    /**
     * Replaces Nested Parameter Dependencies
     * For Example
     * myDependency = [
     *      subEntry = '%my.app.parameter',
     *      other = [
     *          subSub = '%my.app.deep_parameter%'
     *      ]
     * ]
     *
     * @param array $array The dependency array
     * @return array The dependency array with the replaced parameters
     */
    public function replaceNestedParameters(array $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->replaceNestedParameters($value);
            } elseif (is_string($value) && preg_match_all('~%(.*?)%~', $value, $matches) > 0) {
                foreach ($matches[0] as $mKey => $mValue) {
                    $value = str_replace($mValue, $this->getParameter($matches[1][$mKey]), $value);
                }
                $array[$key] = $value;
            }
        }
        return $array;
    }

    /**
     * Returns all registered services
     *
     * @return array The registered Services
     */
    public function getRegisteredServices()
    {
        return $this->services;
    }

    /**
     * Returns all loaded services
     *
     * @return array The loaded Services
     */
    public function getLoadedServices()
    {
        return $this->loadedServices;
    }

    /**
     * Returns all defined parameters
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Adds a new Parameter to the service container
     *
     * @param string $name The parameter name
     * @param mixed $value The parameter value
     *
     * @throws \InvalidArgumentException If the name is not a string
     */
    public function addParameter($name, $value)
    {
        $this->setParameter($name, $value, false);
    }

    /**
     * Sets a parameter
     *
     * @param string $name The parameter name
     * @param mixed $value The parameter value
     * @param boolean $override If true, the parameter will be overwritten if it's already setted
     *
     * @throws \InvalidArgumentException If the name is not a string
     * @throws \LogicException If the parameter is already defined and override = false
     */
    public function setParameter($name, $value, $override = true)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('The name must be a string');
        } elseif (isset($this->parameters[$name]) && $override !== true) {
            throw new \LogicException("The parameter {$name} is already defined");
        }

        $this->parameters[$name] = $value;
    }

    /**
     * Unsets a parameter
     *
     * @param $name
     *
     * @throws \InvalidArgumentException If the name is not a string
     * @throws ParameterNotDefinedException If the parameter is not defined
     */
    public function unsetParameter($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('The name must be a string');
        } elseif (!$this->hasParameter($name)) {
            throw new ParameterNotDefinedException("A parameter with the name {$name} is not defined");
        }

        unset($this->parameters[$name]);
    }

    /**
     * Gets a single parameter
     *
     * @param string $name The parameter name
     * @return mixed|null
     *
     * @throws ParameterNotDefinedException If the parameter is not defined
     */
    public function getParameter($name)
    {
        if (!$this->hasParameter($name)) {
            throw new ParameterNotDefinedException("A parameter with the name {$name} is not defined");
        }
        return $this->parameters[$name];
    }

    /**
     * Checks if a parameter is setted
     *
     * @param string $name The parameter name
     * @return bool
     */
    public function hasParameter($name)
    {
        return array_key_exists($name, $this->parameters);
    }
}
