[![Travis](https://img.shields.io/travis/Devtronic/injector.svg)](https://travis-ci.org/Devtronic/injector)
[![Packagist](https://img.shields.io/packagist/v/devtronic/injector.svg)](https://packagist.org/packages/devtronic/injector)
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
To register a service you have to call the `registerService`-Method.  
```
ServiceContainer::registerService($name, $service, $arguments = [])
```
|  Parameter | Description                                                                    | Example              |
|:-----------|:-------------------------------------------------------------------------------|:---------------------|
| name       | The unique name of the service.                                                | app.my_service       |
| service    | The service callable.                                                          | `function($arg1) {}`  |
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

### Load a service
To load a service you have to call the `loadService`-Method.  
Once a service is loaded, it remains in memory at runtime.
When the same service is loaded again, the first instance is returned.

```
ServiceContainer::loadService($name)
```
|  Parameter | Description                | Example        |
|:-----------|:---------------------------|:---------------|
| name       | The unique of the service. | app.my_service |

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

## Testing

```bash
$ phpunit
```

## Contribute
Feel free to fork and add pull-requests 🤓