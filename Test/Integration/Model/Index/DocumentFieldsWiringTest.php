<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Integration\Model\Index;

use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldTypesInterface;
use Hryvinskyi\CatalogSorting\Model\Index\DocumentFields;
use Hryvinskyi\CatalogSorting\Plugin\Index\DocumentFieldTypesPlugin;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * A document field provider registered the way a method module's di.xml does it reaches both points the OpenSearch
 * indexer reads: its values come out of the platform's additional fields provider of the product data mapper, and
 * its field and type come out of OpenSearch's product field mapper, which builds the mapping of a new index.
 *
 * Every scenario runs on a fresh application, because the provider is registered by reconfiguring the container.
 */
#[AppArea('global')]
class DocumentFieldsWiringTest extends TestCase
{
    private const ADDITIONAL_FIELDS_PROVIDER = 'additionalFieldsProviderForElasticsearch';
    private const OPENSEARCH_FIELD_MAPPER = 'Magento\OpenSearch\Model\Adapter\FieldMapper\ProductFieldMapper';
    private const PRODUCT_ID = 987001;

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testTheFieldTypesContractResolvesToTheComposite(): void
    {
        self::assertInstanceOf(
            DocumentFields::class,
            Bootstrap::getObjectManager()->get(DocumentFieldTypesInterface::class)
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testTheProductDataMappersAdditionalFieldsCarryTheProviderValues(): void
    {
        $this->registerTheTestProvider();
        $provider = Bootstrap::getObjectManager()->create(self::ADDITIONAL_FIELDS_PROVIDER);
        self::assertInstanceOf(AdditionalFieldsProviderInterface::class, $provider);

        $fields = $provider->getFields([self::PRODUCT_ID], $this->defaultStoreId());

        $productFields = $fields[self::PRODUCT_ID] ?? null;
        self::assertIsArray($productFields);
        self::assertSame(
            FixedDocumentFieldProvider::VALUE,
            $productFields[FixedDocumentFieldProvider::FIELD] ?? null
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testTheOpenSearchMappingDeclaresTheProviderFieldWithItsType(): void
    {
        $this->registerTheTestProvider();
        $mapper = Bootstrap::getObjectManager()->create(self::OPENSEARCH_FIELD_MAPPER);
        self::assertInstanceOf(FieldMapperInterface::class, $mapper);
        $storeId = $this->defaultStoreId();

        $types = $mapper->getAllAttributesTypes(
            ['entityType' => 'product', 'websiteId' => $storeId, 'storeId' => $storeId]
        );

        self::assertSame(['type' => 'long'], $types[FixedDocumentFieldProvider::FIELD] ?? null);
    }

    /**
     * Register the test provider through the container the way a method module's di.xml does, and drop the shared
     * instances already built without it
     *
     * @return void
     */
    private function registerTheTestProvider(): void
    {
        $objectManager = $this->objectManager();
        $objectManager->configure([
            DocumentFields::class => [
                'arguments' => [
                    'providers' => ['hryvinskyiTestProvider' => ['instance' => FixedDocumentFieldProvider::class]],
                ],
            ],
        ]);
        $objectManager->removeSharedInstance(DocumentFields::class);
        $objectManager->removeSharedInstance(DocumentFieldTypesPlugin::class);
        $objectManager->removeSharedInstance(self::ADDITIONAL_FIELDS_PROVIDER);
    }

    /**
     * Get the id of the default store
     *
     * @return int
     */
    private function defaultStoreId(): int
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);

        return (int)$storeManager->getDefaultStoreView()?->getId();
    }

    /**
     * Get the test application's object manager
     *
     * @return ObjectManager
     */
    private function objectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        if (!$objectManager instanceof ObjectManager) {
            throw new \LogicException('The integration tests run without the test object manager.');
        }

        return $objectManager;
    }
}
