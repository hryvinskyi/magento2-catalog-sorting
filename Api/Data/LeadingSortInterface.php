<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Data;

/**
 * An engine-neutral sort a listing rule puts in front of the shopper's sort
 *
 * It names a search-index field, the direction to order it in, the value a document without the field sorts
 * as, and the engine type the field has where it is unmapped. It is valid by construction: the field is never
 * empty, and the direction and the type can only be one of their enum cases.
 *
 * Create it through the generated `Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterfaceFactory`. Its
 * `create()` keys are a published contract, exactly:
 *
 * - `field` (string) — the search-index field name
 * - `direction` ({@see SortDirection})
 * - `missingValue` (int|float|string|bool)
 * - `fieldType` ({@see FieldType})
 *
 * The factory accepts any array, so a misspelt key passes static analysis and fails only at runtime.
 *
 * @api
 */
interface LeadingSortInterface
{
    /**
     * Get the search-index field the listing is ordered by
     *
     * @return string Never empty
     */
    public function getField(): string;

    /**
     * Get the direction the field is ordered in
     *
     * @return SortDirection
     */
    public function getDirection(): SortDirection;

    /**
     * Get the value a document without the field sorts as
     *
     * @return int|float|string|bool
     */
    public function getMissingValue(): int|float|string|bool;

    /**
     * Get the engine type the field has where it is unmapped
     *
     * @return FieldType
     */
    public function getFieldType(): FieldType;
}
