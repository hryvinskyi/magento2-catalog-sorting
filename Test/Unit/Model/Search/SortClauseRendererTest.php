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
use Hryvinskyi\CatalogSorting\Model\Data\SortField;
use Hryvinskyi\CatalogSorting\Model\Search\SortClauseRenderer;
use PHPUnit\Framework\TestCase;

/**
 * A leading sort renders as one field clause with its direction, missing value and unmapped type; a sort field
 * renders as the same clause in the direction it is given; relevance renders as a descending score clause.
 */
class SortClauseRendererTest extends TestCase
{
    /**
     * @var SortClauseRenderer
     */
    private SortClauseRenderer $renderer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->renderer = new SortClauseRenderer();
    }

    /**
     * @return void
     */
    public function testRendersAnAscendingIntegerSort(): void
    {
        self::assertSame(
            ['sales_rank' => ['order' => 'asc', 'missing' => 0, 'unmapped_type' => 'integer']],
            $this->renderer->render(new LeadingSort('sales_rank', SortDirection::Ascending, 0, FieldType::Integer))
        );
    }

    /**
     * @return void
     */
    public function testRendersADescendingKeywordSort(): void
    {
        self::assertSame(
            ['brand' => ['order' => 'desc', 'missing' => 'zzz', 'unmapped_type' => 'keyword']],
            $this->renderer->render(new LeadingSort('brand', SortDirection::Descending, 'zzz', FieldType::Keyword))
        );
    }

    /**
     * @return void
     */
    public function testRendersASortFieldInTheDirectionItIsGiven(): void
    {
        $field = new SortField('sort_units_sold', 0, FieldType::Integer);

        self::assertSame(
            ['sort_units_sold' => ['order' => 'desc', 'missing' => 0, 'unmapped_type' => 'integer']],
            $this->renderer->renderField($field, SortDirection::Descending)
        );
        self::assertSame(
            ['sort_units_sold' => ['order' => 'asc', 'missing' => 0, 'unmapped_type' => 'integer']],
            $this->renderer->renderField($field, SortDirection::Ascending)
        );
    }

    /**
     * A sort field and a leading sort on the same values render the same clause.
     *
     * @return void
     */
    public function testRendersASortFieldInTheSameShapeAsALeadingSort(): void
    {
        self::assertSame(
            $this->renderer->render(new LeadingSort('sort_rating', SortDirection::Ascending, 0.5, FieldType::Float)),
            $this->renderer->renderField(new SortField('sort_rating', 0.5, FieldType::Float), SortDirection::Ascending)
        );
    }

    /**
     * @return void
     */
    public function testRendersRelevanceAsADescendingScore(): void
    {
        self::assertSame(['_score' => ['order' => 'desc']], $this->renderer->renderRelevance());
    }
}
