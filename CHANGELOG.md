# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/)

## [Unreleased]
> Upgrading from 7.x or from an 8.0 alpha? See [UPGRADE.md](UPGRADE.md).

### Added
- `searchablePartitionKey()`: models without an integer primary key declare a numeric column to enable parallel import. `NULL` values are covered by a dedicated null-bucket job.
- `--catch-up` option for `scout:import --parallel`: right before the alias switch, re-imports rows changed during the import and removes the ones that stopped being searchable or were soft-deleted — for applications that write to the database without Eloquent events. A change counts when it moved `updated_at` or `deleted_at`; a row deleted outright by raw SQL leaves nothing to read and cannot be detected.
- `elasticsearch.parallel.chunks_per_range` config (default `8`) controlling how much work one job carries. A range with more rows is handed on to further jobs in the same batch, so skewed data cannot make one job outlive the queue's `retry_after` or `--timeout`.
- Live progress for `--parallel`: the console follows the batch and advances the bar as range jobs finish. It exits only after the alias switch, so success means searches already use the new index. When range jobs fail, or the final step fails, it reports the reason and the alias is not switched. Set `scout.queue` to keep the old fire-and-forget behaviour.
- `scout:import:status` command showing the progress, job counts and failures of parallel imports — for imports running on workers, or after you closed the terminal.

### Fixed
- A row changed while an import was running could be overwritten by the import's older copy of it: a deleted product reappeared in search, and an edit made mid-import was rolled back. Every write now carries a version — the time its data became true, plus a rank — and Elasticsearch keeps the newest, whatever order the writes arrive in. Imports and catch-up write a row as of its last change (`updated_at`, or `deleted_at` when later). A delete from Scout's observers uses the moment it is sent, so it applies even when sent from a model instance loaded before someone else edited the row. Within one second, a live change beats a catch-up copy, which beats an import copy. Both the parallel and the sequential import are covered. Indices the package creates keep delete records for 12 hours (`index.gc_deletes`), so a slow import job cannot outlive a delete; a `gc_deletes` value in your index settings takes precedence, and `elasticsearch.indices.gc_deletes` changes the default. Models without an `updated_at` column stay unversioned, as before.
- The sequential import (`scout:import` without `--parallel`) stopped after a chunk in which no row was searchable — for example a run of archived records — and skipped every row after it while still reporting success. It now moves past every row it reads.
- A queue that delivered a range job twice could publish an index with missing rows. Any Laravel queue can do this — after a worker crash, or when the database queue fails to delete a finished job, which MySQL 5.7 does often with several workers — and the job batch counts every delivery. Its pending count then reached zero while ranges were still running, and the batch callback switched the alias. Now each range job records its finished range in a small state index in Elasticsearch, and the job that records the last range starts the final step. The records are read only by document id, so a read sees every confirmed write at once, without waiting for a refresh. A repeated job adds no second job for the same rows, and only one job starts the final step. Range jobs and the final job retry up to 3 times.
- A range job of a superseded import could re-create its deleted index through Elasticsearch auto-creation, leaving a stranded index behind. Range and catch-up jobs now write through a per-import alias with `require_alias`: once the import is superseded or published, the alias is gone and a late write is rejected instead of resurrecting the index.
- Indices leaked by a crashed import (created, then never finished or cleaned) are now reclaimed on the next import, together with the import's state index. New indices carry a `_meta.scout_import` provenance marker. Cleanup deletes only marked indices that are named the way this model's imports name them, and that the model's search alias does not point to. So it never touches an index the package did not create, or the index of another model whose name starts the same.

### Changed
- Parallel import was rebuilt on Laravel job batching (`Bus::batch()`): key ranges are computed up front with one aggregate query and imported by independent queued jobs on a single shared queue. Range jobs write to the concrete index name instead of the write alias, so jobs of a superseded import can never pollute a newer index. A new `--parallel` import cancels a still-running one for the same index. Requires the `job_batches` table (shipped by default since Laravel 11).

### Removed
- The custom job tracking subsystem used by parallel import (`TrackedJob` model, `tracked_jobs` table and migration, `elasticsearch.tracked_jobs` config) — superseded by Laravel job batching.
- Round-robin parallel queues (`elasticsearch-parallel-N`, `scout.chunk.handlers`) — the new design uses one queue with any number of workers.
- `elasticsearch.queue.name` (`SCOUT_QUEUE_NAME`) config parameter, added in `8.0.0-alpha.2` — it named the round-robin queue prefix, which no longer exists. Range jobs use the model's Scout queue.

### Upgrading
- Full instructions, including the two interface changes that affect custom `HitsIteratorAggregate` and `ImportSource` implementations, are in [UPGRADE.md](UPGRADE.md).
- Alpha testers: the `tracked_jobs` table is no longer used, and parallel import now needs the standard `job_batches` table instead.

## [8.0.0-alpha.3] - 2026-02-09
### Changed
- The usage of mateusjunges/laravel-trackable-jobs package for parallel import
- Small composer package updates to better fit Laravel 12
- Dockerfile syntax
- Dockerfile PHP 8.4
- PHPUnit deprecation fixes

## [7.13.0] - 2026-05-09
### Added
- Support for Laravel Scout v11.1.0+ by updating query where handling for the new Scout where structure and operators. [#322](https://github.com/matchish/laravel-scout-elasticsearch/pull/322)

## [7.12.0] - 2025-08-26
### Changed
- Removed `roave/better-reflection` dependency and replaced usage with native PHP reflection in `SearchableListFactory`, reducing package size while maintaining behavior. [#314](https://github.com/matchish/laravel-scout-elasticsearch/pull/314)
- Dockerfile updated to use `netcat-openbsd` instead of deprecated `netcat`. [#314](https://github.com/matchish/laravel-scout-elasticsearch/pull/314)
- 
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

## [8.0.0-alpha.2] - 2024-06-20
### Added
- ElasticParams trait, that adds 'getElasticsearchScore' and 'getElasticsearchHighlight' to the model after performing a search.
- 'elasticsearch.queue.name' config parameter to set a custom parallel import queue name.

## [8.0.0-alpha.1] - 2024-05-29
### Added
- fromScope, uses forPageAfterId.
- a new option "--parallel" for ImportCommand, can only be used with [Trackable-Jobs](https://github.com/mateusjunges/trackable-jobs-for-laravel) package currently.

## Changed
- StageInterface now has two more functions. Both are used to make PullFromSource stage an iterable one.
- ImportSource interface now has three more functions. Chunk scope can now be set from a stage.
- When possible (model without a custom key), by default fromScope is used instead of pageScope.

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
