<?php
/**
 * This file is part of the Devtronic Injector package.
 *
 * Copyright {year} by Julian Finkler <julian@developer-heaven.de>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Devtronic\Injector;

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
     * Loads a service and returns the result
     * Dependencies are also loaded
     *
     * @param string $name The name of the Service
     * @return mixed The load result
     *
     * @throws ServiceNotFoundException If the service is not registered
     * @throws \InvalidArgumentException If the dependency count does not match the argument count of the service
     */
    public function loadService($name)
    {
        if (!isset($this->services[$name])) {
            throw new ServiceNotFoundException("A service with the name {$name} does not exist");
        }

        if (isset($this->loadedServices[$name])) {
            return $this->loadedServices[$name];
        }

        $reflection = new \ReflectionFunction($this->services[$name]['service']);

        $injections = $this->services[$name]['arguments'];
        $parameters = [];

        $numGiven = count($injections);
        $numExpected = count($reflection->getParameters());

        if ($numGiven == $numExpected) {
            foreach ($injections as $injection) {
                $parameter = $injection;
                if (substr($injection, 0, 1) == '@') {
                    $parameter = $this->loadService(substr($injection, 1));
                }
                $parameters[] = $parameter;
            }
        } else {
            throw new \InvalidArgumentException("The Service {$name} expects exact {$numExpected} arguments, {$numGiven} given");
        }

        $loadedService = call_user_func_array($this->services[$name]['service'], $parameters);
        $this->loadedServices[$name] = &$loadedService;
        return $loadedService;
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
}
