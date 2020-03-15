# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/)

## [Unreleased]

## [4.0.0] - 2020-03-12
### Added
-  Scout 8 Support

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
