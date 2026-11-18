<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Search;

use Magento\Framework\Search\RequestInterface;

/**
 * Puts the active listing sort rules' clauses in front of a search request's own sort clauses
 *
 * @api
 */
interface LeadingSortsInterface
{
    /**
     * Get the request's engine sort clauses with the active rules' clauses in front
     *
     * A request this module does not handle, or one whose store cannot be told, gets its clauses back unchanged.
     *
     * @param RequestInterface $request The search request being mapped to an engine query
     * @param array<int,mixed> $sorts The request's engine sort clauses, in order
     * @return array<int, mixed>
     */
    public function apply(RequestInterface $request, array $sorts): array;
}
