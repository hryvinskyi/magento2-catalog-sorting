<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Data;

/**
 * The search-index field a shopper's sort code is rendered to
 *
 * It names the field, the value a document without the field sorts as, and the engine type the field has where it
 * is unmapped. It carries no direction: the direction is the one the shopper chose. It is valid by construction:
 * the field is never empty and the type can only be one of its enum cases.
 *
 * Create it through the generated `Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterfaceFactory`. Its `create()`
 * keys are a published contract, exactly:
 *
 * - `field` (string) — the search-index field name
 * - `missingValue` (int|float|string|bool)
 * - `fieldType` ({@see FieldType})
 *
 * The factory accepts any array, so a misspelt key passes static analysis and fails only at runtime.
 *
 * @api
 */
interface SortFieldInterface
{
    /**
     * Get the search-index field the listing is ordered by
     *
     * @return string Never empty
     */
    public function getField(): string;

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
