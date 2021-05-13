<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle;

use Lmc\Cqrs\Bundle\DependencyInjection\Compiler\HandlerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LmcCqrsBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new HandlerPass());
    }
}
