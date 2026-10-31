<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * Reads a bulk response and separates the failures that matter from
 * the ones that are the system working as designed.
 *
 * A version conflict means another write already stored something at
 * the same version or later — a live change beating a stale snapshot.
 * That is the ordering rule doing its job, so it is not an error.
 *
 * @internal
 */
final class BulkResult
{
    /**
     * @var array<mixed>
     */
    private $response;

    /**
     * @param  array<mixed>  $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * Errors other than version conflicts, which are expected.
     */
    public function hasFatalErrors(): bool
    {
        if (! ($this->response['errors'] ?? false)) {
            return false;
        }

        foreach ($this->errorTypes() as $type) {
            if ($type !== 'version_conflict_engine_exception') {
                return true;
            }
        }

        return false;
    }

    /**
     * Every action failed because its target does not exist: the
     * import was revoked and its index deleted.
     */
    public function allTargetsMissing(): bool
    {
        $types = $this->errorTypes();
        if ($types === []) {
            return false;
        }

        foreach ($types as $type) {
            if ($type !== 'index_not_found_exception') {
                return false;
            }
        }

        $items = $this->response['items'] ?? [];

        return is_array($items) && count($types) === count($items);
    }

    public function toJson(): string
    {
        $json = json_encode($this->response, JSON_PRETTY_PRINT);

        return $json === false ? 'unknown error' : $json;
    }

    /**
     * @return array<int, string>
     */
    private function errorTypes(): array
    {
        $items = $this->response['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $types = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($item as $action) {
                $type = $action['error']['type'] ?? null;
                if (is_string($type)) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }
}
