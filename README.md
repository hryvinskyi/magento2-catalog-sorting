# Magento 2 Catalog Sorting

Sorting of storefront catalog listings inside the search engine: leading sort rules put in front of the shopper's sort, sort codes ordered by a search-document field of their own, and the document fields other modules write into the product search index. The module ships no rule, field or value of its own; the rule and method modules fill its three pools.

## What it does for a store

- **Leading sort rules.** A rule module (for example out-of-stock products last) adds a sort in front of the one the shopper chose, on category pages, quick search and advanced search. The shopper's sort still orders the products within each group.
- **Sort codes that are no product attribute.** A method module registers a code such as `bestsellers` with the document field it is ordered by (`sort_bestsellers`). Without this, the platform sends a clause keyed by the bare code, which the engine rejects.
- **Document fields at index time.** A method module writes numeric values (a creation time, a popularity count, a saving) into the product search documents while `catalogsearch_fulltext` builds them, one query per batch of products.
- **Nothing per request but the sort.** Every order is on a precomputed numeric document field. A storefront query adds one pass over its sort clauses; no table is read and no join is made per request. A clause names the value a document without the field sorts as and the type to assume where the index does not map the field, so a query never fails on an index that lacks it.

## Requirements

- PHP 8.1 to 8.5.
- OpenSearch or Elasticsearch 8 as the catalog search engine.
- The platform's Store, Config, Backend, AdvancedSearch, Elasticsearch, OpenSearch and Elasticsearch8 modules (what `composer.json` and `etc/module.xml` declare).

## Installation

```bash
composer require hryvinskyi/magento2-catalog-sorting
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

On its own the module changes no listing. Install the rule and method modules a store needs, or the whole set with `hryvinskyi/magento2-catalog-sorting-pack`.

## Configuration

The module adds the section **Stores › Configuration › Catalog › Catalog Sorting** (`catalog_sorting`, access rule `Hryvinskyi_CatalogSorting::config`). It declares no group itself: each rule and method module adds its own group there.

## How a listing query is changed

An after-plugin on each supported engine's own query mapper (`Magento\OpenSearch\SearchAdapter\Mapper`, `Magento\Elasticsearch8\SearchAdapter\Mapper`, plugin `Hryvinskyi_CatalogSorting::leadingSorts`) rewrites the finished query's `body.sort` in two steps:

1. **Shopper sort codes**, on every query: each clause keyed by a registered code is rendered on the code's field, in the shopper's direction (descending when the clause names none). A query without a sort list is left as it is.
2. **Leading rules**, only on the search requests `catalog_view_container`, `quick_search_container` and `advanced_search_container`. Each rule owns its field: every clause the request already carries on that field is dropped, and the rule's own clause goes in front when the rule is active for the request's store. Rules go in front in the order of their `sortOrder`. When a rule's clause is the only one left, the relevance clause follows it. A request whose store cannot be told keeps its clauses.

The document fields' mapping types are added when an index is created, through a plugin on `Magento\Elasticsearch\ElasticAdapter\Model\Adapter\FieldMapper\ProductFieldMapper` (`Hryvinskyi_CatalogSorting::documentFieldTypes`). A field the mapping already declares keeps its type. A new field therefore needs one full `catalogsearch_fulltext` reindex; until then every document sorts as the field's missing value.

## Extending

Every pool is an argument in `etc/di.xml`. Contribute from a module's global `etc/di.xml`: indexing runs from cron and the command line, and search requests are also built outside the storefront area.

### A leading sort rule

Implement `Api\ListingSortRuleInterface` (`getLeadingSort()`, `isActive(int $storeId)`), or `Api\RequestScopedListingSortRuleInterface` to apply to some search requests only (`appliesTo(string $requestName, int $storeId)`). Build the sort through the generated `Api\Data\LeadingSortInterfaceFactory` with the keys `field`, `direction` (`Api\Data\SortDirection`), `missingValue` and `fieldType` (`Api\Data\FieldType`). Add the rule to the `rules` argument, with a `sortOrder` (lower goes first):

```xml
<type name="Hryvinskyi\CatalogSorting\Model\Search\LeadingSorts">
    <arguments>
        <argument name="rules" xsi:type="array">
            <item name="out_of_stock" xsi:type="object" sortOrder="100">Hryvinskyi\CatalogSortingOutOfStock\Model\OutOfStockLastRule</item>
        </argument>
    </arguments>
</type>
```

`getLeadingSort()` runs for every rule on every handled request, active or not, because its field is needed to drop the request's own clause on it. A rule only names a field: a rule whose field the index does not hold brings it in through a document field provider. A listing with a search request of its own is added to the `requestNames` argument of the same type.

### A shopper sort code

Declare a virtual type of `Model\ConfiguredShopperSortField` with `code`, `field`, `fieldType` and `missingValue` (default 0), and add it to the `sortFields` argument of `Model\Search\ShopperSortFields`. The item key is free text; the code comes from the item.

```xml
<virtualType name="HryvinskyiCatalogSortingBestsellersSortField"
             type="Hryvinskyi\CatalogSorting\Model\ConfiguredShopperSortField">
    <arguments>
        <argument name="code" xsi:type="string">bestsellers</argument>
        <argument name="field" xsi:type="string">sort_bestsellers</argument>
        <argument name="fieldType" xsi:type="string">double</argument>
        <argument name="missingValue" xsi:type="number">0</argument>
    </arguments>
</virtualType>
<type name="Hryvinskyi\CatalogSorting\Model\Search\ShopperSortFields">
    <arguments>
        <argument name="sortFields" xsi:type="array">
            <item name="bestsellers" xsi:type="object">HryvinskyiCatalogSortingBestsellersSortField</item>
        </argument>
    </arguments>
</type>
```

A field that depends on the shopper (one per customer group) implements `Api\ShopperSortFieldInterface` itself. Every item is built for every search query, so it stays cheap to construct, and `getSortField()` reads no database. A code must never be a product attribute code. Two items with one code fail the construction. To offer the code in the Sort By list, register it with `hryvinskyi/magento2-catalog-sorting-options`.

### A document field

Implement `Api\Index\DocumentFieldProviderInterface`: `getFieldTypes()` declares every field the provider may write, with its `FieldType`, for every store; `getValues(int $storeId, array $productIds)` answers one batch with one query at most. Add it to the `providers` argument:

```xml
<type name="Hryvinskyi\CatalogSorting\Model\Index\DocumentFields">
    <arguments>
        <argument name="providers" xsi:type="array">
            <item name="newest" xsi:type="object">Hryvinskyi\CatalogSortingNewest\Model\Index\NewestDocumentField</item>
        </argument>
    </arguments>
</type>
```

Only integers and finite floats of the asked-for products reach a document. Two providers that declare one field with different types fail when an index is created. Field types are `integer`, `long`, `float`, `double`, `keyword` and `boolean`.

## Public contracts

`Api\ListingSortRuleInterface`, `Api\RequestScopedListingSortRuleInterface`, `Api\ShopperSortFieldInterface`, `Api\Data\LeadingSortInterface`, `Api\Data\SortFieldInterface`, `Api\Data\SortDirection`, `Api\Data\FieldType`, `Api\Index\DocumentFieldProviderInterface`, `Api\Index\DocumentFieldTypesInterface`, `Api\Search\LeadingSortsInterface`, `Api\Search\ShopperSortFieldsInterface` and `Api\Search\SortClauseRendererInterface` are `@api`. The `create()` keys of the generated `LeadingSortInterfaceFactory` and `SortFieldInterfaceFactory` are part of the contract.

## Limits

- Listings that do not go through the search engine query keep their own order.
- An order is as fresh as the search index and the full-page cache.
