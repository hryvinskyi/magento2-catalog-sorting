<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Plugin\Search;

use Hryvinskyi\CatalogSorting\Api\Search\LeadingSortsInterface;
use Hryvinskyi\CatalogSorting\Api\Search\ShopperSortFieldsInterface;
use Magento\Framework\Search\RequestInterface;

/**
 * Renders the shopper's sort codes on their index fields and puts the listing sort rules' clauses in front of the
 * sort of a search engine query
 *
 * It sits on each supported engine's own query mapper, the class the engine's search adapter asks for the finished
 * query, and rewrites the query's `body.sort` there. How an engine assembles the sort inside its mapper may change
 * between releases; the mapper's finished query is what the engine runs.
 *
 * Shopper sort codes are rendered first, on every query, because a clause left on a bare code makes the engine
 * reject the query; the leading rules then go in front of the rendered clauses, on the listings they serve.
 */
class LeadingSortsPlugin
{
    /**
     * @param ShopperSortFieldsInterface $shopperSortFields Renders shopper sort codes on their index fields
     * @param LeadingSortsInterface $leadingSorts Puts the active rules' clauses in front
     */
    public function __construct(
        private readonly ShopperSortFieldsInterface $shopperSortFields,
        private readonly LeadingSortsInterface $leadingSorts
    ) {
    }

    /**
     * Render the shopper sort codes, then put the active rules' clauses in front of the finished query's clauses
     *
     * A query without a sort list is returned as it is: the rules order a sort the engine already applies, they
     * never introduce one.
     *
     * @param object $subject The engine's query mapper
     * @param array<mixed> $result The finished engine query
     * @param RequestInterface $request The search request the query was built for
     * @return array<mixed>
     */
    public function afterBuildQuery(object $subject, array $result, RequestInterface $request): array
    {
        $body = $result['body'] ?? null;
        if (!is_array($body) || !is_array($body['sort'] ?? null)) {
            return $result;
        }

        $sorts = $this->shopperSortFields->apply(array_values($body['sort']));
        $body['sort'] = $this->leadingSorts->apply($request, $sorts);
        $result['body'] = $body;

        return $result;
    }
}
