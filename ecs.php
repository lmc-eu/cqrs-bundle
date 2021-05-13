<?php declare(strict_types=1);

use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestAnnotationFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(
        Option::SKIP,
        ['SlevomatCodingStandard\Sniffs\Exceptions\ReferenceThrowableOnlySniff.ReferencedGeneralException' => ['tests/Exception/*.php']]
    );

    $containerConfigurator->import(__DIR__ . '/vendor/lmc/coding-standard/ecs.php');

    $services = $containerConfigurator->services();

    $services->set(PhpUnitTestAnnotationFixer::class)
        ->call('configure', [['style' => 'annotation']]);

    $services->set(NoSuperfluousPhpdocTagsFixer::class)
        ->call('configure', [['allow_mixed' => true]]);
};
