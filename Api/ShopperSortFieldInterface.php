<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api;

use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterface;

/**
 * A sort code a shopper can pick that is ordered by a search-index field of its own rather than by a product
 * attribute
 *
 * Implementations are registered as items of the `sortFields` argument of
 * `Hryvinskyi\CatalogSorting\Model\Search\ShopperSortFields` in `di.xml`; the item key is free text, the code comes
 * from {@see getCode()}. Whenever a search query sorts by the code, its clause is rendered on the field instead,
 * in the direction the shopper chose. A code must never be a product attribute code: the engine would then sort on
 * the attribute's own field under another key, and the code would not be recognised.
 *
 * Every registered item is built for every search query, so implementations stay cheap to construct.
 *
 * @api
 */
interface ShopperSortFieldInterface
{
    /**
     * Get the sort code as the listing toolbar sends it
     *
     * @return string Never empty
     */
    public function getCode(): string;

    /**
     * Get the search-index field the code is ordered by
     *
     * It may depend on the current shopper, but must not read the database: it runs for every search query that
     * sorts by the code.
     *
     * @return SortFieldInterface
     */
    public function getSortField(): SortFieldInterface;
}
