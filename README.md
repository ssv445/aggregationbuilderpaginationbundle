# LudoAggregationBuilderPaginationBundle

![pipeline_status][pipeline]

Extension bundle for Symfony's [KnpPaginatorBundle][KnpPaginatorBundle] that allows to paginate
[DoctrineMongoDBBundle][DoctrineMongoDBBundle] [`Doctrine\ODM\MongoDB\Aggregation\Builder`][AggregationBuilder].

## Requirements

Bundle uses MongoDB [`$facet`][facet] operator which is available since MongoDB 3.4.

## Installation with Symfony Flex

Open a command console, enter your project directory and execute:

```console
$ composer require ludo444/aggregation-builder-pagination-bundle
```

## Installation without Symfony Flex

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require ludo444/aggregation-builder-pagination-bundle
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Ludo\Bundle\AggregationBuilderPaginationBundle\LudoAggregationBuilderPaginationBundle::class => ['all' => true],
];
```

## Usage

[`Doctrine\ODM\MongoDB\Aggregation\Builder`][AggregationBuilder] needs to be passed into `paginate()` method. Be aware that
most of the `Builder` methods are returning `Doctrine\ODM\MongoDB\Aggregation\Stage`. So you need to do eg.:

```php
// src/Repository/ExampleRepository.php
use Doctrine\ODM\MongoDB\Aggregation\Builder as AggregationBuilder;
// ...

class ExampleRepository extends Repository
{
    public function getExamples(): AggregationBuilder
    {
        $ab = $this->createAggregationBuilder();
        
        $ab->hydrate(Example::class)
            ->match()
                ->field('field')
                ->equals('value');
        
        return $ab;
    }
}
```

As `->equals('value')` would not return `AggregationBuilder`, the code would throw an `Exception` if you return
the result of that method directly. Now to paginate the example repository method, you can just do:

```php
// src/Subfolder/ExamplePagination.php
// ...

class ExamplePagination
{
    // ...

    public function __construct(DocumentManager $manager, PaginatorInterface $paginator)
    {
        $this->manager = $manager;
        $this->paginator = $paginator;
    }

    public function getPaginatedExamples(): PaginationInterface
    {
        return $this->paginator->paginate(
            $this->manager->getRepository(ExampleRepository::class)->getExamples()
        );
    }
}
```

[pipeline]: https://gitlab.com/ludo444/aggregationbuilderpaginationbundle/badges/master/pipeline.svg
[KnpPaginatorBundle]: https://github.com/KnpLabs/KnpPaginatorBundle
[DoctrineMongoDBBundle]: https://github.com/doctrine/DoctrineMongoDBBundle
[facet]: https://docs.mongodb.com/manual/reference/operator/aggregation/facet/
[AggregationBuilder]: https://www.doctrine-project.org/projects/doctrine-mongodb-odm/en/2.0/reference/aggregation-builder.html
