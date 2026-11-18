<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterfaceFactory;
use Hryvinskyi\CatalogSorting\Model\ConfiguredShopperSortField;
use Hryvinskyi\CatalogSorting\Model\Data\SortField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A configured shopper sort field answers with its code and builds its sort field once, on first use, from the
 * values it was configured with; a blank code or an unknown field type is refused when it is built.
 */
class ConfiguredShopperSortFieldTest extends TestCase
{
    /**
     * @var SortFieldInterfaceFactory&MockObject
     */
    private $sortFieldFactory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->sortFieldFactory = $this->createMock(SortFieldInterfaceFactory::class);
    }

    /**
     * @return void
     */
    public function testAnswersWithItsCodeWithoutBuildingTheSortField(): void
    {
        $this->sortFieldFactory->expects(self::never())->method('create');

        self::assertSame('top_sellers', $this->sortField('top_sellers', 'sort_units_sold', 'integer')->getCode());
    }

    /**
     * The factory double builds the real object from the keys it receives, so a misspelt key fails here instead
     * of on every storefront listing sorted by the code.
     *
     * @return void
     */
    public function testBuildsTheSortFieldFromItsConfiguration(): void
    {
        $this->sortFieldFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(self::buildSortField(...));

        $field = $this->sortField('rating', 'sort_rating', 'float', 2.5)->getSortField();

        self::assertSame('sort_rating', $field->getField());
        self::assertSame(2.5, $field->getMissingValue());
        self::assertSame(FieldType::Float, $field->getFieldType());
    }

    /**
     * @return void
     */
    public function testAMissingValueThatIsNotConfiguredIsZero(): void
    {
        $this->sortFieldFactory->method('create')->willReturnCallback(self::buildSortField(...));

        $sortField = new ConfiguredShopperSortField(
            $this->sortFieldFactory,
            'top_sellers',
            'sort_units_sold',
            'integer'
        );

        self::assertSame(0, $sortField->getSortField()->getMissingValue());
    }

    /**
     * @return void
     */
    public function testBuildsTheSortFieldOnceAndKeepsIt(): void
    {
        $this->sortFieldFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(self::buildSortField(...));
        $sortField = $this->sortField('top_sellers', 'sort_units_sold', 'integer');

        self::assertSame($sortField->getSortField(), $sortField->getSortField());
    }

    /**
     * @param string $code A code with nothing in it
     * @return void
     * @dataProvider emptyCodeProvider
     */
    #[DataProvider('emptyCodeProvider')]
    public function testRejectsACodeWithNothingInIt(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the code is empty');

        $this->sortField($code, 'sort_units_sold', 'integer');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyCodeProvider(): array
    {
        return [
            'empty' => [''],
            'blank' => ['  '],
        ];
    }

    /**
     * @return void
     */
    public function testRejectsAFieldTypeTheEngineDoesNotKnowNamingTheCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shopper sort field "top_sellers" names the field type "int"');

        $this->sortField('top_sellers', 'sort_units_sold', 'int');
    }

    /**
     * Build a real sort field from the array the generated factory's create() receives, the way the object
     * manager does: every key is a constructor parameter name, so an unknown key is an error
     *
     * @param array{field: string, missingValue: int|float|string|bool, fieldType: FieldType} $data Arguments
     *     keyed by constructor parameter name
     * @return SortField
     */
    private static function buildSortField(array $data): SortField
    {
        return new SortField(...$data);
    }

    /**
     * Build the shopper sort field under test
     *
     * @param string $code Sort code
     * @param string $field Search-index field
     * @param string $fieldType Engine type name
     * @param int|float $missingValue Value a document without the field sorts as
     * @return ConfiguredShopperSortField
     */
    private function sortField(
        string $code,
        string $field,
        string $fieldType,
        int|float $missingValue = 0
    ): ConfiguredShopperSortField {
        return new ConfiguredShopperSortField($this->sortFieldFactory, $code, $field, $fieldType, $missingValue);
    }
}
