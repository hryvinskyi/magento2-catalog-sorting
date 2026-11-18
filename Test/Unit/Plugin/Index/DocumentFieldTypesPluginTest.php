<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Plugin\Index;

use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldTypesInterface;
use Hryvinskyi\CatalogSorting\Plugin\Index\DocumentFieldTypesPlugin;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use PHPUnit\Framework\TestCase;

/**
 * Every provider field the field mapper does not declare is added to the mapping with its type; a field the mapper
 * already declares keeps the mapper's mapping.
 */
class DocumentFieldTypesPluginTest extends TestCase
{
    /**
     * @return void
     */
    public function testAddsEveryProviderFieldWithItsType(): void
    {
        $result = $this->plugin(['sort_units_sold' => 'long', 'sort_saving_0' => 'float'])
            ->afterGetAllAttributesTypes(
                $this->createStub(FieldMapperInterface::class),
                ['name' => ['type' => 'text']]
            );

        self::assertSame(
            [
                'name' => ['type' => 'text'],
                'sort_units_sold' => ['type' => 'long'],
                'sort_saving_0' => ['type' => 'float'],
            ],
            $result
        );
    }

    /**
     * @return void
     */
    public function testNeverOverwritesAFieldTheMapperDeclares(): void
    {
        $result = $this->plugin(['is_out_of_stock' => 'long', 'sort_new' => 'long'])
            ->afterGetAllAttributesTypes(
                $this->createStub(FieldMapperInterface::class),
                ['is_out_of_stock' => ['type' => 'integer'], 'price_0_1' => ['type' => 'double']]
            );

        self::assertSame(
            [
                'is_out_of_stock' => ['type' => 'integer'],
                'price_0_1' => ['type' => 'double'],
                'sort_new' => ['type' => 'long'],
            ],
            $result
        );
    }

    /**
     * @return void
     */
    public function testWithoutProviderFieldsTheMappingIsKept(): void
    {
        $mapping = ['name' => ['type' => 'text']];

        self::assertSame(
            $mapping,
            $this->plugin([])->afterGetAllAttributesTypes($this->createStub(FieldMapperInterface::class), $mapping)
        );
    }

    /**
     * Build the plugin over fixed field types
     *
     * @param array<string, string> $fieldTypes Field name => mapping type
     * @return DocumentFieldTypesPlugin
     */
    private function plugin(array $fieldTypes): DocumentFieldTypesPlugin
    {
        $documentFieldTypes = $this->createStub(DocumentFieldTypesInterface::class);
        $documentFieldTypes->method('getFieldTypes')->willReturn($fieldTypes);

        return new DocumentFieldTypesPlugin($documentFieldTypes);
    }
}
