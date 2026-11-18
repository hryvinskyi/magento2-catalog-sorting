<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api;

use Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterface;

/**
 * A rule that puts its leading sort in front of the shopper's sort on storefront catalog listings
 *
 * Implementations are registered as items of the `rules` argument of
 * `Hryvinskyi\CatalogSorting\Model\Search\LeadingSorts` in `di.xml`, each with a `sortOrder`; a lower
 * `sortOrder` goes first. The rule owns its field in the listing: whatever sort on that field the request
 * already carries is dropped, and only this rule's own sort is put in front, when the rule is active.
 *
 * The rule only names a search-index field. A rule whose field the search index does not hold must bring
 * that field into the index itself.
 *
 * @api
 */
interface ListingSortRuleInterface
{
    /**
     * Get the sort this rule puts in front of the shopper's sort
     *
     * @return LeadingSortInterface
     */
    public function getLeadingSort(): LeadingSortInterface;

    /**
     * Check whether the rule applies to the current shopper in this store's listings
     *
     * @param int $storeId Store the listing is served for
     * @return bool
     */
    public function isActive(int $storeId): bool;
}
