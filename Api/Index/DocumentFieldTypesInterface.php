<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Api\Index;

/**
 * The index mapping types of every product document field the registered document field providers write
 *
 * @api
 */
interface DocumentFieldTypesInterface
{
    /**
     * Get the mapping type of every field any registered provider may write
     *
     * Two providers may declare one field only with one type.
     *
     * @return array<string, string> Field name => the engine's mapping type name
     * @throws \InvalidArgumentException When two providers declare one field with different types
     */
    public function getFieldTypes(): array;
}
