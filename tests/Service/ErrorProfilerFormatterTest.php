<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Service;

use Lmc\Cqrs\Bundle\AbstractTestCase;
use Lmc\Cqrs\Types\ValueObject\FormattedValue;
use Lmc\Cqrs\Types\ValueObject\ProfilerItem;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class ErrorProfilerFormatterTest extends AbstractTestCase
{
    private ErrorProfilerFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ErrorProfilerFormatter();
    }

    /**
     * @dataProvider provideError
     *
     * @test
     */
    public function shouldFormatErrors(ProfilerItem $item, ProfilerItem $expected): void
    {
        $formatted = $this->formatter->formatItem($item);

        $this->assertEquals($expected, $formatted);
    }

    public function provideError(): array
    {
        return [
            'without any error' => [
                new ProfilerItem('id', null, 'test'),
                new ProfilerItem('id', null, 'test'),
            ],
            'with error' => [
                new ProfilerItem(
                    'id',
                    null,
                    'test',
                    '',
                    null,
                    $error = new \Exception('error message')
                ),
                new ProfilerItem(
                    'id',
                    null,
                    'test',
                    '',
                    null,
                    new FormattedValue(
                        'error message',
                        FlattenException::createFromThrowable($error),
                        true
                    )
                ),
            ],
        ];
    }
}
