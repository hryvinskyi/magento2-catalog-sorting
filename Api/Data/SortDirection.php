<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Data;

/**
 * The direction a leading sort orders its field in; the case value is the engine's word for it
 *
 * @api
 */
enum SortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}
