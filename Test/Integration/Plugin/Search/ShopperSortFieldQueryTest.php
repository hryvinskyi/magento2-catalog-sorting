<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Integration\Plugin\Search;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Data\SortFieldInterfaceFactory;
use Hryvinskyi\CatalogSorting\Api\Search\ShopperSortFieldsInterface;
use Hryvinskyi\CatalogSorting\Model\ConfiguredShopperSortField;
use Hryvinskyi\CatalogSorting\Model\Search\ShopperSortFields;
use Hryvinskyi\CatalogSorting\Plugin\Search\LeadingSortsPlugin;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Search\Request\Builder;
use Magento\Framework\Search\RequestInterface;
use Magento\OpenSearch\SearchAdapter\Mapper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * A storefront category listing sorted by a code that is no product attribute reaches the engine with that code
 * rendered on its registered index field, in the shopper's direction, with a missing value and an unmapped type
 *
 * The query is built by the real OpenSearch query mapper from a real `catalog_view_container` request, so the test
 * also proves the key the platform gives such a clause: the bare code. Every scenario runs on a fresh application,
 * because the shopper sort code is registered by reconfiguring the container.
 */
#[AppArea('frontend')]
class ShopperSortFieldQueryTest extends TestCase
{
    private const CODE = 'hryvinskyi_test_sort';
    private const FIELD = 'sort_hryvinskyi_test';
    private const SORT_FIELD_TYPE = 'HryvinskyiCatalogSortingTestShopperSortField';
    private const REQUEST_NAME = 'catalog_view_container';
    private const STORE_CODE = 'default';
    private const VISIBLE_IN_CATALOG_AND_SEARCH = [2, 4];
    private const ROOT_CATEGORY = 2;

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testEveryContractResolves(): void
    {
        $sortField = $this->service(SortFieldInterfaceFactory::class)->create([
            'field' => self::FIELD,
            'missingValue' => 0,
            'fieldType' => FieldType::Integer,
        ]);
        self::assertSame(self::FIELD, $sortField->getField());

        self::assertInstanceOf(ShopperSortFields::class, $this->service(ShopperSortFieldsInterface::class));
    }

    /**
     * Without a registration the clause reaches the engine raw, keyed by the bare code with only a direction:
     * the shape the shopper sort fields match on, and the one the engine would reject.
     *
     * @return void
     */
    #[AppIsolation(true)]
    public function testThePlatformKeysAnUnregisteredNonAttributeCodeByTheBareCode(): void
    {
        self::assertContains(
            [self::CODE => ['order' => 'desc']],
            $this->sortOfTheEngineQuery('DESC')
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testARegisteredCodeReachesTheEngineRenderedOnItsFieldInTheShoppersDirection(): void
    {
        $this->registerTheTestSortCode();

        $sort = $this->sortOfTheEngineQuery('DESC');

        self::assertContains(
            [self::FIELD => ['order' => 'desc', 'missing' => 0, 'unmapped_type' => 'integer']],
            $sort
        );
        self::assertSame([], $this->clausesKeyedBy(self::CODE, $sort));
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testARegisteredCodeKeepsAnAscendingDirection(): void
    {
        $this->registerTheTestSortCode();

        self::assertContains(
            [self::FIELD => ['order' => 'asc', 'missing' => 0, 'unmapped_type' => 'integer']],
            $this->sortOfTheEngineQuery('ASC')
        );
    }

    /**
     * Register the test sort code through the container the way a method module's di.xml does, and drop the
     * shared instances already built without it
     *
     * @return void
     */
    private function registerTheTestSortCode(): void
    {
        $objectManager = $this->objectManager();
        $objectManager->configure([
            self::SORT_FIELD_TYPE => [
                'type' => ConfiguredShopperSortField::class,
                'arguments' => [
                    'code' => self::CODE,
                    'field' => self::FIELD,
                    'fieldType' => FieldType::Integer->value,
                ],
            ],
            ShopperSortFields::class => [
                'arguments' => [
                    'sortFields' => ['hryvinskyiTestSort' => ['instance' => self::SORT_FIELD_TYPE]],
                ],
            ],
        ]);
        $objectManager->removeSharedInstance(ShopperSortFields::class);
        $objectManager->removeSharedInstance(LeadingSortsPlugin::class);
    }

    /**
     * Build the category listing request sorted by the test code and get the sort of the engine query for it
     *
     * @param string $direction The direction as the listing passes it
     * @return array<mixed>
     */
    private function sortOfTheEngineQuery(string $direction): array
    {
        $query = $this->newInstance(Mapper::class)->buildQuery($this->listingRequest($direction));
        $body = $query['body'] ?? null;
        self::assertIsArray($body);
        $sort = $body['sort'] ?? null;
        self::assertIsArray($sort);

        return $sort;
    }

    /**
     * Build the storefront category listing request, sorted by the test code
     *
     * @param string $direction The direction as the listing passes it
     * @return RequestInterface
     */
    private function listingRequest(string $direction): RequestInterface
    {
        $storeId = (int)$this->service(StoreManagerInterface::class)->getStore(self::STORE_CODE)->getId();
        $builder = $this->newInstance(Builder::class);
        $builder->setRequestName(self::REQUEST_NAME);
        $builder->bindDimension('scope', (string)$storeId);
        $builder->bind('category_ids', self::ROOT_CATEGORY);
        $builder->bind('visibility', self::VISIBLE_IN_CATALOG_AND_SEARCH);
        $builder->setFrom(0);
        $builder->setSize(12);
        $sortOrder = $this->newInstance(SortOrder::class);
        $sortOrder->setField(self::CODE);
        $sortOrder->setDirection($direction);
        $builder->setSort([$sortOrder]);

        return $builder->create();
    }

    /**
     * Get the clauses keyed by the given key
     *
     * @param string $key Clause key
     * @param array<mixed> $sort Engine sort clauses
     * @return array<mixed>
     */
    private function clausesKeyedBy(string $key, array $sort): array
    {
        return array_values(array_filter(
            $sort,
            static fn (mixed $clause): bool => is_array($clause) && array_key_first($clause) === $key
        ));
    }

    /**
     * Get a shared service from the real container, checked to be of the requested type
     *
     * @template T of object
     * @param class-string<T> $type Interface or class to resolve
     * @return T
     */
    private function service(string $type): object
    {
        $service = Bootstrap::getObjectManager()->get($type);
        if (!$service instanceof $type) {
            throw new \LogicException(sprintf('The container returned a service that is not a %s.', $type));
        }

        return $service;
    }

    /**
     * Create a new instance through the real container, checked to be of the requested type
     *
     * @template T of object
     * @param class-string<T> $type Class to create
     * @return T
     */
    private function newInstance(string $type): object
    {
        $instance = Bootstrap::getObjectManager()->create($type);
        if (!$instance instanceof $type) {
            throw new \LogicException(sprintf('The container created an object that is not a %s.', $type));
        }

        return $instance;
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
