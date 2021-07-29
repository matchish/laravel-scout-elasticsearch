# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/)

## [Unreleased]

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
