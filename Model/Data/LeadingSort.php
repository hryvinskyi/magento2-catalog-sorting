<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Model\Data;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterface;
use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;

/**
 * Immutable leading sort, valid by construction
 *
 * The constructor's parameter names are the keys rule modules pass to the generated factory's `create()`;
 * renaming one breaks every caller at runtime, not at compile time.
 */
class LeadingSort implements LeadingSortInterface
{
    /**
     * @param string $field Search-index field name; must not be empty or blank
     * @param SortDirection $direction Direction the field is ordered in
     * @param int|float|string|bool $missingValue Value a document without the field sorts as
     * @param FieldType $fieldType Engine type the field has where it is unmapped
     * @throws \InvalidArgumentException When the field is empty or blank
     */
    public function __construct(
        private readonly string $field,
        private readonly SortDirection $direction,
        private readonly int|float|string|bool $missingValue,
        private readonly FieldType $fieldType
    ) {
        if (trim($field) === '') {
            throw new \InvalidArgumentException('A leading sort needs a field to sort by; the field is empty.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @inheritDoc
     */
    public function getDirection(): SortDirection
    {
        return $this->direction;
    }

    /**
     * @inheritDoc
     */
    public function getMissingValue(): int|float|string|bool
    {
        return $this->missingValue;
    }

    /**
     * @inheritDoc
     */
    public function getFieldType(): FieldType
    {
        return $this->fieldType;
    }
}
