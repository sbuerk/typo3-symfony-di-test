sbuerk/typo3-symfony-di-test
============================

## Preamble

This repository and package is just a `proof-of-concept` implementation and not
meant to be a longtime example or demonstration. At lest not yet. Thus, this
package lacks for now CGL, code analyzers and test and is not maintained in any
case.

Take this into account if you want to contribute, as literally this may be a
waste of time because it is not meant to be maintained.

However, while talking about this contribution may be added (pull-request) to
co-work on this to have something testable at hand to talk and decide if the
included pattern(s) may be used and documented within the TYPO3 core and/or
extensions.

Note that this TYPO3 extension will not be released in the TYPO3 Extension
repository - either download it/check it out for non-composer installations
or monorepo. However, it is registered in public packagist and can be required
in composer based installations with:

```terminal
composer require --dev sbuerk/typo3-symfony-di-test
```

## Introduction

This package contains a TYPO3 extension which demonstrates different Symfony DI
techniques.

### Using Symfony Service decoration for extendable service factories

It's a common way to use the service factory pattern to create a instance based
on different configuration parameters. In these cases the `ServiceFactory` is
used within consumer classes (injected service factory) to retrieve the matching
service.

The usual TYPO3 Core way to use service factory forces extension authors to
replace the DI definition with the extended service factory which is only
possible if the service factory is not final or the factory implements a
factory interface and the interface is used as type annotation in core code.
Extension authors adopted the same technique for their own extensions.

In cases multiple extension wants to influence the factory to retrieve
adjusted (custom) services based on some context data but still keep
prior service factory in place this was not possible.

Symfony DI supports service decoration, which can be used to provide a more
flexible way for extension authors to influence service creation based on
context data. This package contains a demonstration for that technique.

#### Scenario introduction

For the demonstration scenario we assume, that a way is needed to create
specialized service based on a `string value` as context data with a default
service.

That means, that we need a `ServiceInterface` which can be used for PHP type
declarations in methods and return types and ensure that required methods exists.
The service itself should be able to retrieve autowired dependencies.

To be able to create service instance based on that context value, a service
factory is a good way to archieve that:

```php
final class ServiceFactory {
    public function create(string $context): ServiceInterface
    {
        return match($context) {
          'special' => new SpecialService(),
          default => new DefaultService(),
        };
    }
}
```

If the service factory class is final like in the example above, extension
authors are not able to extend the service factory and replace (alias) them
in the Symfony DI configuration. Using a registry (injected) or tagged services
array could be used to replace the match/context determination by calling a
method on all retrieved tagged service to determine the service to use and
return.

For the sake of this scenario, it would be nice that extension authors may be
able to simple add custom service factories retrieving the prior service factory,
so it can fallback to the parent factory:

```php
final class ServiceFactory {
    public function __construct(
        private DefaultServiceFactory $serviceFactory,
    ) {}

    public function create(string $context): ServiceInterface
    {
        return match($context) {
          'special' => new SpecialService(),
          default => $this->serviceFactory->create($context),
        };
    }
}
```

This is possible in general, but nearly impossible for extension when multiple
extension want to do that because the type hint in the constructor.

Given that we introduce a `ServiceFactoryInterface` it is still a configuration
nightmare and instances (developer/maintainer) needs to adjust the chain through
multiple extensions which is quite a horrible way.

Combining the use of an interface with the `Decorator Pattern` this would be
more suitable:

```php
final class ServiceFactory implements ServiceFactoryInterface  {
    public function __construct(
        private ServiceFactoryInterface $serviceFactory,
    ) {}

    public function create(string $context): ServiceInterface
    {
        return match($context) {
          'special' => new SpecialService(),
          default => $this->serviceFactory->create($context),
        };
    }
}
```

we could provide a chain. How to use the decorator pattern with Symfony DI is
the background for this example.

#### Symfony DI service decorator pattern - what is needed ?

From the TYPO3 Core side (or a extension providing this) following basic parts
are needed:

* `ServiceInterface`: Define the required methods and helps with type declaration
* `ServiceFactoryInterface`: Define the factory method with context data, for
  example `ServiceFactoryInterface->create(string $context): ServiceInterface;`
* `DefaultService` implementing the `ServiceInterface` is the default service.
* `DefaultServiceFactory` implementing the `ServiceFactoryInterface` is the
  default service factory retrieved for the `ServiceFactoryInterface` -

Implementation of the aforementioned requirement are explained in detail in
next sections.

Based on that, extension authors would

* implement a custom service based on `ServiceInterface` => `CustomService`
* implement a custom service factory based on the `ServiceFactoryInterface`,
  configured as decorator for the default service factory implementation
  `DefaultServiceFactory`

The example implementation for extension authors are also explained later.

Note that default extension `Configuration/Services.yaml` snippet is assumed
for explained explanation using Symfony PHP attributes:

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  SBUERK\DiTests\:
    resource: '../Classes/*'
```

Readup:

* [Symfony ServiceContainer: How to Decorate Services](https://symfony.com/doc/current/service_container/service_decoration.html)

##### BASE: ServiceInterface

The service interface defines the required public facing methods all services
requires to implement, for the demonstration purpose a simple `ping()` method:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

interface ServiceInterface
{
    public function ping(): string;
}
```

##### BASE: ServiceFactoryInterface

We need to ensure a fixed factory method and additional prepare for decorating
services, providing an interface for it is the way to go:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

interface ServiceFactoryInterface
{
    public function create(string $someData): ServiceInterface;
}
```

Note that we do not make the constructor part of the interface to allow Symfony
DI autowiring for factory implementations.

##### BASE: DefaultServiceInterface

As we want to provide a default implementation or the service, we implement a
generic one. We also uses PHP Attrobites to tell Symfony DI to use this class
as implementation for the `ServiceInterface` (default service):

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsAlias(id: ServiceInterface::class, public: true)]
final readonly class DefaultService implements ServiceInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function ping(): string
    {
        return __CLASS__;
    }
}
```

Note that we need to mark this service public, as we later retrieve it from
the DI container within the DefaultServiceFactory - without marking it public
the `DefaultService` would be removed from the DI container leading to an error.

Additionally, we add an alias to the service factory so it is possible to
retrieve the DefaultService for the interface, for example in custom service
implementations (see later for second service implementation).

##### BASE: DefaultServiceFactory

Now, we implement a default service factory to retrieve the default service and
also being the first class to autowire if `ServiceFactoryInterface` is requested
to be autowired into classes:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: ServiceFactoryInterface::class)]
final readonly class DefaultServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return $this->container->get(DefaultService::class);
    }
}
```

The `#[AsAlias]` attribute is here used to define this class as default factory
for the `ServiceFactoryInterface`.

Note that it is possible for extension loaded later to also use this attribute
and override the `DefaultServiceFactory` if required. The original default can
still be autowired by using `DefaultServiceFactory` type within the constructor.
Replacing the default service factory is not part of this demonstration.

##### EXTENSION: Provide custom service - CustomService

Extension may want to provide custom services for specific context values. For
that, they implement the custom service based on the `ServiceInterface`:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[Autoconfigure(public: true)]
final readonly class SecondService implements ServiceInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private ResourceFactory $resourceFactory,
    ) {}

    public function ping(): string
    {
        return __CLASS__;
    }
}
```

Note that `public: true` is required here to avoid the removal from the DI
container - otherwise the factory could not retrieve it from the DI container.

In some cases it may be useful to retrieve the original default service,
which can be achieved by adding it to the service constructor by name and
not the interface:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[Autoconfigure(public: true)]
final readonly class SecondService implements ServiceInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private ResourceFactory $resourceFactory,
        private ServiceInterface $defaultService,
        // ^^ or private DefaultService $defaultService,
    ) {}

    public function ping(): string
    {
        // use public methods from the default service
        return $this->defaultService->ping() , ' -> ' . __CLASS__;
    }
}
```

##### EXTENSION: Provide custom service factory SecondServiceFactory

Usually and mostly known in the TYPO3 community, we would now implement a
custom service factory retrieving the `DefaultFactory` to be able to call it:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;use TYPO3\CMS\Install\FolderStructure\DefaultFactory;

#[AsAlias(id: ServiceFactoryInterface::class)]
final readonly class SecondServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        private ContainerInterface $container,
        private DefaultFactory $defaultFactory,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return match ($someData) {
            'second' => $this->container->get(SecondService::class),
            default => $this->defaultFactory->create($someData),
        };
    }
}
```

which replaces the `DefaultFactory` for `ServiceFactoryInterface` when the
extension is loaded after the extension providing the base implementation,
controlled by require/suggest within composer.json OR depends/suggest in
`ext_emconf.php` in legacy mode.

However, with multiple extension in need to do this it is impossible with
this technique to have all in the chain. Some would start to check for other
extension and do the implementation casual based in some way which is still
a continues maintainance burden and communication/coordination affort for
extension authors.

To mitigate these issues, extension authors can use the decorator pattern
to decorate the prior service factory which simply build a decorator chain:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;

#[AsDecorator(decorates: ServiceFactoryInterface::class, priority: 0, onInvalid: SymfonyContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final readonly class SecondServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        private ContainerInterface $container,
        #[AutowireDecorated]
        private ServiceFactoryInterface $serviceFactory,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return match ($someData) {
            'second' => $this->container->get(SecondService::class),
            default => $this->serviceFactory->create($someData),
        };
    }
}
```

We use the default service factory as decoration basis, but note that the
retrieved `$serviceFactory` may already be a decorated service factory
from another extension which allows to call the chain within the create
method.

`#[AsDecorator]` attribute is used to tell Symfony DI to decorate the current
`DefaultServiceFactory` instance with the new one, either the initial or the
already decorated instance. Using an Interface for type declarations allows us
to make all decorated service factories final, because extending is not needed.

The `#[AutowireDecorated]` flags the constructor argument which retrieves the
prior service factory instance, which should be decorated by this implementation.
Depending on options (onInvalid) it may be required to make the property nullable
and check for execution.

Note that it is possible to use a `AbstractServiceFactory` instead of an interface
in type hints and configuration which all custom service factories needs to implement
which may be also a valid approach. Using an interface does not make an abstract
implementation impossible, but extension authors are free to use the abstract or
not as long as they implement all required interface methods.

Other extension may add additional service factory/factories:

```php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;

#[AsDecorator(decorates: DefaultServiceFactory::class, priority: 1, onInvalid: SymfonyContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final readonly class SomeServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        private ContainerInterface $container,
        #[AutowireDecorated]
        private ServiceFactoryInterface $serviceFactory,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return match ($someData) {
            'some' => $this->container->get(SomeService::class),
            'third' => $this->container->get(ThirdService::class),
            default => $this->serviceFactory->create($someData),
        };
    }
}
```

##### BASE: Demo cli command with injected servicefactory

Let's now implement a cli command which expects autowired `ServiceFactoryInterface`
as constructor argument and call the `ServiceFactoryInterface->create()` with multiple
context values and returning the output of the `ServiceInterface->ping()` method result:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Command;

use SBUERK\DiTests\Services\ServiceFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:test')]
final class TestCommand extends Command
{
    public function __construct(
        private ServiceFactoryInterface $serviceFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');

        $output->writeln(sprintf('ServiceFactoryInterface implemetnation: %s', $this->serviceFactory::class));
        $output->writeln('');

        $someFactory = $this->serviceFactory->create('some');
        $output->writeln(sprintf('Service class for context "some": %s', $someFactory::class));
        $output->writeln('Service->ping() result: ' . $someFactory->ping());
        $output->writeln('');

        $secondService = $this->serviceFactory->create('second');
        $output->writeln(sprintf('Service class for context "second": %s', $secondService::class));
        $output->writeln('Service->ping() result: ' . $secondService->ping());
        $output->writeln('');

        return Command::SUCCESS;
    }
}
```

If we execute this command, we would retrieve following output:

```terminal
$ bin/typo3 di:test

ServiceFactoryInterface implemetnation: SBUERK\DiTests\Services\SecondServiceFactory

Service class for context "some": SBUERK\DiTests\Services\DefaultService
Service->ping() result: SBUERK\DiTests\Services\DefaultService

Service class for context "second": SBUERK\DiTests\Services\SecondService
Service->ping() result: SBUERK\DiTests\Services\DefaultService => SBUERK\DiTests\Services\SecondService

```

##### Combining with other techniques

This pattern can be mixed with the Symfony DI service `Lazy` instantiating feature.

For example, the `SecondService` could be injected in the `SecondServiceFactory`
and using the lazy option for the autowiring attribute. The service factory
would then look similar to following:

```php
<?php

declare(strict_types=1);

namespace SBUERK\DiTests\Services;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;

#[AsDecorator(decorates: ServiceFactoryInterface::class, priority: 0, onInvalid: SymfonyContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final readonly class SecondServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        #[Autowire(service: SecondService::class, lazy: true)]
        private ServiceInterface $secondService,
        #[AutowireDecorated]
        private ServiceFactoryInterface $serviceFactory,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return match ($someData) {
            'second' => $this->secondService,
            default => $this->serviceFactory->create($someData),
        };
    }
}
```

which is compatible to the prior implementation, but will change the output of
the prior described test command:

```terminal
$ bin/typo3 di:test

ServiceFactoryInterface implemetnation: SBUERK\DiTests\Services\SecondServiceFactory

Service class for context "some": SBUERK\DiTests\Services\DefaultService
Service->ping() result: SBUERK\DiTests\Services\DefaultService

Service class for context "second": ServiceInterfaceProxyD7aab2d
Service->ping() result: SBUERK\DiTests\Services\DefaultService => SBUERK\DiTests\Services\SecondService

```

The minor but important hint is the service class for second, which is now an
automatic created service proxy, here named `ServiceInterfaceProxyD7aab2d`,
returning the `SecondService` instance when request by calling methods on it by
dispatching. The lazy proxy is a intelligent and hidden decorator variant.

The benefit is, that the service must not be marked as public anymore and it
is not needed to hand over the container to the factory.

#### Conclusion / Fazit

From my point of view, using the decorator service (factory) pattern along with
lazy service injection even for the default service factory would be a good way
for new implementation, allowing the TYPO3 Core to mark factories and services
final while keeping extensibility and testability up.

#### Still open to consider/verify

The `[AsDecorator()]` attribute allows to define priorities. The pro/cons to use
the priority is open to investigate. That should be done before using the
`Decorator` pattern for services or service factories to have proper material
and argumentation for the TYPO3 Documentation at hand.
