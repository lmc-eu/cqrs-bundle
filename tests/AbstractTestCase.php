<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

abstract class AbstractTestCase extends TestCase
{
    protected function assertHasServiceWithAlias(string $alias, string $class, ContainerBuilder $containerBuilder): void
    {
        $this->assertTrue($containerBuilder->hasDefinition($alias));
        $this->assertTrue($containerBuilder->has($class));
    }

    protected function assertHasDefinitionWithPriority(
        string $class,
        string $tag,
        int $priority,
        ContainerBuilder $containerBuilder,
    ): void {
        $definition = $containerBuilder->findDefinition($class);

        $this->assertTrue($definition->hasTag($tag));
        $tagAttributes = $definition->getTag($tag)[0];
        $this->assertSame($priority, $tagAttributes['priority']);
    }

    protected function assertHasServiceWithAliasTagAndPriority(
        string $alias,
        string $class,
        string $tag,
        int $priority,
        ContainerBuilder $containerBuilder,
    ): void {
        $this->assertHasServiceWithAlias($alias, $class, $containerBuilder);
        $this->assertHasDefinitionWithPriority($class, $tag, $priority, $containerBuilder);
    }

    protected function assertReference(string $expectedReferenceId, mixed $reference): void
    {
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame($expectedReferenceId, (string) $reference);
    }

    protected function assertTaggedIterator(
        string $expectedTag,
        string $expectedDefaultPriorityMethod,
        mixed $argument,
    ): void {
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);

        if ($argument instanceof TaggedIteratorArgument) {
            $this->assertSame($expectedTag, $argument->getTag());
            $this->assertSame($expectedDefaultPriorityMethod, $argument->getDefaultPriorityMethod());
        }
    }

    protected function assertAutoconfiguredTags(
        array $expectedAutoconfiguredTags,
        ContainerBuilder $containerBuilder,
    ): void {
        $definitions = $containerBuilder->getAutoconfiguredInstanceof();

        foreach ($expectedAutoconfiguredTags as $tag => $interface) {
            $this->assertArrayHasKey($interface, $definitions);
            $this->assertArrayHasKey($tag, $definitions[$interface]->getTags());
        }
    }
}
