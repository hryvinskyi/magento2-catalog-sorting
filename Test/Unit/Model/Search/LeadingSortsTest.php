<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Search;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSorting\Model\Data\LeadingSort;
use Hryvinskyi\CatalogSorting\Model\Search\LeadingSorts;
use Hryvinskyi\CatalogSorting\Model\Search\SortClauseRenderer;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * On an allow-listed request with a resolvable store, active rules' clauses go first in pool order and the
 * request's other clauses follow in their order; no clause on a rule's field survives except the active rule's
 * own; a leading clause with nothing after it is followed by relevance. A rule limited to some requests is inactive
 * on the others and in the other stores, yet still owns its field there. Any other request comes back identical.
 */
class LeadingSortsTest extends TestCase
{
    private const REQUEST = 'catalog_view_container';
    private const OTHER_LISTED_REQUEST = 'quick_search_container';
    private const DIMENSION_STORE = 1;
    private const RESOLVED_STORE = 4;

    private const NAME_CLAUSE = ['name' => ['order' => 'asc']];
    private const PRICE_CLAUSE = ['price_0_1' => ['order' => 'desc']];
    private const SALES_RANK_CLAUSE = [
        'sales_rank' => ['order' => 'asc', 'missing' => 0, 'unmapped_type' => 'integer'],
    ];
    private const FEATURED_CLAUSE = [
        'is_featured' => ['order' => 'desc', 'missing' => false, 'unmapped_type' => 'boolean'],
    ];
    private const RELEVANCE_CLAUSE = ['_score' => ['order' => 'desc']];

    /**
     * @var ScopeResolverInterface&MockObject
     */
    private $scopeResolver;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->scopeResolver = $this->createMock(ScopeResolverInterface::class);
        $this->scopeResolver->method('getScope')->willReturnMap([
            [self::DIMENSION_STORE, $this->scope(self::RESOLVED_STORE)],
        ]);
    }

    /**
     * @return void
     */
    public function testPutsAnActiveRulesClauseInFrontOfTheRequestsClauses(): void
    {
        $service = $this->service([$this->salesRankRule(true)]);

        self::assertSame(
            [self::SALES_RANK_CLAUSE, self::NAME_CLAUSE, self::PRICE_CLAUSE],
            $service->apply($this->request(), [self::NAME_CLAUSE, self::PRICE_CLAUSE])
        );
    }

    /**
     * @return void
     */
    public function testPutsTwoActiveRulesInFrontInPoolOrder(): void
    {
        $service = $this->service([$this->featuredRule(true), $this->salesRankRule(true)]);

        self::assertSame(
            [self::FEATURED_CLAUSE, self::SALES_RANK_CLAUSE, self::NAME_CLAUSE],
            $service->apply($this->request(), [self::NAME_CLAUSE])
        );
    }

    /**
     * A clause the request already carries on an inactive rule's field would still order by that field.
     *
     * @return void
     */
    public function testDropsTheRequestsClauseOnAnInactiveRulesFieldAndKeepsTheOthersInOrder(): void
    {
        $service = $this->service([$this->salesRankRule(false)]);

        self::assertSame(
            [self::NAME_CLAUSE, self::PRICE_CLAUSE],
            $service->apply(
                $this->request(),
                [self::NAME_CLAUSE, ['sales_rank' => ['order' => 'desc']], self::PRICE_CLAUSE]
            )
        );
    }

    /**
     * Two active rules on the same field yield one clause, the first rule's; the second is not asked.
     *
     * @return void
     */
    public function testKeepsOnlyTheFirstActiveRuleWhenTwoRulesShareAField(): void
    {
        $descendingSalesRank = new RecordingListingSortRule(
            new LeadingSort('sales_rank', SortDirection::Descending, 0, FieldType::Integer),
            true
        );
        $service = $this->service([$this->salesRankRule(true), $descendingSalesRank]);

        self::assertSame(
            [self::SALES_RANK_CLAUSE, self::NAME_CLAUSE],
            $service->apply($this->request(), [self::NAME_CLAUSE])
        );
        self::assertSame([], $descendingSalesRank->askedStoreIds());
    }

    /**
     * A clause that is not keyed by a field is not the rule's to judge and stays where it is.
     *
     * @return void
     */
    public function testKeepsAClauseOfAnotherShapeInItsPlace(): void
    {
        $service = $this->service([$this->salesRankRule(true)]);

        self::assertSame(
            [self::SALES_RANK_CLAUSE, '_doc', self::NAME_CLAUSE],
            $service->apply($this->request(), ['_doc', self::NAME_CLAUSE])
        );
    }

    /**
     * @return void
     */
    public function testKeepsExactlyOneClauseOnAnActiveRulesFieldInFront(): void
    {
        $service = $this->service([$this->salesRankRule(true)]);

        self::assertSame(
            [self::SALES_RANK_CLAUSE, self::NAME_CLAUSE],
            $service->apply($this->request(), [['sales_rank' => ['order' => 'desc']], self::NAME_CLAUSE])
        );
    }

    /**
     * @return void
     */
    public function testLeavesARequestOffTheListIdenticalWithoutConsultingAnyRule(): void
    {
        $rule = $this->salesRankRule(true);
        $this->scopeResolver->expects(self::never())->method('getScope');
        $sorts = [self::NAME_CLAUSE, ['sales_rank' => ['order' => 'desc']]];

        self::assertSame($sorts, $this->service([$rule])->apply($this->request('other_container'), $sorts));
        self::assertFalse($rule->wasConsulted());
    }

    /**
     * @return void
     */
    public function testLeavesARequestWithoutDimensionIdentical(): void
    {
        $sorts = [self::NAME_CLAUSE];

        self::assertSame(
            $sorts,
            $this->service([$this->salesRankRule(true)])->apply($this->request(self::REQUEST, []), $sorts)
        );
    }

    /**
     * @return void
     */
    public function testLeavesTheRequestIdenticalWhenTheStoreCannotBeResolved(): void
    {
        $scopeResolver = $this->createMock(ScopeResolverInterface::class);
        $scopeResolver->method('getScope')->willThrowException(
            new NoSuchEntityException(new Phrase('The store that was requested wasn\'t found.'))
        );
        $service = new LeadingSorts(
            $scopeResolver,
            new SortClauseRenderer(),
            [$this->salesRankRule(true)],
            [self::REQUEST]
        );
        $sorts = [self::NAME_CLAUSE];

        self::assertSame($sorts, $service->apply($this->request(), $sorts));
    }

    /**
     * @return void
     */
    public function testAsksTheRuleWithTheResolvedStoreId(): void
    {
        $rule = $this->salesRankRule(true);

        $this->service([$rule])->apply($this->request(), [self::NAME_CLAUSE]);

        self::assertSame([self::RESOLVED_STORE], $rule->askedStoreIds());
    }

    /**
     * A dimension may carry the store itself rather than its id.
     *
     * @return void
     */
    public function testResolvesAStoreGivenAsTheDimensionValue(): void
    {
        $rule = $this->salesRankRule(true);

        $this->service([$rule])->apply(
            $this->request(self::REQUEST, [new Dimension('scope', $this->scope(self::DIMENSION_STORE))]),
            [self::NAME_CLAUSE]
        );

        self::assertSame([self::RESOLVED_STORE], $rule->askedStoreIds());
    }

    /**
     * @return void
     */
    public function testFollowsTheRuleClauseWithRelevanceWhenTheRequestHasNoSort(): void
    {
        self::assertSame(
            [self::SALES_RANK_CLAUSE, self::RELEVANCE_CLAUSE],
            $this->service([$this->salesRankRule(true)])->apply($this->request(), [])
        );
    }

    /**
     * @return void
     */
    public function testFollowsTheRuleClauseWithRelevanceWhenTheOnlyClauseWasOnTheRulesField(): void
    {
        self::assertSame(
            [self::SALES_RANK_CLAUSE, self::RELEVANCE_CLAUSE],
            $this->service([$this->salesRankRule(true)])->apply(
                $this->request(),
                [['sales_rank' => ['order' => 'desc']]]
            )
        );
    }

    /**
     * @return void
     */
    public function testAddsNoRelevanceWhenNoRuleIsActiveAndTheRequestHasNoSort(): void
    {
        self::assertSame([], $this->service([$this->salesRankRule(false)])->apply($this->request(), []));
    }

    /**
     * @return void
     */
    public function testPutsARequestScopedRuleInFrontOfARequestAndStoreItAppliesTo(): void
    {
        $rule = $this->scopedSalesRankRule([[self::REQUEST, self::RESOLVED_STORE]]);

        self::assertSame(
            [self::SALES_RANK_CLAUSE, self::NAME_CLAUSE],
            $this->service([$rule])->apply($this->request(), [self::NAME_CLAUSE])
        );
        self::assertSame([[self::REQUEST, self::RESOLVED_STORE]], $rule->askedScopes());
    }

    /**
     * On a handled request the rule does not apply to, the rule is not asked whether it is active, and the
     * request's own clause on the rule's field is still dropped.
     *
     * @return void
     */
    public function testARequestScopedRuleIsInactiveForAnotherRequestNameButStillOwnsItsField(): void
    {
        $rule = $this->scopedSalesRankRule([[self::REQUEST, self::RESOLVED_STORE]]);

        self::assertSame(
            [self::NAME_CLAUSE],
            $this->service([$rule])->apply(
                $this->request(self::OTHER_LISTED_REQUEST),
                [['sales_rank' => ['order' => 'desc']], self::NAME_CLAUSE]
            )
        );
        self::assertSame([[self::OTHER_LISTED_REQUEST, self::RESOLVED_STORE]], $rule->askedScopes());
        self::assertSame([], $rule->askedStoreIds());
    }

    /**
     * @return void
     */
    public function testARequestScopedRuleIsInactiveInAStoreItDoesNotApplyTo(): void
    {
        $rule = $this->scopedSalesRankRule([[self::REQUEST, self::RESOLVED_STORE + 1]]);

        self::assertSame(
            [self::NAME_CLAUSE],
            $this->service([$rule])->apply($this->request(), [self::NAME_CLAUSE])
        );
    }

    /**
     * A request-scoped rule that does not apply leaves the field to the next rule on it.
     *
     * @return void
     */
    public function testAnInapplicableRequestScopedRuleLeavesItsFieldToTheNextRule(): void
    {
        $descendingSalesRank = new RecordingListingSortRule(
            new LeadingSort('sales_rank', SortDirection::Descending, 0, FieldType::Integer),
            true
        );

        self::assertSame(
            [['sales_rank' => ['order' => 'desc', 'missing' => 0, 'unmapped_type' => 'integer']], self::NAME_CLAUSE],
            $this->service([$this->scopedSalesRankRule([]), $descendingSalesRank])
                ->apply($this->request(), [self::NAME_CLAUSE])
        );
    }

    /**
     * @return void
     */
    public function testLeavesTheRequestIdenticalWithoutRules(): void
    {
        $sorts = [self::NAME_CLAUSE];

        self::assertSame($sorts, $this->service([])->apply($this->request(), $sorts));
    }

    /**
     * @return void
     */
    public function testRejectsAPoolItemThatIsNotARule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listing sort rule "broken"');

        new LeadingSorts(
            $this->scopeResolver,
            new SortClauseRenderer(),
            ['valid' => $this->salesRankRule(true), 'broken' => new \stdClass()],
            [self::REQUEST]
        );
    }

    /**
     * @return void
     */
    public function testRejectsAnEmptyRequestName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search request name "blank"');

        new LeadingSorts($this->scopeResolver, new SortClauseRenderer(), [], ['blank' => ' ']);
    }

    /**
     * Build the service with the given rules, handling the allow-listed request name
     *
     * @param list<RecordingListingSortRule> $rules Rules in pool order
     * @return LeadingSorts
     */
    private function service(array $rules): LeadingSorts
    {
        return new LeadingSorts(
            $this->scopeResolver,
            new SortClauseRenderer(),
            $rules,
            ['catalog_view' => self::REQUEST, 'quick_search' => self::OTHER_LISTED_REQUEST]
        );
    }

    /**
     * Build an active rule ordering by sales rank, ascending, that applies only where it is told to
     *
     * @param list<array{string, int}> $appliesTo Request name and store pairs the rule applies to
     * @return RecordingRequestScopedListingSortRule
     */
    private function scopedSalesRankRule(array $appliesTo): RecordingRequestScopedListingSortRule
    {
        return new RecordingRequestScopedListingSortRule(
            new LeadingSort('sales_rank', SortDirection::Ascending, 0, FieldType::Integer),
            true,
            $appliesTo
        );
    }

    /**
     * Build a rule ordering by sales rank, ascending
     *
     * @param bool $active Whether the rule applies
     * @return RecordingListingSortRule
     */
    private function salesRankRule(bool $active): RecordingListingSortRule
    {
        return new RecordingListingSortRule(
            new LeadingSort('sales_rank', SortDirection::Ascending, 0, FieldType::Integer),
            $active
        );
    }

    /**
     * Build a rule putting featured products first
     *
     * @param bool $active Whether the rule applies
     * @return RecordingListingSortRule
     */
    private function featuredRule(bool $active): RecordingListingSortRule
    {
        return new RecordingListingSortRule(
            new LeadingSort('is_featured', SortDirection::Descending, false, FieldType::Boolean),
            $active
        );
    }

    /**
     * Build a search request
     *
     * @param string $name Request name
     * @param list<Dimension>|null $dimensions Dimensions; null for one naming the dimension store by id
     * @return RequestInterface&MockObject
     */
    private function request(string $name = self::REQUEST, ?array $dimensions = null): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getName')->willReturn($name);
        $request->method('getDimensions')->willReturn(
            $dimensions ?? ['scope' => new Dimension('scope', (string)self::DIMENSION_STORE)]
        );

        return $request;
    }

    /**
     * Build a scope with the given id
     *
     * @param int $id Scope id
     * @return ScopeInterface&MockObject
     */
    private function scope(int $id): ScopeInterface
    {
        $scope = $this->createMock(ScopeInterface::class);
        $scope->method('getId')->willReturn($id);

        return $scope;
    }
}
