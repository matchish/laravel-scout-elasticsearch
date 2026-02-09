<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table_name;

    private bool $usingUuid;

    public function __construct()
    {
        $this->table_name = config('trackable-jobs.tables.tracked_jobs', 'tracked_jobs');
        $this->usingUuid = config('trackable-jobs.using_uuid', false);
    }

    public function up(): void
    {
        Schema::create($this->table_name, function (Blueprint $table) {
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
        Schema::dropIfExists($this->table_name);
    }
};
