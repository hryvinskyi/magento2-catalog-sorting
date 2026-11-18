<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api;

/**
 * A listing sort rule that applies only to some of the listings the rules serve
 *
 * For a search request it does not apply to, the rule is treated as inactive: its sort is not put in front, while
 * the rule still owns its field, so whatever sort on that field the request already carries is still dropped. This
 * is how a rule limits itself to, for example, category pages, possibly differently per store.
 *
 * @api
 */
interface RequestScopedListingSortRuleInterface extends ListingSortRuleInterface
{
    /**
     * Check whether the rule applies to the listing served by this search request in this store
     *
     * @param string $requestName Name of the search request, e.g. `catalog_view_container`
     * @param int $storeId Store the listing is served for
     * @return bool
     */
    public function appliesTo(string $requestName, int $storeId): bool;
}
