<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Model\Search;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSorting\Api\Search\ShopperSortFieldsInterface;
use Hryvinskyi\CatalogSorting\Api\Search\SortClauseRendererInterface;
use Hryvinskyi\CatalogSorting\Api\ShopperSortFieldInterface;

/**
 * Renders every sort clause keyed by a registered shopper sort code on that code's search-index field
 *
 * When a shopper sorts by a code that is no product attribute, the platform keys the clause by the bare code and
 * gives it only a direction. No index holds a field of that name, so the engine would reject the whole query; the
 * clause is therefore rewritten on every query that carries it, whatever the request and whether or not its store
 * can be told. The rewritten clause takes the place of the original and keeps its direction (`asc` or `desc`, in
 * any letter case); a clause without a direction, or with one the engine does not know, is ordered descending.
 * Clauses that are not keyed arrays, and clauses on any other key, are kept as they are.
 *
 * The cost on a query is one pass over its sort clauses with one lookup each, and nothing when no code is
 * registered.
 */
class ShopperSortFields implements ShopperSortFieldsInterface
{
    /**
     * @var array<string, ShopperSortFieldInterface>
     */
    private readonly array $sortFieldsByCode;

    /**
     * @param SortClauseRendererInterface $renderer Renders a sort field as an engine clause
     * @param array<mixed> $sortFields Shopper sort fields; the item key is free text, the code comes from the item
     * @throws \InvalidArgumentException When an item is not a shopper sort field, or two items share a code
     */
    public function __construct(
        private readonly SortClauseRendererInterface $renderer,
        array $sortFields = []
    ) {
        $this->sortFieldsByCode = $this->indexByCode($sortFields);
    }

    /**
     * @inheritDoc
     */
    public function apply(array $sorts): array
    {
        if ($this->sortFieldsByCode === []) {
            return $sorts;
        }

        foreach ($sorts as $position => $clause) {
            if (!is_array($clause)) {
                continue;
            }

            $code = array_key_first($clause);
            if (!is_string($code) || !isset($this->sortFieldsByCode[$code])) {
                continue;
            }

            $sorts[$position] = $this->renderer->renderField(
                $this->sortFieldsByCode[$code]->getSortField(),
                $this->direction($clause[$code])
            );
        }

        return $sorts;
    }

    /**
     * Get the direction a clause asks for
     *
     * @param mixed $body The clause's value, holding its `order`
     * @return SortDirection Descending when the clause names no direction the engine knows
     */
    private function direction(mixed $body): SortDirection
    {
        $order = is_array($body) ? ($body['order'] ?? null) : null;

        return is_string($order)
            ? SortDirection::tryFrom(strtolower($order)) ?? SortDirection::Descending
            : SortDirection::Descending;
    }

    /**
     * Map each registered code to its shopper sort field
     *
     * @param array<mixed> $sortFields Items from the pool
     * @return array<string, ShopperSortFieldInterface>
     * @throws \InvalidArgumentException When an item is not a shopper sort field, or two items share a code
     */
    private function indexByCode(array $sortFields): array
    {
        $byCode = [];
        foreach ($sortFields as $key => $sortField) {
            if (!$sortField instanceof ShopperSortFieldInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Shopper sort field "%s" must implement %s.',
                    $key,
                    ShopperSortFieldInterface::class
                ));
            }

            $code = $sortField->getCode();
            if (isset($byCode[$code])) {
                throw new \InvalidArgumentException(sprintf(
                    'The sort code "%s" is registered twice as a shopper sort field; each code has one field.',
                    $code
                ));
            }
            $byCode[$code] = $sortField;
        }

        return $byCode;
    }
}
