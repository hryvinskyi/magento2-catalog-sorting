<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Plugin\Index;

use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldTypesInterface;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;

/**
 * Declares the document fields of the registered providers in the mapping of every new product search index
 *
 * The engine adapter asks the product field mapper for all field types only when it creates an index, and puts the
 * answer into the new index's mapping; this adds each provider field with its type. A field the mapper already
 * declares keeps the mapper's type: a provider never changes the mapping of a field it does not own. Nothing here
 * runs on the storefront request path.
 */
class DocumentFieldTypesPlugin
{
    /**
     * @param DocumentFieldTypesInterface $documentFieldTypes Mapping types of the provider fields
     */
    public function __construct(
        private readonly DocumentFieldTypesInterface $documentFieldTypes
    ) {
    }

    /**
     * Add every provider field the mapper does not declare yet, with its mapping type
     *
     * @param FieldMapperInterface $subject The product field mapper
     * @param array<mixed> $result Field name => mapping, as the mapper built it
     * @return array<mixed>
     * @throws \InvalidArgumentException When two providers declare one field with different types
     */
    public function afterGetAllAttributesTypes(FieldMapperInterface $subject, array $result): array
    {
        foreach ($this->documentFieldTypes->getFieldTypes() as $field => $type) {
            if (!array_key_exists($field, $result)) {
                $result[$field] = ['type' => $type];
            }
        }

        return $result;
    }
}
