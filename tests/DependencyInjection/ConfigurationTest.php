<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\DependencyInjection;

use Lmc\Cqrs\Bundle\AbstractTestCase;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;

class ConfigurationTest extends AbstractTestCase
{
    /**
     * @test
     */
    public function configurationDefinition(): void
    {
        $dumper = new YamlReferenceDumper();

        $reference = <<<CONFIG
            lmc_cqrs:
                profiler:
                    enabled:%w false
                    verbosity:%w ''
                debug:%w false
                cache:
                    enabled:%w null
                    cache_provider:%w null
                extension:
                    http:%w false
                    solr:%w false

            CONFIG;

        $this->assertStringMatchesFormat($reference, $dumper->dump(new Configuration()));
    }
}
