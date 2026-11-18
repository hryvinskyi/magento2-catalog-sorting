# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-18

### Added
- Leading sort rules: the `rules` pool of `Model\Search\LeadingSorts`, applied on `catalog_view_container`,
  `quick_search_container` and `advanced_search_container`. Each rule owns its field; a request-scoped rule limits
  itself to some search requests.
- Shopper sort fields: the `sortFields` pool of `Model\Search\ShopperSortFields` renders a sort code that is no product
  attribute on a search-document field of its own, in the shopper's direction; `Model\ConfiguredShopperSortField`
  declares one in `di.xml`.
- Document fields: the `providers` pool of `Model\Index\DocumentFields` writes numeric fields into the product search
  documents per batch and declares their mapping types when an index is created.
- Query plugins on the OpenSearch and Elasticsearch 8 query mappers; every clause carries its missing value and
  unmapped type.
- The configuration section **Catalog › Catalog Sorting** (`catalog_sorting`) and its access rule
  `Hryvinskyi_CatalogSorting::config`.
