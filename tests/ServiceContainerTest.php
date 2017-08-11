<?php
/**
 * This file is part of the Devtronic Injector package.
 *
 * Copyright 2017 by Julian Finkler <julian@developer-heaven.de>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Devtronic\Tests\Injector;

use Devtronic\Injector\Exception\ParameterNotDefinedException;
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

    public function testUnregisterServiceFailsMissing()
    {
        $serviceContainer = new ServiceContainer();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('A service with the name foobar does not exist');
        $serviceContainer->unregisterService('foobar');
    }

    public function testUnregisterServiceFailsAlreadyLoaded()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('foobar', function () {
            return 'baz';
        });
        $serviceContainer->loadService('foobar');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The service foobar can not be unregistered because its already loaded');
        $serviceContainer->unregisterService('foobar');
    }

    public function testUnregisterService()
    {
        $serviceContainer = new ServiceContainer();
        $this->assertEquals([], $serviceContainer->getRegisteredServices());
        $this->assertEquals([], $serviceContainer->getLoadedServices());
        $serviceContainer->registerService('foobar', function () {
            return 'baz';
        });

        $this->assertCount(1, $serviceContainer->getRegisteredServices());
        $this->assertCount(0, $serviceContainer->getLoadedServices());

        $serviceContainer->unregisterService('foobar');
        $this->assertCount(0, $serviceContainer->getRegisteredServices());
        $this->assertCount(0, $serviceContainer->getLoadedServices());
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

    public function testLoadServiceWithOptionalDependencyFails()
    {
        $serviceContainer = new ServiceContainer();

        $serviceContainer->registerService('message', function ($dependency = false) {
        }, ['one', 'two']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Service message expects min. 0 and max. 1 arguments, 2 given');
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

    public function testLoadServiceWithStaticDependencyArray()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('messages', function ($arrMessages, $delimiter = ';') {
            return implode($delimiter, $arrMessages);
        }, [['Hello', 'World'], ', ']);

        $message = $serviceContainer->loadService('messages');

        $this->assertSame('Hello, World', $message);
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

    public function testLoadServiceWithFQCNFails()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('app.my_car', 'Vendor\\Car', [244, 'red']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service Vendor\\Car not found');
        $serviceContainer->loadService('app.my_car');
    }

    public function testLoadServiceUnknownTypeFails()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('app.my_car', [], [244, 'red']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The service must be an instance of string, callable, array given.');
        $serviceContainer->loadService('app.my_car');
    }

    public function testLoadServiceWithFQCN()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('app.my_car', TestClass::class, [244, 'red']);

        $myCar = $serviceContainer->loadService('app.my_car');
        $this->assertTrue($myCar instanceof TestClass);
        $this->assertSame(244, $myCar->maxSpeed);
        $this->assertSame('red', $myCar->color);
    }

    public function testAddParameterFailsNoStringName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The name must be a string');

        $serviceContainer = new ServiceContainer();

        /** @noinspection PhpParamsInspection */
        $serviceContainer->addParameter([], []);
    }

    public function testAddParameterFailsAlreadyDefined()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->addParameter('foobar', 'baz');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The parameter foobar is already defined');

        $serviceContainer->addParameter('foobar', []);
    }

    public function testAddParameter()
    {
        $serviceContainer = new ServiceContainer();
        $this->assertSame([], $serviceContainer->getParameters());
        $serviceContainer->addParameter('foobar', 'baz');
        $this->assertSame(['foobar' => 'baz'], $serviceContainer->getParameters());
    }

    public function testSetParameterFailsNoStringName()
    {
        $serviceContainer = new ServiceContainer();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The name must be a string');

        /** @noinspection PhpParamsInspection */
        $serviceContainer->setParameter([], []);
    }

    public function testSetParameterFailsAlreadyDefined()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->setParameter('foobar', 'baz');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The parameter foobar is already defined');

        $serviceContainer->setParameter('foobar', [], false);
    }

    public function testSetParameter()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->setParameter('foobar', 'baz');
        $this->assertEquals('baz', $serviceContainer->getParameter('foobar'));
    }

    public function testSetParameterOverride()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->setParameter('foobar', 'baz');
        $this->assertEquals('baz', $serviceContainer->getParameter('foobar'));
        $serviceContainer->setParameter('foobar', []);
        $this->assertEquals([], $serviceContainer->getParameter('foobar'));
    }

    public function testUnsetParameterFailsNotExist()
    {
        $serviceContainer = new ServiceContainer();
        $this->assertCount(0, $serviceContainer->getParameters());

        $this->expectException(ParameterNotDefinedException::class);
        $this->expectExceptionMessage('A parameter with the name foobar is not defined');
        $serviceContainer->unsetParameter('foobar');
    }

    public function testUnsetParameterFailsNoStringName()
    {
        $serviceContainer = new ServiceContainer();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The name must be a string');
        $serviceContainer->unsetParameter([]);
    }

    public function testUnsetParameter()
    {
        $serviceContainer = new ServiceContainer();
        $this->assertCount(0, $serviceContainer->getParameters());
        $serviceContainer->addParameter('foobar', 'asd');
        $serviceContainer->setParameter('foobar', 'baz');
        $this->assertCount(1, $serviceContainer->getParameters());
        $serviceContainer->unsetParameter('foobar');
        $this->assertCount(0, $serviceContainer->getParameters());
    }

    public function testHasService()
    {
        $serviceContainer = new ServiceContainer();
        $this->assertFalse($serviceContainer->hasParameter('foobar'));
        $serviceContainer->addParameter('foobar', 'baz');
        $this->assertTrue($serviceContainer->hasParameter('foobar'));
    }

    public function testGetParameterFails()
    {
        $this->expectException(ParameterNotDefinedException::class);
        $this->expectExceptionMessage('A parameter with the name foobar is not defined');
        $serviceContainer = new ServiceContainer();
        $serviceContainer->getParameter('foobar');
    }

    public function testGetParameter()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->addParameter('foobar', 'baz');
        $this->assertEquals('baz', $serviceContainer->getParameter('foobar'));
    }

    public function testLoadServiceWithParameter()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->addParameter('database.host', 'my.server.tld');
        $serviceContainer->registerService('db.ctx', function ($host) {
            return 'Connecting to ' . $host;
        }, ['%database.host%']);

        $this->assertEquals('Connecting to my.server.tld', $serviceContainer->loadService('db.ctx'));
    }

    public function testLoadServiceWithParameterFails()
    {
        $serviceContainer = new ServiceContainer();
        $serviceContainer->registerService('app.foo', function ($db) {
            return $db;
        }, ['%foobar%']);


        $this->expectException(ParameterNotDefinedException::class);
        $this->expectExceptionMessage('A parameter with the name foobar is not defined');
        $serviceContainer->loadService('app.foo');
    }
}
