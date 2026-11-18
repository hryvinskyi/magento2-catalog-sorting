<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Search;

use Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterface;
use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterface;

/**
 * Turns an engine-neutral leading sort or sort field into a clause of the search engine's sort list
 *
 * Every clause is a one-key array: the key names what the documents are ordered by, the value holds how.
 *
 * @api
 */
interface SortClauseRendererInterface
{
    /**
     * Render one sort clause ordering the documents by the leading sort's field
     *
     * @param LeadingSortInterface $sort The sort to render
     * @return array<string, array<string, int|float|string|bool>> One clause, keyed by the field
     */
    public function render(LeadingSortInterface $sort): array;

    /**
     * Render one sort clause ordering the documents by the sort field, in the given direction
     *
     * @param SortFieldInterface $field The field to order by
     * @param SortDirection $direction The direction to order it in
     * @return array<string, array<string, int|float|string|bool>> One clause, keyed by the field
     */
    public function renderField(SortFieldInterface $field, SortDirection $direction): array;

    /**
     * Render the clause for the order the engine uses when a request names no sort: by relevance
     *
     * @return array<string, array<string, int|float|string|bool>> One clause
     */
    public function renderRelevance(): array;
}
