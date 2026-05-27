<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\TrackableJobs;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|int $id
 * @property string|null $trackable_id
 * @property string|null $trackable_type
 * @property string $name
 * @property string|null $job_id
 * @property string|null $status
 * @property int $attempts
 * @property array|null $output
 * @property string|null $queue
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * 
 * @phpstan-type TrackedJobModelType TrackedJobContract&Model
 */
interface TrackedJobContract
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FAILED = 'failed';

    public const STATUS_FINISHED = 'finished';

    public function markAsRunning(): void;

    public function markAsFinished(): void;

    public function markAsFailed(string $reason = ''): void;
}
