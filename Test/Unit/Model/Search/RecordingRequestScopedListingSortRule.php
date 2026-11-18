<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Search;

use Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterface;
use Hryvinskyi\CatalogSorting\Api\RequestScopedListingSortRuleInterface;

/**
 * Test double of a listing sort rule that applies only to the request names and stores it was given, recording
 * every question asked
 */
class RecordingRequestScopedListingSortRule extends RecordingListingSortRule implements
    RequestScopedListingSortRuleInterface
{
    /**
     * @var list<array{string, int}>
     */
    private array $askedScopes = [];

    /**
     * @param LeadingSortInterface $leadingSort The sort the rule puts in front
     * @param bool $active Whether the rule is active, in any store
     * @param list<array{string, int}> $appliesTo The request name and store pairs the rule applies to
     */
    public function __construct(
        LeadingSortInterface $leadingSort,
        bool $active,
        private readonly array $appliesTo
    ) {
        parent::__construct($leadingSort, $active);
    }

    /**
     * @inheritDoc
     */
    public function appliesTo(string $requestName, int $storeId): bool
    {
        $this->askedScopes[] = [$requestName, $storeId];

        return in_array([$requestName, $storeId], $this->appliesTo, true);
    }

    /**
     * Get the request name and store pairs the rule was asked about, in order
     *
     * @return list<array{string, int}>
     */
    public function askedScopes(): array
    {
        return $this->askedScopes;
    }
}
