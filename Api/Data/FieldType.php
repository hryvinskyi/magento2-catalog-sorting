<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Data;

/**
 * The scalar search-engine field types a leading sort can name; the case value is the engine's type name
 *
 * The engine uses the type for an index in which the field is not mapped, so a sort on it orders every
 * document by its missing value instead of failing the query.
 *
 * @api
 */
enum FieldType: string
{
    case Integer = 'integer';
    case Long = 'long';
    case Float = 'float';
    case Double = 'double';
    case Keyword = 'keyword';
    case Boolean = 'boolean';
}
