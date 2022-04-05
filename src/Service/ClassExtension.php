<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Service;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ClassExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter(
                'genericClass',
                fn (string $class) => $this->formatGenericClass($class),
                ['is_safe' => ['html']]
            ),
        ];
    }

    private function formatGenericClass(string $class): string
    {
        if (empty($class)) {
            return $class;
        }

        try {
            if ($this->tryParseClassWithoutGenerics($class, $shortName)) {
                return sprintf(
                    '<small class="className">%s</small><strong>%s</strong>',
                    $this->replaceOnceFromEnd($shortName, '', $class),
                    $shortName
                );
            } elseif ($this->tryParseClassWithGenerics($class, $shortName, $genericArguments)) {
                if ($this->tryParseClassWithGenerics($genericArguments)) {
                    $generics = $this->formatGenericClass($genericArguments);
                } else {
                    $generics = array_map(
                        fn (string $genericArgument) => $this->formatGenericClass(trim($genericArgument)),
                        explode(',', $genericArguments)
                    );

                    $generics = implode(', ', $generics);
                }

                [$classWithoutGenerics] = explode('<', $class, 2);

                return sprintf(
                    '<small class="className">%s</small><strong>%s</strong>&lt;%s&gt;',
                    $this->replaceOnceFromEnd($shortName, '', $classWithoutGenerics),
                    $shortName,
                    $generics,
                );
            }

            return $class;
        } catch (\Throwable $e) {
            return $class;
        }
    }

    private function replaceOnceFromEnd(string $search, string $replace, string $value): string
    {
        $position = mb_strrpos($value, $search);
        if ($position === false) {
            return $value;
        }

        return substr_replace($value, $replace, $position, mb_strlen($search));
    }

    private function tryParseClassWithoutGenerics(string $class, ?string &$shortClassName = null): bool
    {
        if (preg_match('/^([A-Z][A-Za-z0-9]*\\\\)*([A-Z][A-Za-z]*?)$/', $class, $matches) === 1) {
            $shortClassName = array_pop($matches);

            return true;
        }

        return false;
    }

    private function tryParseClassWithGenerics(
        string $class,
        ?string &$shortClassName = null,
        ?string &$genericArguments = null
    ): bool {
        if (preg_match('/^([A-Z][A-Za-z0-9]*\\\\)*([A-Z][A-Za-z]*?)<(.*)>$/', $class, $matches) === 1) {
            $genericArguments = array_pop($matches);
            $shortClassName = array_pop($matches);

            return true;
        }

        return false;
    }
}
