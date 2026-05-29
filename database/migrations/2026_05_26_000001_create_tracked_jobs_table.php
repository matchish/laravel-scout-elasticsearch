<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $tableName;

    private bool $usingUuid;

    public function __construct()
    {
        $this->tableName = config('elasticsearch.tracked_jobs.table', 'tracked_jobs');
        $this->usingUuid = config('elasticsearch.tracked_jobs.using_uuid', false);
    }

    public function up(): void
    {
        Schema::create($this->tableName, function (Blueprint $table) {
            $this->usingUuid
                ? $table->uuid()->primary()
                : $table->id();
            $table->string('trackable_id')->index()->nullable();
            $table->string('trackable_type')->index()->nullable();
            $table->string('name');
            $table->string('job_id')->nullable();
            $table->string('status')->nullable();
            $table->integer('attempts')->default(1);
            $table->json('output')->nullable();
            $table->string('queue')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName);
    }
};
