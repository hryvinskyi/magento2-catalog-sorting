<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Model\Search;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterface;
use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterface;
use Hryvinskyi\CatalogSorting\Api\Search\SortClauseRendererInterface;

/**
 * Sort clauses in the field-sort format both supported search engines read (OpenSearch and Elasticsearch 8)
 *
 * A field clause carries the direction, the value a document without the field sorts as (`missing`) and the
 * type to assume where an index does not map the field (`unmapped_type`), so the query never fails on an index
 * that lacks the field. Leading sorts and shopper sort fields render through the same builder, so the clause has
 * one shape. The relevance clause is keyed `_score`, the key the platform's own relevance sort renders.
 */
class SortClauseRenderer implements SortClauseRendererInterface
{
    private const RELEVANCE_FIELD = '_score';

    /**
     * @inheritDoc
     */
    public function render(LeadingSortInterface $sort): array
    {
        return $this->fieldClause(
            $sort->getField(),
            $sort->getDirection(),
            $sort->getMissingValue(),
            $sort->getFieldType()
        );
    }

    /**
     * @inheritDoc
     */
    public function renderField(SortFieldInterface $field, SortDirection $direction): array
    {
        return $this->fieldClause(
            $field->getField(),
            $direction,
            $field->getMissingValue(),
            $field->getFieldType()
        );
    }

    /**
     * @inheritDoc
     */
    public function renderRelevance(): array
    {
        return [self::RELEVANCE_FIELD => ['order' => 'desc']];
    }

    /**
     * Build one field clause that never fails on an index without the field
     *
     * @param string $field Search-index field
     * @param SortDirection $direction Direction the field is ordered in
     * @param int|float|string|bool $missingValue Value a document without the field sorts as
     * @param FieldType $fieldType Engine type the field has where it is unmapped
     * @return array<string, array<string, int|float|string|bool>> One clause, keyed by the field
     */
    private function fieldClause(
        string $field,
        SortDirection $direction,
        int|float|string|bool $missingValue,
        FieldType $fieldType
    ): array {
        return [
            $field => [
                'order' => $direction->value,
                'missing' => $missingValue,
                'unmapped_type' => $fieldType->value,
            ],
        ];
    }
}
