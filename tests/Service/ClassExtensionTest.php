<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Service;

use PHPUnit\Framework\TestCase;

class ClassExtensionTest extends TestCase
{
    private ClassExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ClassExtension();
    }

    /**
     * @test
     * @dataProvider provideClass
     */
    public function shouldFormatClassString(string $string, string $expected): void
    {
        $filter = $this->extension->getFilters()[0];
        $this->assertSame('genericClass', $filter->getName());

        $result = call_user_func($filter->getCallable(), $string);

        $this->assertSame($expected, $result);
    }

    public function provideClass(): array
    {
        return [
            // input, expected
            'empty' => ['', ''],
            'not a class' => ['foo', 'foo'],
            'class without generics' => [
                'Root\Service\ServiceName',
                '<small class="className">Root\Service\</small><strong>ServiceName</strong>',
            ],
            'class with generic parameter' => [
                'Root\Service\Generic\ServiceName<T>',
                '<small class="className">Root\Service\Generic\</small><strong>ServiceName</strong>&lt;<small class="className"></small><strong>T</strong>&gt;',
            ],
            'generic class' => [
                'Root\Service\Generic\ServiceName<Root\Value\Foo>',
                '<small class="className">Root\Service\Generic\</small><strong>ServiceName</strong>&lt;<small class="className">Root\Value\</small><strong>Foo</strong>&gt;',
            ],
            'generic class with multiple generic arguments' => [
                'Root\Service\Generic\ServiceName<Root\Value\Foo, Root\Value\Bar>',
                '<small class="className">Root\Service\Generic\</small><strong>ServiceName</strong>&lt;<small class="className">Root\Value\</small><strong>Foo</strong>, <small class="className">Root\Value\</small><strong>Bar</strong>&gt;',
            ],
            'generic class with generic class argument' => [
                'Root\Service\Generic\ServiceName<Root\Value\Foo<Root\Value\Bar>>',
                '<small class="className">Root\Service\Generic\</small><strong>ServiceName</strong>&lt;<small class="className">Root\Value\</small><strong>Foo</strong>&lt;<small class="className">Root\Value\</small><strong>Bar</strong>&gt;&gt;',
            ],
            'generic class with duplicity in name' => [
                'Root\Service\Generic\ServiceName<Root\Value\Foo\Foo>',
                '<small class="className">Root\Service\Generic\</small><strong>ServiceName</strong>&lt;<small class="className">Root\Value\Foo\</small><strong>Foo</strong>&gt;',
            ],
            'generic class with multiple duplicities' => [
                'Root\Service\Generic\Foo<Root\Value\Foo<Root\Foo\Foo, Root\Foo\Foo>>',
                '<small class="className">Root\Service\Generic\</small><strong>Foo</strong>&lt;<small class="className">Root\Value\</small><strong>Foo</strong>&lt;<small class="className">Root\Foo\</small><strong>Foo</strong>, <small class="className">Root\Foo\</small><strong>Foo</strong>&gt;&gt;',
            ],
        ];
    }
}
