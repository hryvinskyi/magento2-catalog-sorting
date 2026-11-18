<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Integration\Model\Index;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldProviderInterface;

/**
 * Test document field provider: one long field holding the same value for every asked-for product in every store
 */
class FixedDocumentFieldProvider implements DocumentFieldProviderInterface
{
    public const FIELD = 'sort_hryvinskyi_test_document_field';
    public const VALUE = 42;

    /**
     * @inheritDoc
     */
    public function getFieldTypes(): array
    {
        return [self::FIELD => FieldType::Long];
    }

    /**
     * @inheritDoc
     */
    public function getValues(int $storeId, array $productIds): array
    {
        return array_fill_keys($productIds, [self::FIELD => self::VALUE]);
    }
}
