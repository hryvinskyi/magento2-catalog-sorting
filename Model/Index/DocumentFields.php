<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSorting\Model\Index;

use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldProviderInterface;
use Hryvinskyi\CatalogSorting\Api\Index\DocumentFieldTypesInterface;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;

/**
 * Hands the search indexer the fields every registered document field provider writes, one batch at a time
 *
 * The indexer asks once per batch of products of one store, and every provider is asked once with the whole batch.
 * The indexer merges each returned product's fields into that product's document and has no document for a
 * product outside the batch, so an id it did not ask for would abort the whole batch: only the asked-for ids are
 * returned, whatever a provider answers. Values other than integers and finite floats are dropped, so a provider
 * can never put a value into a document that the engine would refuse. When two providers write one field of one
 * product, the provider registered later wins.
 *
 * Which stores a provider writes values for is the provider's own decision; every provider is asked for every
 * store. The same pool declares the fields' mapping types, which the index mapping is built from.
 */
class DocumentFields implements AdditionalFieldsProviderInterface, DocumentFieldTypesInterface
{
    /**
     * @var array<string, DocumentFieldProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param array<mixed> $providers Document field providers, keyed by item name
     * @throws \InvalidArgumentException When an item is not a document field provider
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $this->validated($providers);
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $productIds Products of the batch, as the indexer keys its documents
     * @param mixed $storeId Store whose documents are being built
     * @return array<int, array<string, int|float>> Product id => field name => value, only for the given products
     */
    public function getFields(array $productIds, $storeId): array
    {
        $requested = $this->requestedIds($productIds);
        $store = $this->storeId($storeId);
        if ($requested === [] || $store === null || $this->providers === []) {
            return [];
        }

        $ids = array_keys($requested);
        $fields = [];
        foreach ($this->providers as $provider) {
            $fields = $this->merged($fields, $provider->getValues($store, $ids), $requested);
        }

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function getFieldTypes(): array
    {
        $types = [];
        $declaredBy = [];
        foreach ($this->providers as $key => $provider) {
            foreach ($provider->getFieldTypes() as $field => $fieldType) {
                $field = (string)$field;
                if (isset($types[$field]) && $types[$field] !== $fieldType->value) {
                    throw new \InvalidArgumentException(sprintf(
                        'The document field "%s" is declared as "%s" by "%s" and as "%s" by "%s"; '
                        . 'a field has one type.',
                        $field,
                        $types[$field],
                        $declaredBy[$field] ?? '',
                        $fieldType->value,
                        $key
                    ));
                }

                $types[$field] = $fieldType->value;
                $declaredBy[$field] = $key;
            }
        }

        return $types;
    }

    /**
     * Merge one provider's answer into the fields gathered so far, keeping only asked-for products and numbers
     *
     * The answer is read as untrusted input: whatever a provider returns, only an integer product id that was
     * asked for, a non-empty field name and an integer or finite float value get through.
     *
     * @param array<int,array<string,int|float>> $fields Fields gathered so far
     * @param mixed $answer The provider's answer
     * @param array<int,int> $requested Asked-for product ids as keys
     * @return array<int, array<string, int|float>>
     */
    private function merged(array $fields, mixed $answer, array $requested): array
    {
        if (!is_array($answer)) {
            return $fields;
        }

        foreach ($answer as $productId => $values) {
            if (!is_int($productId) || !isset($requested[$productId]) || !is_array($values)) {
                continue;
            }

            foreach ($values as $field => $value) {
                if (!is_string($field) || $field === '' || !$this->isDocumentNumber($value)) {
                    continue;
                }
                $fields[$productId][$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * Check whether a value can be written into a document as a number
     *
     * @param mixed $value Value a provider returned
     * @return bool
     * @phpstan-assert-if-true int|float $value
     */
    private function isDocumentNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }

    /**
     * Get the asked-for product ids as integer keys
     *
     * @param array<mixed> $productIds Product ids as the indexer passes them
     * @return array<int, int> Product id => position
     */
    private function requestedIds(array $productIds): array
    {
        $ids = [];
        foreach ($productIds as $productId) {
            if (is_int($productId) || (is_string($productId) && ctype_digit($productId))) {
                $ids[] = (int)$productId;
            }
        }

        return array_flip($ids);
    }

    /**
     * Get the store id the indexer passed, or null when it is not one
     *
     * @param mixed $storeId Store id as the indexer passes it
     * @return int|null
     */
    private function storeId(mixed $storeId): ?int
    {
        if (is_int($storeId)) {
            return $storeId;
        }

        return is_string($storeId) && ctype_digit($storeId) ? (int)$storeId : null;
    }

    /**
     * Check every pool item is a document field provider
     *
     * @param array<mixed> $providers Items from the pool
     * @return array<string, DocumentFieldProviderInterface>
     * @throws \InvalidArgumentException When an item is not a document field provider
     */
    private function validated(array $providers): array
    {
        $validated = [];
        foreach ($providers as $key => $provider) {
            if (!$provider instanceof DocumentFieldProviderInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Document field provider "%s" must implement %s.',
                    $key,
                    DocumentFieldProviderInterface::class
                ));
            }
            $validated[(string)$key] = $provider;
        }

        return $validated;
    }
}
