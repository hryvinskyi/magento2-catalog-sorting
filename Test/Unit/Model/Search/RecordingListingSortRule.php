<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Search;

use Hryvinskyi\CatalogSorting\Api\Data\LeadingSortInterface;
use Hryvinskyi\CatalogSorting\Api\ListingSortRuleInterface;

/**
 * Test double of a listing sort rule: a fixed leading sort, a fixed answer, and a record of every question asked
 */
class RecordingListingSortRule implements ListingSortRuleInterface
{
    /**
     * @var list<int>
     */
    private array $askedStoreIds = [];

    /**
     * @var int
     */
    private int $leadingSortReads = 0;

    /**
     * @param LeadingSortInterface $leadingSort The sort the rule puts in front
     * @param bool $active Whether the rule applies, in any store
     */
    public function __construct(
        private readonly LeadingSortInterface $leadingSort,
        private readonly bool $active
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLeadingSort(): LeadingSortInterface
    {
        $this->leadingSortReads++;

        return $this->leadingSort;
    }

    /**
     * @inheritDoc
     */
    public function isActive(int $storeId): bool
    {
        $this->askedStoreIds[] = $storeId;

        return $this->active;
    }

    /**
     * Get the store ids the rule was asked about, in order
     *
     * @return list<int>
     */
    public function askedStoreIds(): array
    {
        return $this->askedStoreIds;
    }

    /**
     * Check whether anything was asked of the rule
     *
     * @return bool
     */
    public function wasConsulted(): bool
    {
        return $this->leadingSortReads > 0 || $this->askedStoreIds !== [];
    }
}
