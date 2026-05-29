<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\TrackableJobs;

use Illuminate\Database\Eloquent\Model;

class TrackedJob extends Model implements TrackedJobContract
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'output' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getTable(): string
    {
        $table = config('elasticsearch.tracked_jobs.table', 'tracked_jobs');

        if (! \is_string($table) || $table === '') {
            throw new \InvalidArgumentException('The tracked jobs table name must be a non-empty string.');
        }

        return $table;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markAsFinished(): void
    {
        $this->update([
            'status' => self::STATUS_FINISHED,
            'finished_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason = ''): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'output' => ['message' => $reason],
            'finished_at' => now(),
        ]);
    }
}
