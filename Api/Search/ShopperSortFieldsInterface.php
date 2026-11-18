<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Search;

/**
 * Renders the clauses of a search query that sort by a registered shopper sort code on that code's index field
 *
 * @api
 */
interface ShopperSortFieldsInterface
{
    /**
     * Get the engine sort clauses with every clause on a registered code rendered on the code's field
     *
     * A rendered clause stays in its place and keeps the direction the shopper chose; every other clause is kept
     * as it is.
     *
     * @param array<int,mixed> $sorts The query's engine sort clauses, in order
     * @return array<int, mixed>
     */
    public function apply(array $sorts): array;
}
