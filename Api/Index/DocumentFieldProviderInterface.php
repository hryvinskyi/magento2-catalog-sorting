<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Index;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;

/**
 * Writes numeric fields of its own into the product search documents while the search index is built
 *
 * Implementations are registered as items of the `providers` argument of
 * `Hryvinskyi\CatalogSorting\Model\Index\DocumentFields` in `di.xml`. The search indexer asks every provider once
 * per batch of products of one store, so a provider answers a batch with one query at most and never with one query
 * per product. Nothing here runs on the storefront request path.
 *
 * @api
 */
interface DocumentFieldProviderInterface
{
    /**
     * Get every field this provider may write, with its type in the index mapping
     *
     * The mapping of a new index is built from it, for every store, so the answer does not depend on any store's
     * settings: a field the provider writes only in some stores is declared all the same. Usually static data; a
     * provider with one field per customer group may read the group list. It is asked when an index is created,
     * never per document batch.
     *
     * @return array<string, FieldType> Field name => mapping type
     */
    public function getFieldTypes(): array;

    /**
     * Get the field values of the given products in the given store
     *
     * Only the given products are answered; a product the provider has no value for is left out. Whether a
     * provider writes values in a store where its sort method is switched off is the provider's own decision; one
     * that writes nothing there returns an empty array without querying.
     *
     * @param int $storeId Store whose documents are being built
     * @param list<int> $productIds Products of the batch, never empty
     * @return array<int, array<string, int|float>> Product id => field name => value
     */
    public function getValues(int $storeId, array $productIds): array;
}
