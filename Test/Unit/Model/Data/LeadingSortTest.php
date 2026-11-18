<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Data;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSorting\Model\Data\LeadingSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A leading sort keeps the four values it was given, refuses a field that is empty or blank, and is built
 * from the same named keys the generated factory's create() receives.
 */
class LeadingSortTest extends TestCase
{
    /**
     * @return void
     */
    public function testHoldsTheValuesItWasGiven(): void
    {
        $sort = new LeadingSort('sales_rank', SortDirection::Ascending, 0, FieldType::Integer);

        self::assertSame('sales_rank', $sort->getField());
        self::assertSame(SortDirection::Ascending, $sort->getDirection());
        self::assertSame(0, $sort->getMissingValue());
        self::assertSame(FieldType::Integer, $sort->getFieldType());
    }

    /**
     * @return void
     */
    public function testKeepsAMissingValueOfAnotherScalarTypeAsGiven(): void
    {
        $sort = new LeadingSort('brand', SortDirection::Descending, 'zzz', FieldType::Keyword);

        self::assertSame(SortDirection::Descending, $sort->getDirection());
        self::assertSame('zzz', $sort->getMissingValue());
        self::assertSame(FieldType::Keyword, $sort->getFieldType());
    }

    /**
     * The keys below are the published factory contract: renaming a constructor parameter must fail here.
     *
     * @return void
     */
    public function testIsBuiltFromTheNamedKeysTheFactoryReceives(): void
    {
        $data = [
            'field' => 'is_featured',
            'direction' => SortDirection::Descending,
            'missingValue' => false,
            'fieldType' => FieldType::Boolean,
        ];

        $sort = new LeadingSort(...$data);

        self::assertSame('is_featured', $sort->getField());
        self::assertSame(SortDirection::Descending, $sort->getDirection());
        self::assertFalse($sort->getMissingValue());
        self::assertSame(FieldType::Boolean, $sort->getFieldType());
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

        new LeadingSort($field, SortDirection::Ascending, 0, FieldType::Integer);
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
