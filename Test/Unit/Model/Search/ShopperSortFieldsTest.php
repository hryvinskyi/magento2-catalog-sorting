<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Search;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Search\SortClauseRendererInterface;
use Hryvinskyi\CatalogSorting\Api\ShopperSortFieldInterface;
use Hryvinskyi\CatalogSorting\Model\Data\SortField;
use Hryvinskyi\CatalogSorting\Model\Search\ShopperSortFields;
use Hryvinskyi\CatalogSorting\Model\Search\SortClauseRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A clause keyed by a registered shopper sort code is rendered on the code's field in the same place and the same
 * direction (descending when the clause names none the engine knows); every other clause is kept as it is. The pool
 * refuses an item that is not a shopper sort field and a code registered twice.
 */
class ShopperSortFieldsTest extends TestCase
{
    private const NAME_CLAUSE = ['name' => ['order' => 'asc']];
    private const RELEVANCE_CLAUSE = ['_score' => ['order' => 'desc']];

    /**
     * @param string|null $order The clause's `order`, or null for a clause without one
     * @param string $expected The direction of the rendered clause
     * @return void
     * @dataProvider directionProvider
     */
    #[DataProvider('directionProvider')]
    public function testRendersARegisteredCodeOnItsFieldInItsPlaceKeepingTheDirection(
        ?string $order,
        string $expected
    ): void {
        $clause = $order === null ? ['top_sellers' => []] : ['top_sellers' => ['order' => $order]];

        self::assertSame(
            [
                self::NAME_CLAUSE,
                ['sort_units_sold' => ['order' => $expected, 'missing' => 0, 'unmapped_type' => 'integer']],
                self::RELEVANCE_CLAUSE,
            ],
            $this->service([$this->unitsSold()])->apply([self::NAME_CLAUSE, $clause, self::RELEVANCE_CLAUSE])
        );
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function directionProvider(): array
    {
        return [
            'ascending' => ['asc', 'asc'],
            'descending' => ['desc', 'desc'],
            'upper-case descending' => ['DESC', 'desc'],
            'upper-case ascending' => ['ASC', 'asc'],
            'no direction' => [null, 'desc'],
            'unknown direction' => ['sideways', 'desc'],
        ];
    }

    /**
     * @return void
     */
    public function testAClauseWhoseValueIsNotAnArrayIsOrderedDescending(): void
    {
        self::assertSame(
            [['sort_units_sold' => ['order' => 'desc', 'missing' => 0, 'unmapped_type' => 'integer']]],
            $this->service([$this->unitsSold()])->apply([['top_sellers' => 'asc-ish']])
        );
    }

    /**
     * @return void
     */
    public function testRendersEachOfTwoRegisteredCodesOnItsOwnField(): void
    {
        $service = $this->service([
            'sold' => $this->unitsSold(),
            'rated' => $this->sortField('rating', new SortField('sort_rating', 0.0, FieldType::Float)),
        ]);

        self::assertSame(
            [
                ['sort_rating' => ['order' => 'asc', 'missing' => 0.0, 'unmapped_type' => 'float']],
                ['sort_units_sold' => ['order' => 'desc', 'missing' => 0, 'unmapped_type' => 'integer']],
            ],
            $service->apply([['rating' => ['order' => 'asc']], ['top_sellers' => ['order' => 'desc']]])
        );
    }

    /**
     * @return void
     */
    public function testKeepsAClauseOnAnUnregisteredKeyAndDoesNotAskForAnyField(): void
    {
        $sortField = $this->createMock(ShopperSortFieldInterface::class);
        $sortField->method('getCode')->willReturn('top_sellers');
        $sortField->expects(self::never())->method('getSortField');
        $sorts = [self::NAME_CLAUSE, ['price_0_1' => ['order' => 'desc']]];

        self::assertSame($sorts, $this->service([$sortField])->apply($sorts));
    }

    /**
     * @return void
     */
    public function testKeepsAClauseThatIsNotAKeyedArrayInItsPlace(): void
    {
        $rendered = ['sort_units_sold' => ['order' => 'asc', 'missing' => 0, 'unmapped_type' => 'integer']];

        self::assertSame(
            ['_doc', [['top_sellers']], $rendered],
            $this->service([$this->unitsSold()])->apply(
                ['_doc', [['top_sellers']], ['top_sellers' => ['order' => 'asc']]]
            )
        );
    }

    /**
     * @return void
     */
    public function testReturnsTheClausesAsTheyAreWhenNoCodeIsRegistered(): void
    {
        $renderer = $this->createMock(SortClauseRendererInterface::class);
        $renderer->expects(self::never())->method('renderField');
        $sorts = [['top_sellers' => ['order' => 'desc']], self::NAME_CLAUSE];

        self::assertSame($sorts, (new ShopperSortFields($renderer, []))->apply($sorts));
    }

    /**
     * @return void
     */
    public function testRejectsAPoolItemThatIsNotAShopperSortFieldNamingItsKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shopper sort field "broken"');

        $this->service(['valid' => $this->unitsSold(), 'broken' => new \stdClass()]);
    }

    /**
     * @return void
     */
    public function testRejectsACodeRegisteredTwiceNamingTheCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The sort code "top_sellers" is registered twice');

        $this->service([
            'first' => $this->unitsSold(),
            'second' => $this->sortField('top_sellers', new SortField('sort_other', 0, FieldType::Long)),
        ]);
    }

    /**
     * Build the service with the given pool items and the real renderer
     *
     * @param array<mixed> $sortFields Pool items as di.xml would pass them
     * @return ShopperSortFields
     */
    private function service(array $sortFields): ShopperSortFields
    {
        return new ShopperSortFields(new SortClauseRenderer(), $sortFields);
    }

    /**
     * Build the shopper sort field of the code `top_sellers`, ordered by units sold
     *
     * @return ShopperSortFieldInterface&MockObject
     */
    private function unitsSold(): ShopperSortFieldInterface
    {
        return $this->sortField('top_sellers', new SortField('sort_units_sold', 0, FieldType::Integer));
    }

    /**
     * Build a shopper sort field with a fixed code and field
     *
     * @param string $code Sort code
     * @param SortField $field Field the code is ordered by
     * @return ShopperSortFieldInterface&MockObject
     */
    private function sortField(string $code, SortField $field): ShopperSortFieldInterface
    {
        $sortField = $this->createMock(ShopperSortFieldInterface::class);
        $sortField->method('getCode')->willReturn($code);
        $sortField->method('getSortField')->willReturn($field);

        return $sortField;
    }
}
