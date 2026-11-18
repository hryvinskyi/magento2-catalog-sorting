<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Test\Unit\Model\Index;

use Hryvinskyi\CatalogSorting\Api\Data\FieldType;
use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldProviderInterface;
use Hryvinskyi\CatalogSorting\Model\Index\DocumentFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The document fields of every provider are merged per product, for the asked-for products only and with numeric
 * values only; every provider is asked once per batch, and an empty batch asks none. The providers' mapping types
 * are merged, two types for one field are refused by field name, and an item that is not a provider is refused by
 * item key.
 */
class DocumentFieldsTest extends TestCase
{
    private const STORE_ID = 3;

    /**
     * @return void
     */
    public function testMergesTheFieldsOfEveryProviderPerProduct(): void
    {
        $fields = $this->documentFields([
            'unitsSold' => $this->provider([10 => ['sort_units_sold' => 7], 20 => ['sort_units_sold' => 2]]),
            'newest' => $this->provider([10 => ['sort_new' => 1700000000]]),
        ])->getFields([10, 20, 30], self::STORE_ID);

        self::assertSame(
            [
                10 => ['sort_units_sold' => 7, 'sort_new' => 1700000000],
                20 => ['sort_units_sold' => 2],
            ],
            $fields
        );
    }

    /**
     * @return void
     */
    public function testTheProviderRegisteredLaterWinsAFieldBothWrite(): void
    {
        $fields = $this->documentFields([
            'first' => $this->provider([10 => ['sort_shared' => 1]]),
            'second' => $this->provider([10 => ['sort_shared' => 2]]),
        ])->getFields([10], self::STORE_ID);

        self::assertSame([10 => ['sort_shared' => 2]], $fields);
    }

    /**
     * The indexer keys its documents by product id, so the ids may arrive as digit strings and the store id too.
     *
     * @return void
     */
    public function testAsksEveryProviderOnceWithTheWholeBatchAsIntegers(): void
    {
        $first = $this->createMock(DocumentFieldProviderInterface::class);
        $first->expects(self::once())->method('getValues')->with(self::STORE_ID, [10, 20])->willReturn([]);
        $second = $this->createMock(DocumentFieldProviderInterface::class);
        $second->expects(self::once())->method('getValues')->with(self::STORE_ID, [10, 20])->willReturn([]);

        $fields = $this->documentFields(['first' => $first, 'second' => $second])
            ->getFields(['10', 20, 10], (string)self::STORE_ID);

        self::assertSame([], $fields);
    }

    /**
     * @param array<mixed> $productIds Product ids as the indexer passes them
     * @param mixed $storeId Store id as the indexer passes it
     * @return void
     * @dataProvider nothingToAskProvider
     */
    #[DataProvider('nothingToAskProvider')]
    public function testAsksNoProviderWithoutABatchOrAStore(array $productIds, mixed $storeId): void
    {
        $provider = $this->createMock(DocumentFieldProviderInterface::class);
        $provider->expects(self::never())->method('getValues');

        self::assertSame([], $this->documentFields(['unitsSold' => $provider])->getFields($productIds, $storeId));
    }

    /**
     * @return array<string, array{array<mixed>, mixed}>
     */
    public static function nothingToAskProvider(): array
    {
        return [
            'empty batch' => [[], self::STORE_ID],
            'no product id in the batch' => [['abc', null], self::STORE_ID],
            'store id that is no number' => [[10], 'default'],
            'no store id' => [[10], null],
        ];
    }

    /**
     * The indexer merges each returned product into its document and has none for a product outside the batch.
     *
     * @return void
     */
    public function testAProductTheIndexerDidNotAskForNeverReachesTheResult(): void
    {
        $fields = $this->documentFields([
            'unitsSold' => $this->provider([
                10 => ['sort_units_sold' => 7],
                99 => ['sort_units_sold' => 3],
                'parent' => ['sort_units_sold' => 1],
            ]),
        ])->getFields([10, 20], self::STORE_ID);

        self::assertSame([10 => ['sort_units_sold' => 7]], $fields);
    }

    /**
     * @param mixed $value A value a provider returned
     * @return void
     * @dataProvider notANumberProvider
     */
    #[DataProvider('notANumberProvider')]
    public function testAValueThatIsNotANumberIsDropped(mixed $value): void
    {
        $fields = $this->documentFields([
            'unitsSold' => $this->provider([10 => ['sort_kept' => 4, 'sort_dropped' => $value]]),
        ])->getFields([10], self::STORE_ID);

        self::assertSame([10 => ['sort_kept' => 4]], $fields);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notANumberProvider(): array
    {
        return [
            'digit string' => ['5'],
            'null' => [null],
            'boolean' => [true],
            'array' => [[1]],
            'infinite float' => [INF],
            'not a number' => [NAN],
        ];
    }

    /**
     * @return void
     */
    public function testZeroAndFloatsAreKept(): void
    {
        $fields = $this->documentFields([
            'saving' => $this->provider([10 => ['sort_saving_0' => 12.5, 'sort_saving_1' => 0, 'sort_x' => -1.0]]),
        ])->getFields([10], self::STORE_ID);

        self::assertSame([10 => ['sort_saving_0' => 12.5, 'sort_saving_1' => 0, 'sort_x' => -1.0]], $fields);
    }

    /**
     * @return void
     */
    public function testAnAnswerThatIsNoListOfFieldsIsSkipped(): void
    {
        $fields = $this->documentFields([
            'broken' => $this->provider([10 => 5, 20 => ['' => 1, 0 => 2]]),
            'unitsSold' => $this->provider([10 => ['sort_units_sold' => 7]]),
        ])->getFields([10, 20], self::STORE_ID);

        self::assertSame([10 => ['sort_units_sold' => 7]], $fields);
    }

    /**
     * @return void
     */
    public function testWithoutProvidersThereAreNoFieldsAndNoTypes(): void
    {
        $documentFields = $this->documentFields([]);

        self::assertSame([], $documentFields->getFields([10], self::STORE_ID));
        self::assertSame([], $documentFields->getFieldTypes());
    }

    /**
     * @return void
     */
    public function testMergesTheFieldTypesOfEveryProvider(): void
    {
        $types = $this->documentFields([
            'unitsSold' => $this->provider([], ['sort_units_sold' => FieldType::Long]),
            'saving' => $this->provider([], ['sort_saving_0' => FieldType::Float, 'sort_saving_1' => FieldType::Float]),
            'again' => $this->provider([], ['sort_units_sold' => FieldType::Long]),
        ])->getFieldTypes();

        self::assertSame(
            ['sort_units_sold' => 'long', 'sort_saving_0' => 'float', 'sort_saving_1' => 'float'],
            $types
        );
    }

    /**
     * @return void
     */
    public function testTwoTypesForOneFieldAreRefusedNamingTheField(): void
    {
        $documentFields = $this->documentFields([
            'unitsSold' => $this->provider([], ['sort_popular' => FieldType::Long]),
            'viewed' => $this->provider([], ['sort_popular' => FieldType::Integer]),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The document field "sort_popular" is declared as "long" by "unitsSold" and as "integer" by "viewed"'
        );

        $documentFields->getFieldTypes();
    }

    /**
     * @return void
     */
    public function testAnItemThatIsNotAProviderIsRefusedNamingTheKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document field provider "notAProvider" must implement');

        $this->documentFields(['unitsSold' => $this->provider([]), 'notAProvider' => new \stdClass()]);
    }

    /**
     * Build the composite over the given pool items
     *
     * @param array<mixed> $providers Pool items, keyed by item name
     * @return DocumentFields
     */
    private function documentFields(array $providers): DocumentFields
    {
        return new DocumentFields($providers);
    }

    /**
     * Build a provider with a fixed answer for every batch and fixed field types
     *
     * @param array<mixed> $answer What getValues() returns, as it is
     * @param array<string, FieldType> $fieldTypes What getFieldTypes() returns
     * @return DocumentFieldProviderInterface
     */
    private function provider(array $answer, array $fieldTypes = []): DocumentFieldProviderInterface
    {
        $provider = $this->createStub(DocumentFieldProviderInterface::class);
        $provider->method('getValues')->willReturn($answer);
        $provider->method('getFieldTypes')->willReturn($fieldTypes);

        return $provider;
    }
}
