<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle;

use Lmc\Cqrs\Bundle\DependencyInjection\Compiler\HandlerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LmcCqrsBundleTest extends AbstractTestCase
{
    /**
     * @test
     */
    public function shouldSetUpHandlerPass(): void
    {
        $containerBuilder = new ContainerBuilder();
        $bundle = new LmcCqrsBundle();

        $bundle->build($containerBuilder);

        $containsHandlerPass = false;
        foreach ($containerBuilder->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof HandlerPass) {
                $containsHandlerPass = true;
            }
        }

        $this->assertTrue($containsHandlerPass);
    }
}
