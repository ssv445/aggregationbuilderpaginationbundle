<?php

declare(strict_types=1);

namespace Ludo\Bundle\AggregationBuilderPaginationBundle\Subscriber;

use Doctrine\ODM\MongoDB\Aggregation\Builder as AggregationBuilder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\UnitOfWork;
use Knp\Component\Pager\Event\ItemsEvent;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AggregationBuilderPaginationSubscriber implements EventSubscriberInterface
{
    /**
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * @var ReflectionProperty|null
     */
    private $hydrationClassRefl;

    /**
     * @var ReflectionProperty|null
     */
    private $stagesRefl;

    public function __construct(DocumentManager $dm)
    {
        $this->unitOfWork = $dm->getUnitOfWork();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'knp_pager.items' => ['items', 1],
        ];
    }

    public function items(ItemsEvent $event): void
    {
        if (!($event->target instanceof AggregationBuilder)) {
            return;
        }
        $ab = $event->target;

        $ab->facet()
            ->field('count')
            ->pipeline(
                $this->cleanupAggregationBuilder($ab)
                    ->count('count')
            )
            ->field('results')
            ->pipeline(
                $this->cleanupAggregationBuilder($ab)
                    ->limit($event->getLimit() + $event->getOffset())
                    ->skip($event->getOffset())
            );

        $hydrationClass = $this->getHydrationClass($ab);
        if ($hydrationClass) {
            $this->removeHydrationClass($ab);
        }
        $results = $ab->execute()->toArray();

        $event->count = $results[0]['count'][0]['count'] ?? 0;
        $event->items = $this->hydrateResults($results[0]['results'], $hydrationClass);

        $event->stopPropagation();
    }

    private function hydrateResults(array $results, ?string $hydrationClass): array
    {
        if (!$hydrationClass) {
            return $results;
        }

        return array_map(function ($result) use ($hydrationClass) {
            return $this->unitOfWork->getOrCreateDocument($hydrationClass, $result);
        }, $results);
    }

    private function cleanupAggregationBuilder(AggregationBuilder $ab): AggregationBuilder
    {
        $stagesReflection = $this->getStagesReflection();

        $clonedAb = clone $ab;
        $stagesReflection->setValue($clonedAb, []);

        return $clonedAb;
    }

    private function getHydrationClass(AggregationBuilder $ab): ?string
    {
        $hydrationClassRefl = $this->getHydrationClassReflection();

        return $hydrationClassRefl->getValue($ab);
    }

    private function removeHydrationClass(AggregationBuilder $ab): void
    {
        $hydrationClassRefl = $this->getHydrationClassReflection();
        $hydrationClassRefl->setValue($ab, null);
    }

    private function getHydrationClassReflection(): ReflectionProperty
    {
        if ($this->hydrationClassRefl) {
            return $this->hydrationClassRefl;
        }

        $builderReflClass = new ReflectionClass(AggregationBuilder::class);
        $this->hydrationClassRefl = $builderReflClass->getProperty('hydrationClass');
        $this->hydrationClassRefl->setAccessible(true);

        return $this->hydrationClassRefl;
    }

    private function getStagesReflection(): ReflectionProperty
    {
        if ($this->stagesRefl) {
            return $this->stagesRefl;
        }

        $builderReflClass = new ReflectionClass(AggregationBuilder::class);
        $this->stagesRefl = $builderReflClass->getProperty('stages');
        $this->stagesRefl->setAccessible(true);

        return $this->stagesRefl;
    }
}
