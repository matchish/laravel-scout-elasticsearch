# Upgrade guide

## From 7.x to 8.0

**For most projects this is a drop-in upgrade.** Update the package and you are done:

```bash
composer require matchish/laravel-scout-elasticsearch:^8.0
```

Nothing was dropped from the requirements. Version 8.0 *adds* support for PHP 8.2 and Laravel Scout 11, so every project that runs 7.x can run 8.0.

You only need to change code in the two cases below. Both are advanced features, and each fix is one line.

### 1. You wrote your own `HitsIteratorAggregate`

Only relevant if you bound a custom class to read extra data from results (aggregations, highlights), as described in the "Custom results" section of the README.

The interface now declares a return type on `getIterator()`. PHP requires your class to declare the same type.

```php
// Before
public function getIterator()
{
    return $this->hits;
}

// After
public function getIterator(): \Traversable
{
    return $this->hits;
}
```

Without this change PHP stops with a fatal error when the class is loaded. On PHP 8.1 and newer your class already produced a deprecation warning about this, so the new type only makes the requirement explicit.

### 2. You wrote your own `ImportSource` class

Only relevant if you have a class that implements `ImportSource` directly. If your `ImportSourceFactory` returns `new DefaultImportSource(...)` — the way the README describes eager loading — you do not need to change anything.

The interface changed:

- `chunked()` now returns `?ImportSource` instead of a `Collection`.
- Three methods were added: `setChunkScope()`, `getTotalChunks()`, `getChunkSize()`.

The simplest fix is to extend `DefaultImportSource` and override only what you need. If you keep your own implementation, copy the method signatures from [`src/Searchable/ImportSource.php`](src/Searchable/ImportSource.php).

To use the new parallel import with a custom source, also implement [`Partitionable`](src/Searchable/Partitionable.php), which exposes the query so it can be split into ranges.

### If you tested the 8.0 alpha releases

The alpha versions had a different parallel import. It was rebuilt before the stable release.

- The `tracked_jobs` table is no longer used. You may drop it with your own migration (`Schema::dropIfExists('tracked_jobs')`). **Do not drop it** if you also use the `mateusjunges/laravel-trackable-jobs` package — that package stores its own data in a table with the same name.
- Remove the `elasticsearch.tracked_jobs` section from `config/elasticsearch.php`.
- Remove the `scout.chunk.handlers` setting and stop running one worker per `elasticsearch-parallel-N` queue. The new import uses a single queue, so run several workers on that one queue instead.
- Remove the `elasticsearch.queue.name` setting and the `SCOUT_QUEUE_NAME` variable from your `.env` file. They named the prefix of those per-worker queues. Range jobs now run on the queue the model already uses for Scout, which you set with `scout.queue`.
- Parallel import now needs Laravel's standard `job_batches` table. Laravel 11 and newer ship it by default. On older versions run:

  ```bash
  php artisan queue:batches-table
  php artisan migrate
  ```

### New in 8.0

Nothing here is required, but these are worth knowing:

- `scout:import --parallel` imports with many workers at once. See [docs/parallel-import.md](docs/parallel-import.md).
- `scout:import --parallel --catch-up` re-imports rows that changed while the import was running, for applications that write to the database without Eloquent events.
- Models without an integer primary key can join the parallel import by adding a `searchablePartitionKey()` method that returns the name of a numeric, indexed column.
