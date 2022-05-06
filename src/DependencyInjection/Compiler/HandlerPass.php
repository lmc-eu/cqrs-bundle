<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection\Compiler;

use Lmc\Cqrs\Bundle\DependencyInjection\LmcCqrsExtension;
use Lmc\Cqrs\Types\CommandSenderInterface;
use Lmc\Cqrs\Types\QueryFetcherInterface;
use Lmc\Cqrs\Types\ValueObject\PrioritizedItem;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class HandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->setUpQueryFetcher($container);
        $this->setUpCommandSender($container);
    }

    private function setUpQueryFetcher(ContainerBuilder $container): void
    {
        if (!$container->has(QueryFetcherInterface::class)) {
            return;
        }

        $queryFetcher = $container->findDefinition(QueryFetcherInterface::class);
        $defaultPriority = $this->getDefaultPriority();

        foreach ($this->iterateByTags(
            $container,
            LmcCqrsExtension::TAG_QUERY_HANDLER,
            $defaultPriority,
        ) as $handlerId => $priority) {
            $queryFetcher->addMethodCall('addHandler', [new Reference($handlerId), $priority]);
        }

        foreach ($this->iterateByTags(
            $container,
            LmcCqrsExtension::TAG_RESPONSE_DECODER,
            $defaultPriority,
        ) as $decoderId => $priority) {
            $queryFetcher->addMethodCall('addDecoder', [new Reference($decoderId), $priority]);
        }
    }

    private function setUpCommandSender(ContainerBuilder $container): void
    {
        if (!$container->has(CommandSenderInterface::class)) {
            return;
        }

        $commandSender = $container->findDefinition(CommandSenderInterface::class);
        $defaultPriority = $this->getDefaultPriority();

        foreach ($this->iterateByTags(
            $container,
            LmcCqrsExtension::TAG_SEND_COMMAND_HANDLER,
            $defaultPriority,
        ) as $handlerId => $priority) {
            $commandSender->addMethodCall('addHandler', [new Reference($handlerId), $priority]);
        }

        foreach ($this->iterateByTags(
            $container,
            LmcCqrsExtension::TAG_RESPONSE_DECODER,
            $defaultPriority,
        ) as $decoderId => $priority) {
            $commandSender->addMethodCall('addDecoder', [new Reference($decoderId), $priority]);
        }
    }

    private function getDefaultPriority(): int
    {
        return PrioritizedItem::PRIORITY_MEDIUM;
    }

    private function iterateByTags(ContainerBuilder $container, string $tag, int $defaultPriority): iterable
    {
        foreach ($container->findTaggedServiceIds($tag) as $handlerId => $tags) {
            foreach ($this->getPriorities($tags, $defaultPriority) as $priority) {
                yield $handlerId => $priority;
            }
        }
    }

    private function getPriorities(array $tags, int $defaultPriority): array
    {
        $priorities = [];

        foreach ($tags as $tag) {
            if (array_key_exists('priority', $tag)) {
                $priority = (int) $tag['priority'];
                $priorities[$priority] = $priority;
            }
        }

        return empty($priorities)
            ? [$defaultPriority]
            : $priorities;
    }
}
