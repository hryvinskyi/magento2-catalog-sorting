<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Data;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Model\Data\SortField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A sort field keeps the three values it was given, refuses a field that is empty or blank, and is built from the
 * same named keys the generated factory's create() receives.
 */
class SortFieldTest extends TestCase
{
    /**
     * @return void
     */
    public function testHoldsTheValuesItWasGiven(): void
    {
        $field = new SortField('sort_units_sold', 0, FieldType::Integer);

        self::assertSame('sort_units_sold', $field->getField());
        self::assertSame(0, $field->getMissingValue());
        self::assertSame(FieldType::Integer, $field->getFieldType());
    }

    /**
     * @return void
     */
    public function testKeepsAMissingValueOfAnotherScalarTypeAsGiven(): void
    {
        $field = new SortField('sort_rating', 1.5, FieldType::Float);

        self::assertSame(1.5, $field->getMissingValue());
        self::assertSame(FieldType::Float, $field->getFieldType());
    }

    /**
     * The keys below are the published factory contract: renaming a constructor parameter must fail here.
     *
     * @return void
     */
    public function testIsBuiltFromTheNamedKeysTheFactoryReceives(): void
    {
        $data = [
            'field' => 'sort_views',
            'missingValue' => 0,
            'fieldType' => FieldType::Long,
        ];

        $field = new SortField(...$data);

        self::assertSame('sort_views', $field->getField());
        self::assertSame(0, $field->getMissingValue());
        self::assertSame(FieldType::Long, $field->getFieldType());
    }

    /**
     * @param string $field A field with nothing to sort by
     * @return void
     * @dataProvider emptyFieldProvider
     */
    #[DataProvider('emptyFieldProvider')]
    public function testRejectsAFieldWithNothingInIt(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the field is empty');

        new SortField($field, 0, FieldType::Integer);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyFieldProvider(): array
    {
        return [
            'empty' => [''],
            'blank' => [" \t\n "],
        ];
    }
}
