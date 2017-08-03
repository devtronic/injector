[![Travis](https://img.shields.io/travis/Devtronic/injector.svg)](https://travis-ci.org/Devtronic/injector)
[![Packagist](https://img.shields.io/packagist/v/Devtronic/injector.svg)](https://packagist.org/packages/devtronic/injector)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/Devtronic/injector/master/LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/devtronic/injector.svg)](https://packagist.org/packages/devtronic/injector)

# Injector
Injector is a dependency injection container.  
It's fast, reliable and easy to understand.


## Installation

```bash
$ composer require devtronic/injector
```

## Usage

### Register Services
To register a service you have to call the `registerService`-method.  
```
ServiceContainer::registerService($name, $service, $arguments = [])
```
|  Parameter | Description                                                                    | Example              |
|:-----------|:-------------------------------------------------------------------------------|:---------------------|
| name       | The unique name of the service.                                                | app.my_service       |
| service    | The service callable.                                                          | `function($arg1) {}` |
| arguments  | The arguments for the service. Entries with @-prefix are service references    | `['@app.foo', 1]`    |

#### Register a service with static arguments
Since not all services need an service injection, the arguments array also supports static entries.

```php
<?php

use Devtronic\Injector\ServiceContainer;

$serviceContainer = new ServiceContainer();

$serviceContainer->registerService('app.my_service', function ($name) {
  return 'Hello ' . $name;
}, ['Your Name']);

$serviceContainer->getRegisteredServices(); // Contains the registered Service 
```

#### Register a service with a service dependency
Sometimes you need another registered service in your service.
In that case you can pass the service name with a @-prefix to reference to it.
The (sub-) dependencies are solved recursively.

```php
<?php

use Devtronic\Injector\ServiceContainer;

$serviceContainer = new ServiceContainer();

$serviceContainer->registerService('app.another_service', function () {
    return [
        'name' => 'injector',
        'developer' => 'Julian',
    ];
});

$serviceContainer->registerService('app.my_service', function (array $anotherService) {
    return "Name: {$anotherService['name']}, developer: {$anotherService['developer']}";
}, ['@app.another_service']);
```

#### Register a class as a service
You can also register a class as a service. If the service is loaded, the constructor gets called with the dependencies.

```php
<?php

use Devtronic\Injector\ServiceContainer;

$serviceContainer = new ServiceContainer();

class Car
{
    /** @var int */
    public $maxSpeed = 0;

    /** @var string */
    public $color = '';

    public function __construct($maxSpeed, $color)
    {
        $this->maxSpeed = $maxSpeed;
        $this->color = $color;
    }
}

$serviceContainer->registerService('app.my_car', Car::class, [250, 'red']);

$myCar = $serviceContainer->loadService('app.my_car');
echo "My Car: Speed: {$myCar->maxSpeed}, Color: {$myCar->color}"; // My Car: Speed: 250, Color: red

```

### Load a service
To load a service you have to call the `loadService`-method.  
Once a service is loaded, it remains in memory at runtime.
When the same service is loaded again, the first instance is returned.

```
ServiceContainer::loadService($name)
```
|  Parameter | Description                     | Example        |
|:-----------|:--------------------------------|:---------------|
| name       | The unique name of the service. | app.my_service |

```php
<?php

use Devtronic\Injector\ServiceContainer;

$serviceContainer = new ServiceContainer();

$serviceContainer->registerService('app.another_service', function () {
    return [
        'name' => 'injector',
        'developer' => 'Julian',
    ];
});

$serviceContainer->registerService('app.my_service', function (array $anotherService) {
    return "Name: {$anotherService['name']}, developer: {$anotherService['developer']}";
}, ['@app.another_service']);

echo $serviceContainer->loadService('app.my_service'); // Name: injector, developer: Julian
```

### Add Parameters
The service container also supports static parameters.  
You can add a parameter using the `addParameter`-method
```
ServiceContainer::addParameter($name)
```
|  Parameter | Description                       | Example        |
|:-----------|:----------------------------------|:---------------|
| name       | The unique name of the parameter. | database.host  |

To pass a parameter to a service, add before and after the name a '%':  `%name.of.the.parameter%`
```php
<?php

use Devtronic\Injector\ServiceContainer;

$serviceContainer = new ServiceContainer();

$serviceContainer->addParameter('database.host', 'localhost');
$serviceContainer->registerService('my.service', function ($hostname) {
    return 'Connecting to ' . $hostname;
}, ['%database.host%']);
```

## Testing

```bash
$ phpunit
```

## Contribute
Feel free to fork and add pull-requests 🤓