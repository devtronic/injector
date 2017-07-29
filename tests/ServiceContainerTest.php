<?php
/**
 * This file is part of the Devtronic Injector package.
 *
 * Copyright {year} by Julian Finkler <julian@developer-heaven.de>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Devtronic\Tests\Injector;

use Devtronic\Injector\Exception\ServiceNotFoundException;
use Devtronic\Injector\ServiceContainer;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    public function testConstruct()
    {
        $serviceContainer = new ServiceContainer();

        $this->assertTrue($serviceContainer instanceof \Devtronic\Injector\ServiceContainer);
    }

    public function testRegisterServiceFails()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('fail_service', function () {
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A service with the name fail_service already exist');
        $serviceContainer->registerService('fail_service', function () {
        });
    }

    public function testRegisterService()
    {
        $serviceContainer = new ServiceContainer();
        $this->assertSame([], $serviceContainer->getRegisteredServices());

        $service = function () {
        };
        $serviceContainer->registerService('hello', $service, []);

        $expected = [
            'hello' => [
                'service' => $service,
                'arguments' => []
            ]
        ];
        $this->assertSame($expected, $serviceContainer->getRegisteredServices());
    }

    public function testLoadServiceFails()
    {
        $serviceContainer = new ServiceContainer();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('A service with the name non_existent_service does not exist');
        $serviceContainer->loadService('non_existent_service');
    }

    public function testLoadServiceSimple()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('message', function () {
            return 'A simple message';
        });

        $message = $serviceContainer->loadService('message');
        $this->assertSame('A simple message', $message);
    }

    public function testLoadServiceWithDependencyFails()
    {
        $serviceContainer = new ServiceContainer();

        $serviceContainer->registerService('message', function ($dependency) {
        }, ['one', 'two']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Service message expects exact 1 arguments, 2 given');
        $serviceContainer->loadService('message');
    }

    public function testLoadServiceWithStaticDependency()
    {
        $serviceContainer = new ServiceContainer();

        $serviceContainer->registerService('message', function ($dependency) {
            return 'Hello, I am a ' . $dependency;
        }, ['static dependency']);

        $message = $serviceContainer->loadService('message');
        $this->assertSame('Hello, I am a static dependency', $message);
    }

    public function testLoadServiceWithServiceDependency()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('a_dependency', function () {
            return 'dependency';
        });

        $serviceContainer->registerService('message', function ($dependency) {
            return 'Hello, I am a ' . $dependency;
        }, ['@a_dependency']);

        $message = $serviceContainer->loadService('message');
        $this->assertSame('Hello, I am a dependency', $message);
    }

    public function testLoadAlreadyLoadedService()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('my_object', function () {
            $cls = new \stdClass();
            $cls->name = 'Foobar';
            return $cls;
        });

        $this->assertSame([], $serviceContainer->getLoadedServices());

        $myClass = $serviceContainer->loadService('my_object');
        $this->assertSame('Foobar', $myClass->name);
        $myClass->name = 'Baz';

        // Load the service again
        $myClass = $serviceContainer->loadService('my_object');
        $this->assertSame('Baz', $myClass->name);

        $this->assertEquals([
            'my_object' => (object)['name' => 'Baz'],
        ], $serviceContainer->getLoadedServices());
    }
}
