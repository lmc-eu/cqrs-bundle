LMC CQRS Bundle
===============

[![cqrs-types](https://img.shields.io/badge/cqrs-types-purple.svg)](https://github.com/lmc-eu/cqrs-types)
[![Tests and linting](https://github.com/lmc-eu/cqrs-bundle/actions/workflows/tests.yaml/badge.svg)](https://github.com/lmc-eu/cqrs-bundle/actions/workflows/tests.yaml)
[![Coverage Status](https://coveralls.io/repos/github/lmc-eu/cqrs-bundle/badge.svg?branch=main)](https://coveralls.io/github/lmc-eu/cqrs-bundle?branch=main)

> Symfony bundle for CQRS libraries and extensions. It registers services, data collectors etc. by a configuration.

## Table of contents
- [Installation](#installation)
- [Configuration](#configuration)
    - [Routes](#routes)
    - [Tags](#tags)
- [Services](#services)
    - [Handlers](#handlers)
- [Extensions](#extensions)
    - [HTTP](#http)
    - [Solr](#solr)
- [List of all predefined services](#list-of-all-predefined-services-and-their-priorities)

## Installation
```shell
composer require lmc/cqrs-bundle
```

## Configuration

```yaml
lmc_cqrs:
    profiler: false         # Whether to enable profiler and allow to profile queries and commands [default false]
    debug: false            # Whether to enable debug the CQRS by a console command [default false]

    cache:
        enabled: false                          # Whether to use cache for Queries [default false (true, if you define cache_provider)]
        cache_provider: '@my.cache.provider'    # Service implementing a CacheItemPoolInterface. Required when cache is enabled [default null]

    extension:
        http: false         # Whether should http extension be active (requires a lmc/cqrs-http dependency) [default false]
        solr: false         # Whether should solr extension be active (requires a lmc/cqrs-solr dependency) [default false]
```

**TIPs**:
- it is advised to set `profiler: '%kernel.debug%'` so it profiles (and registers all services for profiling) only when it is really used
- you can define `profiler` and `debug` in your `dev/lmc_cqrs.yaml` to only allow it in `dev` Symfony environment

**Note**: if you don't enable any of the extension, there will only be a `CallbackQueryHandler` and `CallbackSendCommandHandler`, so you probably need to register your own.

### Routes
You must register the routes for a CQRS bundle if you enable a profiler.

```yaml
# config/routes.yaml

lmc_cqrs_bundle_routes:
    resource: "@LmcCqrsBundle/Resources/config/routes.yaml"
```

### Tags:
> Tags are automatically registered, if your class implements an Interface and is registered in Symfony container as a service

- `lmc_cqrs.query_handler` (`QueryHandlerInterface`)
- `lmc_cqrs.send_command_handler` (`SendCommandHandlerInterface`)
- `lmc_cqrs.profiler_formatter` (`ProfilerFormatterInterface`)
- `lmc_cqrs.response_decoder` (`ResponseDecoderInterface`)

With priority
```yaml
services:
    My\CustomQueryHandler:
        tags:
            - { name: 'lmc_cqrs.query_handler', priority: 80 }
```

**Note**: Default priority is `50` and none of the predefined handlers, profilers, etc. has priority higher than `90` (see [complete list below](#list-of-all-predefined-services-and-their-priorities))

## Services
Bundle registers all necessary services according to configuration (for example, if you set `http: true` it will automatically register all http handlers, etc.)

Most of the services are registered both by an alias and a class name, so it will be available for autowiring.
All interfaces are automatically configured to have a tag (see [Tags](#tags) section above).

### Handlers
There are 2 main services, which are essential to the library.
Both of them have its interface to represent it, and it is advised to use it via the interface.

#### 1. Query Fetcher Interface
- implementation `Lmc\Cqrs\Handler\QueryFetcher`
- alias: `@lmc_cqrs.query_fetcher`
- it will find a handler for your query, handles it, decodes a response and caches the result (if cache is enabled)
- provides features:
    - caching
        - requires:
            - cache_provider (set in the configuration) - service implements `Psr\Cache\CacheItemPoolInterface`
            - Query to implement `Lmc\Cqrs\Types\Feature\CacheableInterface`
        - it allows to cache a decoded result and load it again from cache
    - profiling
        - requires:
            - enabled profiler (in the configuration)
            - Query to implement `Lmc\Cqrs\Types\Feature\ProfileableInterface`
        - it profiles a query, its execution time, response, applied handler and decoders and shows the info in the Symfony profiler

Fetching a query

You can do whatever you want with a response, we will persist a result into db, for an example or log an error.
```php
// with continuation
$this->queryFetcher->fetch(
    $query,
    fn ($response) => $this->repository->save($response),
    fn (\Throwable $error) => $this->logger->critical($error->getMassage())
);

// with return
try {
    $response = $this->queryFetcher->fetchAndReturn($query);
    $this->repository->save($response);
} catch (\Throwable $error) {
    $this->logger->critical($error->getMessage());
}
```

#### 2. Command Sender Interface
- implementation `Lmc\Cqrs\Handler\CommandSender`
- alias: `@lmc_cqrs.command_sender`
- it will find a handler for your command, handles it, decodes a response
- provides features:
    - profiling
        - requires:
            - enabled profiler (in the configuration)
            - Command to implement `Lmc\Cqrs\Types\Feature\ProfileableInterface`
        - it profiles a command, its execution time, response, applied handler and decoders and shows the info in the Symfony profiler

Sending a command

You can do whatever you want with a response, we will persist a result into db, for an example or log an error.
```php
// with continuation
$this->commandSender->send(
    $command,
    fn ($response) => $this->repository->save($response),
    fn (\Throwable $error) => $this->logger->critical($error->getMassage())
);

// with return
try {
    $response = $this->commandSender->sendAndReturn($query);
    $this->repository->save($response);
} catch (\Throwable $error) {
    $this->logger->critical($error->getMessage());
}
```

**Note**: There is no logging feature in the CQRS library, if you need one, you have to implement it by yourself.

### Profiler Bag
There is a `profiler bag` service, which is a collection of all profiler information in the current request.
The information inside are used by a `CqrsDataCollector`, which shows them in the Symfony profiler.

It requires `profiler: true` in the configuration.

You can access the profiler bag either by:
- `@lmc_cqrs.profiler_bag` (alias)
- `Lmc\Cqrs\Handler\ProfilerBag` (autowiring)
- or access a `CqrsDataCollector` programmatically (see [here](https://symfony.com/doc/current/profiler.html#accessing-profiling-data-programmatically))

## Extensions

We offer a basic extensions for a common Commands & Queries
- [Http](#http) (using [PSR-7](https://www.php-fig.org/psr/psr-7/))
- [SOLR](#solr) (using [Solarium](https://github.com/solariumphp/solarium))

### Http
> [Http extension repository](https://github.com/lmc-eu/cqrs-http)

Installation
```shell
composer require lmc/cqrs-http
```

**NOTE**: You will also need an implementation for [PSR-7](https://packagist.org/providers/psr/http-message-implementation), [PSR-17](https://packagist.org/providers/psr/http-factory-implementation) and [PSR-18](https://packagist.org/providers/psr/http-client-implementation) for HTTP extensions to work.

Configuration
```yaml
lmc_cqrs:
    extension:
        http: true
```

Enabling a Http extension will allow a `QueryFetcher` and `CommandSender` to handle a PSR-7 Requests/Response and decode it.

### Solr
> [SOLR extension repository](https://github.com/lmc-eu/cqrs-solr)

Installation
```shell
composer require lmc/cqrs-solr
```

Configuration
```yaml
lmc_cqrs:
    extension:
        solr: true
```

Enabling a Solr extension will allow a `QueryFetcher` and `CommandSender` to handle a Solarium Requests/Result and decode it.

#### Solarium Query Builder
It allows you to build a Solarium request only by defining an Entity with all features you want to provide.
See [Solr extension readme](https://github.com/lmc-eu/cqrs-solr#query-builder) for more information.

**Note**: You can specify a tag for your custom applicator by `lmc_cqrs.solr.query_builder_applicator`

## List of all predefined services and their priorities

**Note**: To see a list of all services really registered in your application use `bin/console debug:cqrs` (it requires `debug: true` in your configuration)

### Top most handlers for Commands & Queries
| Interface | Class | Alias |
| ---       | ---   | ---   |
| Lmc\Cqrs\Types\QueryFetcherInterface | Lmc\Cqrs\Handler\QueryFetcher | `@lmc_cqrs.query_fetcher` |
| Lmc\Cqrs\Types\CommandSenderInterface | Lmc\Cqrs\Handler\CommandSender | `@lmc_cqrs.command_sender`

### Query Handlers
| Service | Alias | Tag | Priority | Enabled |
| ---     | ---   | --- | ---      | ---     |
| Lmc\Cqrs\Handler\Handler\GetCachedHandler | - | - | 80 | if `cache` is enabled |
| Lmc\Cqrs\Handler\Handler\CallbackQueryHandler | `@lmc_cqrs.query_handler.callback` | `lmc_cqrs.query_handler` | 50 | *always* |
| Lmc\Cqrs\Http\Handler\HttpQueryHandler | `@lmc_cqrs.query_handler.http` | `lmc_cqrs.query_handler` | 50 | if `http` extension is enabled |

### Send Command Handlers
| Service | Alias | Tag | Priority | Enabled |
| ---     | ---   | --- | ---      | ---     |
| Lmc\Cqrs\Handler\Handler\CallbackSendCommandHandler | `@lmc_cqrs.send_command_handler.callback` | `lmc_cqrs.send_command_handler` | 50 | *always* |
| Lmc\Cqrs\Http\Handler\HttpSendCommandHandler | `@lmc_cqrs.send_command_handler.http` | `lmc_cqrs.send_command_handler` | 50 | if `http` extension is enabled |

### Response decoders
| Service | Alias | Tag | Priority | Enabled |
| ---     | ---   | --- | ---      | ---     |
| Lmc\Cqrs\Http\Decoder\HttpMessageResponseDecoder | `@lmc_cqrs.response_decoder.http` | `lmc_cqrs.response_decoder` | 90 | if `http` extension is enabled |
| Lmc\Cqrs\Http\Decoder\StreamResponseDecoder | `@lmc_cqrs.response_decoder.stream` | `lmc_cqrs.response_decoder` | 70 | if `http` extension is enabled |
| Lmc\Cqrs\Types\Decoder\JsonResponseDecoder | `@lmc_cqrs.response_decoder.json` | `lmc_cqrs.response_decoder` | 20 | *always* |

### Profiler formatters
| Service | Alias | Tag | Priority | Enabled |
| ---     | ---   | --- | ---      | ---     |
| Lmc\Cqrs\Http\Formatter\HttpProfilerFormatter | `@lmc_cqrs.profiler_formatter.http` | `lmc_cqrs.profiler_formatter` | -1 | if `http` extension is enabled |
| Lmc\Cqrs\Types\Formatter\JsonProfilerFormatter | - | `lmc_cqrs.profiler_formatter` | -1 | if `profiler` is enabled |
| Lmc\Cqrs\Bundle\Service\ErrorProfilerFormatter | - | `lmc_cqrs.profiler_formatter` | -1 | if `profiler` is enabled |

### Other services
| Class | Alias | Purpose | Enabled |
| ---   | ---   | ---     | ---     |
| Lmc\Cqrs\Bundle\Controller\CacheController | - | Controller for invalidating cache from a profiler | if `profiler` is enabled |
| Lmc\Cqrs\Bundle\Profiler\CqrsDataCollector | - | Collects a data about Commands & Queries for a profiler | if `profiler` is enabled |
| Lmc\Cqrs\Handler\ProfilerBag | `@lmc_cqrs.profiler_bag` | A collection of all ProfilerItems, it is a main source of data for a CqrsDataCollector | if `profiler` is enabled |
