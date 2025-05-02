# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/)

## [7.11.1] - 2025-05-02
### Added
- Support for legacy environment variables from `mailerlite/laravel-elasticsearch`, allowing smoother migration without requiring `.env` changes. [#XXX]([link-to-pr](https://github.com/matchish/laravel-scout-elasticsearch/pull/307))

## [7.11.0] - 2025-02-20
### Fixed
- SearchFactory adds empty `query_string` query even if query string is empty when no `where` clauses are set.
- DefaultImportSource do not work properly with model that have complex scopes https://github.com/matchish/laravel-scout-elasticsearch/pull/298

## [7.10.0] - 2024-12-12
### Added
- Use [`source` in options](https://github.com/matchish/laravel-scout-elasticsearch/pull/293) to set returned fields

## [7.9.0] - 2024-11-14
### Fixed
- [Using pagination with custom query in Scout Builder](https://github.com/matchish/laravel-scout-elasticsearch/pull/290).
### Added
- [Using `options()` of a builder](https://github.com/matchish/laravel-scout-elasticsearch/issues/252) for set `from` parameter.
- Supporting `take()` method of builder for setting response `size`.

## [7.8.0] - 2024-06-24
### Added
- [Added supports of whereNotIn condition](https://github.com/matchish/laravel-scout-elasticsearch/pull/282).

## [7.6.2] - 2024-06-24
### Fixed
- [Change if conditions order in soft deletes check for compatibility](https://github.com/matchish/laravel-scout-elasticsearch/pull/282).

## [7.6.1] - 2024-05-14
### Fixed
- fix for [parser incompatibility](https://github.com/matchish/laravel-scout-elasticsearch/issues/273)

## [7.6.0] - 2024-02-23
### Added
- Add one more condition. If the search() method does not pass any parameter, there is no need to add QueryStringQuery object.
  
## [7.5.0] - 2023-11-30
### Added
- [Added support for php 8.3](https://github.com/matchish/laravel-scout-elasticsearch/pull/266)
  
## [7.3.0] - 2023-07-31
### Added
- [Added support for `makeSearchableUsing` in Laravel Scout. This allows you to prepare and modify a collection of models before they are made searchable. For example, you may want to eager load a relationship so that the relationship data can be efficiently added to your search index.](https://github.com/matchish/laravel-scout-elasticsearch/pull/253)

## [7.2.2] - 2023-06-06
### Fixed
- [No duplicates in search on reindex anymore. updates/inserts will be visible only after reindex. For most projects should be ok but for some could be breaking changes](https://github.com/matchish/laravel-scout-elasticsearch/issues/247)

## [7.0.0] - 2023-02-01
### Changed
- No duplicates in search on reindex anymore. updates/inserts will be visible only after reindex. For most projects should be ok but for some could be breaking changes

## [6.0.2] - 2022-06-16
### Added
- Elasticsearch basic authentication support
- Elasticsearch CloudId and Api Key credential support

## [6.0.1] - 2022-06-09
### Added
- LazyMap implemented for ElasticsearchEngine

## [6.0.0] - 2022-04-30
### Added
- Elasticsearch 8 Support

## [5.0.2] - 2022-03-24
### Added
-  multiple ElasticSearch nodes support

## [5.0.1] - 2021-07-23
### Added
- whereIn filter support

## [5.0.0] - 2021-05-13
### Added
-  PHP 8 Support
-  Laravel Scout 9 Support

## [4.0.10] - 2021-08-01
### Fixed
-  Avoid ambiguous In Some Cases

## [4.0.9] - 2021-07-29
### Fixed
-  Avoid Conflict Helper Function `resolve()` In Some Packages

## [4.0.8] - 2021-07-23
### Added
-  whereIn filter support

## [4.0.7] - 2021-04-21
Support Scout 9
## [4.0.6] - 2021-04-21
### Fixed
-  Hot fix for https://github.com/matchish/laravel-scout-elasticsearch/issues/160

## [4.0.5] - 2021-01-05
### Fixed
-  Find searchable classes when inherited through traits

## [4.0.4] - 2020-12-14
### Fixed
-  Parse PHP to find searchable classes without loading them

## [4.0.3] - 2020-12-02
### Fixed
-  Compatible with Laravel Telescope as dev requirement [#135](https://github.com/matchish/laravel-scout-elasticsearch/issues/135)

## [4.0.2] - 2020-10-18
### Added
-  Laravel 8 Support

## [4.0.1] - 2020-03-26
### Fixed
-  Prevent unnessasary send `\Laravel\Scout\Jobs\MakeSearchable` to a queue

## [4.0.0] - 2020-03-12
### Added
-  Scout 8 Support

## [3.0.6] - 2021-01-05
### Fixed
-  Find searchable classes when inherited through traits

## [3.0.5] - 2020-12-10
### Fixed
-  Parse PHP to find searchable classes without loading them

## [3.0.4] - 2020-12-03
### Fixed
-  Compatible with Laravel Telescope as dev requirement [#135](https://github.com/matchish/laravel-scout-elasticsearch/issues/135)

## [3.0.3] - 2020-03-14
### Added
-  Load config from package [#84](https://github.com/matchish/laravel-scout-elasticsearch/issues/84)

## [3.0.2] - 2020-03-14
### Added
-  Populate routing meta-field [#90](https://github.com/matchish/laravel-scout-elasticsearch/issues/90)

## [3.0.1] - 2020-03-02
### Fixed
-  Respect the model uses soft delete

## [3.0.0] - 2019-11-17
### Added
- Elasticsearch 7 support
- Added interface binding for HitsIteratorAggregate for custom implementation

## [2.1.0] - 2019-11-13
### Added
- Import source factory
- Using global scopes only for import

## [2.0.4] - 2019-11-10
### Fixed
- Throw more descriptive exception if there are elasticsearch errors on update

## [2.0.3] - 2019-11-04
### Fixed
- Throw exception if there are elasticsearch errors on update

## [2.0.2] - 2019-05-10
### Added
- Search amongst multiple models

## [2.0.1] - 2019-05-06
### Added
- Progress report for console commands

## [2.0.0] - 2019-04-09
### Added
- ElasticSearch service provider

### Changed
- ScoutElasticSearchService don't config elasticsearch client anymore

### Fixed
- Empty elasticsearch host when config is cached

### Added
- Default config

## [1.1.0] - 2019-04-09
### Added
- Default config

## [1.0.0] - 2019-03-30
### Added
- Import console command
- Flush console command
- Implemented all basic scout engine methods
