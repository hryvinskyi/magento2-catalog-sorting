<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Plugin\Search;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSorting\Api\Search\LeadingSortsInterface;
use Hryvinskyi\CatalogSorting\Api\Search\ShopperSortFieldsInterface;
use Hryvinskyi\CatalogSorting\Api\ShopperSortFieldInterface;
use Hryvinskyi\CatalogSorting\Model\Data\LeadingSort;
use Hryvinskyi\CatalogSorting\Model\Data\SortField;
use Hryvinskyi\CatalogSorting\Model\Search\LeadingSorts;
use Hryvinskyi\CatalogSorting\Model\Search\ShopperSortFields;
use Hryvinskyi\CatalogSorting\Model\Search\SortClauseRenderer;
use Hryvinskyi\CatalogSorting\Plugin\Search\LeadingSortsPlugin;
use Hryvinskyi\CatalogSorting\Test\Unit\Model\Search\RecordingListingSortRule;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\RequestInterface;
use Magento\OpenSearch\SearchAdapter\Mapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The plugin hands the finished query's sort clauses to the shopper sort fields first and their answer, with the
 * request, to the leading rules, and writes the rules' answer back into the query, leaving the rest of the query
 * as it was. A request outside the rules' listings still gets its shopper sort codes rendered.
 */
class LeadingSortsPluginTest extends TestCase
{
    private const NAME_CLAUSE = ['name' => ['order' => 'asc']];
    private const SALES_RANK_CLAUSE = ['sales_rank' => ['order' => 'asc']];
    private const SHOPPER_CODE_CLAUSE = ['top_sellers' => ['order' => 'desc']];
    private const RENDERED_SHOPPER_CLAUSE = [
        'sort_units_sold' => ['order' => 'desc', 'missing' => 0, 'unmapped_type' => 'integer'],
    ];
    private const OUT_OF_STOCK_CLAUSE = [
        'is_out_of_stock' => ['order' => 'asc', 'missing' => 0, 'unmapped_type' => 'integer'],
    ];

    /**
     * @var ShopperSortFieldsInterface&MockObject
     */
    private $shopperSortFields;

    /**
     * @var LeadingSortsInterface&MockObject
     */
    private $leadingSorts;

    /**
     * @var RequestInterface&MockObject
     */
    private $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->shopperSortFields = $this->createMock(ShopperSortFieldsInterface::class);
        $this->leadingSorts = $this->createMock(LeadingSortsInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    /**
     * @return void
     */
    public function testWritesTheServicesAnswerIntoTheQuerysSortAndKeepsTheRestOfTheQuery(): void
    {
        $this->shopperSortFields->method('apply')->willReturnArgument(0);
        $this->leadingSorts->expects(self::once())
            ->method('apply')
            ->with(self::identicalTo($this->request), self::identicalTo([self::NAME_CLAUSE]))
            ->willReturn([self::SALES_RANK_CLAUSE, self::NAME_CLAUSE]);

        $query = [
            'index' => 'products_4',
            'body' => ['from' => 0, 'size' => 12, 'sort' => [self::NAME_CLAUSE], 'query' => ['match_all' => []]],
        ];

        self::assertSame(
            [
                'index' => 'products_4',
                'body' => [
                    'from' => 0,
                    'size' => 12,
                    'sort' => [self::SALES_RANK_CLAUSE, self::NAME_CLAUSE],
                    'query' => ['match_all' => []],
                ],
            ],
            $this->plugin()->afterBuildQuery($this->createMock(Mapper::class), $query, $this->request)
        );
    }

    /**
     * The leading rules receive what the shopper sort fields made of the clauses, not the original clauses.
     *
     * @return void
     */
    public function testRendersTheShopperSortFieldsBeforeTheLeadingRules(): void
    {
        $this->shopperSortFields->expects(self::once())
            ->method('apply')
            ->with(self::identicalTo([self::SHOPPER_CODE_CLAUSE]))
            ->willReturn([self::RENDERED_SHOPPER_CLAUSE]);
        $this->leadingSorts->expects(self::once())
            ->method('apply')
            ->with(self::identicalTo($this->request), self::identicalTo([self::RENDERED_SHOPPER_CLAUSE]))
            ->willReturn([self::SALES_RANK_CLAUSE, self::RENDERED_SHOPPER_CLAUSE]);

        self::assertSame(
            ['body' => ['sort' => [self::SALES_RANK_CLAUSE, self::RENDERED_SHOPPER_CLAUSE]]],
            $this->plugin()->afterBuildQuery(
                $this->createMock(Mapper::class),
                ['body' => ['sort' => [self::SHOPPER_CODE_CLAUSE]]],
                $this->request
            )
        );
    }

    /**
     * Clauses keyed by position are handed over as a list.
     *
     * @return void
     */
    public function testHandsTheClausesOverAsAList(): void
    {
        $this->shopperSortFields->expects(self::once())
            ->method('apply')
            ->with(self::identicalTo([self::NAME_CLAUSE]))
            ->willReturnArgument(0);
        $this->leadingSorts->expects(self::once())
            ->method('apply')
            ->with(self::identicalTo($this->request), self::identicalTo([self::NAME_CLAUSE]))
            ->willReturn([self::NAME_CLAUSE]);

        $this->plugin()->afterBuildQuery(
            $this->createMock(Mapper::class),
            ['body' => ['sort' => [3 => self::NAME_CLAUSE]]],
            $this->request
        );
    }

    /**
     * @return void
     */
    public function testLeavesAQueryWithoutASortListAsItIs(): void
    {
        $this->shopperSortFields->expects(self::never())->method('apply');
        $this->leadingSorts->expects(self::never())->method('apply');
        $query = ['body' => ['query' => ['match_all' => []]]];

        self::assertSame(
            $query,
            $this->plugin()->afterBuildQuery($this->createMock(Mapper::class), $query, $this->request)
        );
    }

    /**
     * @return void
     */
    public function testLeavesAQueryWithoutABodyAsItIs(): void
    {
        $this->shopperSortFields->expects(self::never())->method('apply');
        $this->leadingSorts->expects(self::never())->method('apply');

        self::assertSame(
            ['index' => 'products_4'],
            $this->plugin()->afterBuildQuery(
                $this->createMock(Mapper::class),
                ['index' => 'products_4'],
                $this->request
            )
        );
    }

    /**
     * With the real services: on a listing the rules serve, the active rule goes in front of the rendered shopper
     * clause.
     *
     * @return void
     */
    public function testOnAListedRequestTheRuleGoesInFrontOfTheRenderedShopperClause(): void
    {
        $plugin = $this->pluginWithRealServices();

        self::assertSame(
            ['body' => ['sort' => [self::OUT_OF_STOCK_CLAUSE, self::RENDERED_SHOPPER_CLAUSE]]],
            $plugin->afterBuildQuery(
                $this->createMock(Mapper::class),
                ['body' => ['sort' => [self::SHOPPER_CODE_CLAUSE]]],
                $this->requestNamed('catalog_view_container')
            )
        );
    }

    /**
     * With the real services: a request that is not on the rules' allow-list still gets its shopper sort code
     * rendered, since a clause left on the bare code would fail the query, and gets no rule clause.
     *
     * @return void
     */
    public function testARequestOffTheRulesListStillGetsItsShopperCodeRenderedAndNoRule(): void
    {
        $plugin = $this->pluginWithRealServices();

        self::assertSame(
            ['body' => ['sort' => [self::RENDERED_SHOPPER_CLAUSE, self::NAME_CLAUSE]]],
            $plugin->afterBuildQuery(
                $this->createMock(Mapper::class),
                ['body' => ['sort' => [self::SHOPPER_CODE_CLAUSE, self::NAME_CLAUSE]]],
                $this->requestNamed('graphql_product_search')
            )
        );
    }

    /**
     * Build the plugin under test on the service doubles
     *
     * @return LeadingSortsPlugin
     */
    private function plugin(): LeadingSortsPlugin
    {
        return new LeadingSortsPlugin($this->shopperSortFields, $this->leadingSorts);
    }

    /**
     * Build the plugin on the real services, with one shopper sort code and one active rule on the category listing
     *
     * @return LeadingSortsPlugin
     */
    private function pluginWithRealServices(): LeadingSortsPlugin
    {
        $renderer = new SortClauseRenderer();
        $shopperSortField = $this->createMock(ShopperSortFieldInterface::class);
        $shopperSortField->method('getCode')->willReturn('top_sellers');
        $shopperSortField->method('getSortField')->willReturn(
            new SortField('sort_units_sold', 0, FieldType::Integer)
        );
        $scope = $this->createMock(ScopeInterface::class);
        $scope->method('getId')->willReturn(1);
        $scopeResolver = $this->createMock(ScopeResolverInterface::class);
        $scopeResolver->method('getScope')->willReturn($scope);

        return new LeadingSortsPlugin(
            new ShopperSortFields($renderer, ['topSellers' => $shopperSortField]),
            new LeadingSorts(
                $scopeResolver,
                $renderer,
                [
                    new RecordingListingSortRule(
                        new LeadingSort('is_out_of_stock', SortDirection::Ascending, 0, FieldType::Integer),
                        true
                    ),
                ],
                ['catalog_view_container']
            )
        );
    }

    /**
     * Build a search request with the given name, made for store 1
     *
     * @param string $name Request name
     * @return RequestInterface&MockObject
     */
    private function requestNamed(string $name): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getName')->willReturn($name);
        $request->method('getDimensions')->willReturn(['scope' => new Dimension('scope', '1')]);

        return $request;
    }
}
