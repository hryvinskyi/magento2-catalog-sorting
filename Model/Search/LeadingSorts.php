<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Model\Search;

use Hryvinskyi\CatalogSorting\Api\ListingSortRuleInterface;
use Hryvinskyi\CatalogSorting\Api\RequestScopedListingSortRuleInterface;
use Hryvinskyi\CatalogSorting\Api\Search\LeadingSortsInterface;
use Hryvinskyi\CatalogSorting\Api\Search\SortClauseRendererInterface;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\RequestInterface;

/**
 * Puts the active listing sort rules' clauses in front of the storefront listing requests' sort clauses
 *
 * Only requests whose name is on the allow-list are changed; every other request keeps its clauses exactly.
 * Each rule owns its field: every clause the request already carries for that field is dropped, and the rule's
 * own clause goes in front when the rule is active for the request's store, so a field is never sorted on twice.
 * A rule that limits itself to some requests counts as inactive for a request or store it says it does not apply
 * to; it still owns its field there.
 * Active rules come first in pool order, followed by the request's remaining clauses in their original order.
 *
 * When rule clauses were put in front and the request has no other clause left, the relevance clause follows
 * them: a request with no sort is ordered by relevance, while one whose sort holds only a field clause would be
 * ordered by that field and then by index order.
 *
 * A request without a dimension, or whose store cannot be resolved, keeps its clauses: an unknown store never
 * makes the storefront query fail.
 */
class LeadingSorts implements LeadingSortsInterface
{
    /**
     * @var list<ListingSortRuleInterface>
     */
    private readonly array $rules;

    /**
     * @var list<string>
     */
    private readonly array $requestNames;

    /**
     * @param ScopeResolverInterface $scopeResolver Resolves the store a search request is made for
     * @param SortClauseRendererInterface $renderer Renders a rule's leading sort as an engine clause
     * @param array<mixed> $rules Listing sort rules, already ordered by their `sortOrder`
     * @param array<mixed> $requestNames Names of the search requests the rules apply to
     * @throws \InvalidArgumentException When a rule is not a listing sort rule or a request name is not a
     *     non-empty string
     */
    public function __construct(
        private readonly ScopeResolverInterface $scopeResolver,
        private readonly SortClauseRendererInterface $renderer,
        array $rules = [],
        array $requestNames = []
    ) {
        $this->rules = $this->validRules($rules);
        $this->requestNames = $this->validRequestNames($requestNames);
    }

    /**
     * @inheritDoc
     */
    public function apply(RequestInterface $request, array $sorts): array
    {
        if (!in_array($request->getName(), $this->requestNames, true)) {
            return $sorts;
        }

        $storeId = $this->storeId($request);
        if ($storeId === null || $this->rules === []) {
            return $sorts;
        }

        $requestName = $request->getName();
        $leading = [];
        $remaining = $sorts;
        foreach ($this->rules as $rule) {
            $leadingSort = $rule->getLeadingSort();
            $field = $leadingSort->getField();
            $remaining = $this->withoutField($remaining, $field);
            if (!isset($leading[$field])
                && $this->appliesTo($rule, $requestName, $storeId)
                && $rule->isActive($storeId)
            ) {
                $leading[$field] = $this->renderer->render($leadingSort);
            }
        }

        if ($leading !== [] && $remaining === []) {
            $remaining[] = $this->renderer->renderRelevance();
        }

        return [...array_values($leading), ...$remaining];
    }

    /**
     * Check whether the rule applies to this request and store; a rule that sets no such limit applies everywhere
     *
     * @param ListingSortRuleInterface $rule The rule
     * @param string $requestName Name of the search request
     * @param int $storeId Store the listing is served for
     * @return bool
     */
    private function appliesTo(ListingSortRuleInterface $rule, string $requestName, int $storeId): bool
    {
        return !$rule instanceof RequestScopedListingSortRuleInterface || $rule->appliesTo($requestName, $storeId);
    }

    /**
     * Get the id of the store the request is made for
     *
     * @param RequestInterface $request The search request being mapped
     * @return int|null Null when the request names no store or the store cannot be resolved
     */
    private function storeId(RequestInterface $request): ?int
    {
        $dimension = current($request->getDimensions());
        if (!$dimension instanceof Dimension) {
            return null;
        }

        $value = $dimension->getValue();
        if ($value instanceof ScopeInterface) {
            $value = $value->getId();
        }

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        try {
            return (int)$this->scopeResolver->getScope((int)$value)->getId();
        } catch (LocalizedException | \InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Drop every clause keyed by the field, keeping the others, whatever their shape, in their order
     *
     * @param array<int,mixed> $sorts Engine sort clauses
     * @param string $field Search-index field
     * @return array<int, mixed>
     */
    private function withoutField(array $sorts, string $field): array
    {
        return array_values(array_filter(
            $sorts,
            static fn (mixed $clause): bool => !is_array($clause) || array_key_first($clause) !== $field
        ));
    }

    /**
     * Keep the rules as a list in their given order
     *
     * @param array<mixed> $rules Rules from the pool
     * @return list<ListingSortRuleInterface>
     * @throws \InvalidArgumentException When an item is not a listing sort rule
     */
    private function validRules(array $rules): array
    {
        $valid = [];
        foreach ($rules as $key => $rule) {
            if (!$rule instanceof ListingSortRuleInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Listing sort rule "%s" must implement %s.',
                    $key,
                    ListingSortRuleInterface::class
                ));
            }
            $valid[] = $rule;
        }

        return $valid;
    }

    /**
     * Keep the request names as a list
     *
     * @param array<mixed> $requestNames Request names from the allow-list
     * @return list<string>
     * @throws \InvalidArgumentException When an item is not a non-empty string
     */
    private function validRequestNames(array $requestNames): array
    {
        $valid = [];
        foreach ($requestNames as $key => $name) {
            if (!is_string($name) || trim($name) === '') {
                throw new \InvalidArgumentException(sprintf(
                    'Search request name "%s" must be a non-empty string.',
                    $key
                ));
            }
            $valid[] = $name;
        }

        return $valid;
    }
}
