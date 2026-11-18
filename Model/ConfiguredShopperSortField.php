<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Model;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterface;
use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterfaceFactory;
use Hryvinskyi\CatalogSorting\Api\ShopperSortFieldInterface;

/**
 * A shopper sort code ordered by one fixed search-index field, configured entirely in `di.xml`
 *
 * A method module declares a virtual type of this class with its code, field, field type and, when it is not 0,
 * the missing value. The sort field is built on first use and kept: the pool builds every item for every search
 * query, while only a query sorting by this code reads its field.
 */
class ConfiguredShopperSortField implements ShopperSortFieldInterface
{
    /**
     * @var FieldType
     */
    private readonly FieldType $fieldType;

    /**
     * @var SortFieldInterface|null
     */
    private ?SortFieldInterface $sortField = null;

    /**
     * @param SortFieldInterfaceFactory $sortFieldFactory Creates the sort field on first use
     * @param string $code Sort code as the listing toolbar sends it; must not be empty or blank
     * @param string $field Search-index field the code is ordered by
     * @param string $fieldType Engine type of the field, one of the {@see FieldType} values
     * @param int|float $missingValue Value a document without the field sorts as
     * @throws \InvalidArgumentException When the code is empty or blank, or the field type is not a known one
     */
    public function __construct(
        private readonly SortFieldInterfaceFactory $sortFieldFactory,
        private readonly string $code,
        private readonly string $field,
        string $fieldType,
        private readonly int|float $missingValue = 0
    ) {
        if (trim($code) === '') {
            throw new \InvalidArgumentException('A shopper sort field needs a sort code; the code is empty.');
        }

        $this->fieldType = FieldType::tryFrom($fieldType) ?? throw new \InvalidArgumentException(sprintf(
            'Shopper sort field "%s" names the field type "%s", which is not one of: %s.',
            $code,
            $fieldType,
            implode(', ', array_map(static fn (FieldType $type): string => $type->value, FieldType::cases()))
        ));
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getSortField(): SortFieldInterface
    {
        return $this->sortField ??= $this->sortFieldFactory->create([
            'field' => $this->field,
            'missingValue' => $this->missingValue,
            'fieldType' => $this->fieldType,
        ]);
    }
}
