<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Service;

use Lmc\Cqrs\Types\Formatter\ProfilerFormatterInterface;
use Lmc\Cqrs\Types\ValueObject\FormattedValue;
use Lmc\Cqrs\Types\ValueObject\ProfilerItem;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class ErrorProfilerFormatter implements ProfilerFormatterInterface
{
    public function formatItem(ProfilerItem $item): ProfilerItem
    {
        if ($item->getError() instanceof \Throwable) {
            $item->setError($this->formatError($item->getError()));
        }

        return $item;
    }

    /** @phpstan-return FormattedValue<string, FlattenException> */
    private function formatError(\Throwable $error): FormattedValue
    {
        $flatten = FlattenException::createFromThrowable($error);

        return new FormattedValue($error->getMessage(), $flatten, true);
    }
}
