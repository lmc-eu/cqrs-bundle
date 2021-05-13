<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection\Compiler;

use Lmc\Cqrs\Bundle\AbstractTestCase;
use Lmc\Cqrs\Types\CommandSenderInterface;
use Lmc\Cqrs\Types\QueryFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class HandlerPassTest extends AbstractTestCase
{
    /**
     * @test
     */
    public function shouldSetUpQueryFetcher(): void
    {
        $container = new ContainerBuilder();
        $container->register(QueryFetcherInterface::class);
        $container->register('query_handler.first')->addTag('lmc_cqrs.query_handler', ['priority' => 80]);
        $container->register('query_handler.second')->addTag('lmc_cqrs.query_handler');

        $container->register('response_decoder.first')->addTag('lmc_cqrs.response_decoder', ['priority' => 70]);
        $container->register('response_decoder.second')->addTag('lmc_cqrs.response_decoder');

        $expectedMethods = [
            'addHandler' => [
                ['query_handler.first', 80],
                ['query_handler.second', 50],
            ],
            'addDecoder' => [
                ['response_decoder.first', 70],
                ['response_decoder.second', 50],
            ],
        ];

        $compilerPass = new HandlerPass();
        $compilerPass->process($container);

        $methodCalls = $container->getDefinition(QueryFetcherInterface::class)->getMethodCalls();

        $this->assertCalledMethods($expectedMethods, $methodCalls);
    }

    /**
     * @test
     */
    public function shouldSetUpCommandSender(): void
    {
        $container = new ContainerBuilder();
        $container->register(CommandSenderInterface::class);
        $container->register('send_command_handler.first')->addTag('lmc_cqrs.send_command_handler', ['priority' => 80]);
        $container->register('send_command_handler.second')->addTag('lmc_cqrs.send_command_handler');

        $container->register('response_decoder.first')->addTag('lmc_cqrs.response_decoder', ['priority' => 70]);
        $container->register('response_decoder.second')->addTag('lmc_cqrs.response_decoder');

        $expectedMethods = [
            'addHandler' => [
                ['send_command_handler.first', 80],
                ['send_command_handler.second', 50],
            ],
            'addDecoder' => [
                ['response_decoder.first', 70],
                ['response_decoder.second', 50],
            ],
        ];

        $compilerPass = new HandlerPass();
        $compilerPass->process($container);

        $methodCalls = $container->getDefinition(CommandSenderInterface::class)->getMethodCalls();

        $this->assertCalledMethods($expectedMethods, $methodCalls);
    }

    private function assertCalledMethods(array $expectedMethods, array $methodCalls): void
    {
        foreach ($expectedMethods as $method => $expectedParameters) {
            $currentMethodCalls = array_values(
                array_filter(
                    $methodCalls,
                    fn (array $called) => $called[0] === $method
                )
            );

            foreach ($currentMethodCalls as $i => [1 => $calledWith]) {
                $this->assertReference($expectedParameters[$i][0], $calledWith[0]);
                $this->assertSame($expectedParameters[$i][1], $calledWith[1]);
            }
        }
    }
}
