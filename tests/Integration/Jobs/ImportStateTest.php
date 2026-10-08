<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use Matchish\ScoutElasticSearch\Jobs\ImportState;
use Tests\IntegrationTestCase;

final class ImportStateTest extends IntegrationTestCase
{
    public function test_complete_only_once_every_range_is_recorded(): void
    {
        $records = $this->created(3);

        $records->recordFinished($this->elasticsearch, 0);
        $records->recordFinished($this->elasticsearch, 2);
        $this->assertFalse($records->complete($this->elasticsearch), 'range 1 is missing');

        $records->recordFinished($this->elasticsearch, 1);
        $this->assertTrue($records->complete($this->elasticsearch));
    }

    public function test_recording_a_range_twice_changes_nothing(): void
    {
        $records = $this->created(2);

        $records->recordFinished($this->elasticsearch, 1);
        $records->recordFinished($this->elasticsearch, 1);

        $this->assertFalse($records->complete($this->elasticsearch), 'a second record of range 1 must not stand in for range 0');
    }

    /**
     * Search would see a record only after a refresh. The check must
     * not depend on one, so refresh is switched off here.
     */
    public function test_a_record_counts_at_once_without_a_refresh(): void
    {
        $records = $this->created(2);
        $this->elasticsearch->indices()->putSettings([
            'index' => $records->alias(),
            'body' => ['index' => ['refresh_interval' => '-1']],
        ]);

        $records->recordFinished($this->elasticsearch, 0);
        $records->recordFinished($this->elasticsearch, 1);

        $this->assertTrue($records->complete($this->elasticsearch));
    }

    public function test_checks_more_ranges_than_one_request_holds(): void
    {
        $records = $this->created(1001);
        foreach (range(0, 999) as $range) {
            $records->recordFinished($this->elasticsearch, $range);
        }
        $this->assertFalse($records->complete($this->elasticsearch), 'range 1000 sits in a request of its own');

        $records->recordFinished($this->elasticsearch, 1000);
        $this->assertTrue($records->complete($this->elasticsearch));
    }

    public function test_checks_the_first_ranges_too(): void
    {
        $records = $this->created(1001);
        foreach (range(1, 1000) as $range) {
            $records->recordFinished($this->elasticsearch, $range);
        }
        $this->assertFalse($records->complete($this->elasticsearch), 'range 0 sits in the last request');

        $records->recordFinished($this->elasticsearch, 0);
        $this->assertTrue($records->complete($this->elasticsearch));
    }

    public function test_an_import_with_no_ranges_is_complete_while_its_state_exists(): void
    {
        $records = $this->created(0);
        $this->assertTrue($records->complete($this->elasticsearch));

        $records->delete($this->elasticsearch);
        $this->assertFalse($records->complete($this->elasticsearch));
    }

    public function test_records_that_are_gone_are_never_complete(): void
    {
        $records = ImportState::forImport('products_never_created', 1);

        $this->assertFalse($records->exists($this->elasticsearch));
        $this->assertFalse($records->complete($this->elasticsearch));
    }

    public function test_delete_removes_the_records_and_tolerates_a_second_call(): void
    {
        $records = $this->created(1);

        $records->delete($this->elasticsearch);
        $records->delete($this->elasticsearch);

        $this->assertFalse($records->exists($this->elasticsearch));
    }

    /**
     * Elasticsearch would create a missing index on write. A record that
     * arrives after the state was removed must not bring it back.
     */
    public function test_a_late_record_does_not_bring_back_removed_state(): void
    {
        $records = $this->created(1);
        $records->delete($this->elasticsearch);

        $records->recordFinished($this->elasticsearch, 0);
        $records->recordHandOff($this->elasticsearch, 'job');

        $this->assertFalse($records->exists($this->elasticsearch));
        $this->assertFalse(
            $this->elasticsearch->indices()->exists(['index' => $records->alias()])->asBool(),
            'no index may be created under the alias name either'
        );
    }

    public function test_only_the_first_job_to_claim_the_finish_gets_it(): void
    {
        $records = $this->created(1);

        $this->assertTrue($records->claimFinish($this->elasticsearch, 'job-a'));
        $this->assertFalse($records->claimFinish($this->elasticsearch, 'job-b'));
    }

    /**
     * A job that crashed after its claim is delivered again. It must be
     * able to start the finishing job, or nothing ever would.
     */
    public function test_the_claiming_job_gets_the_claim_again(): void
    {
        $records = $this->created(1);

        $records->claimFinish($this->elasticsearch, 'job-a');

        $this->assertTrue($records->claimFinish($this->elasticsearch, 'job-a'));
    }

    public function test_no_claim_to_finish_once_the_state_is_gone(): void
    {
        $records = $this->created(1);
        $records->delete($this->elasticsearch);

        $this->assertFalse($records->claimFinish($this->elasticsearch, 'job-a'));
    }

    public function test_keeps_the_reason_a_finish_failed(): void
    {
        $records = $this->created(1);
        $this->assertNull($records->failure($this->elasticsearch));

        $records->recordFailure($this->elasticsearch, 'the cluster is read-only');

        $this->assertSame('the cluster is read-only', $records->failure($this->elasticsearch));
    }

    public function test_no_failure_once_the_state_is_gone(): void
    {
        $records = $this->created(1);
        $records->delete($this->elasticsearch);

        $records->recordFailure($this->elasticsearch, 'too late');

        $this->assertNull($records->failure($this->elasticsearch));
        $this->assertFalse($records->exists($this->elasticsearch));
    }

    public function test_remembers_which_jobs_handed_on(): void
    {
        $records = $this->created(1);

        $this->assertFalse($records->handedOff($this->elasticsearch, 'job-a'));
        $records->recordHandOff($this->elasticsearch, 'job-a');

        $this->assertTrue($records->handedOff($this->elasticsearch, 'job-a'));
        $this->assertFalse($records->handedOff($this->elasticsearch, 'job-b'));
    }

    public function test_records_are_named_after_the_import_index(): void
    {
        $this->assertSame('products_1786738819-state', ImportState::forImport('products_1786738819', 1)->alias());
    }

    private function created(int $ranges): ImportState
    {
        $records = ImportState::forImport('products_'.random_int(1, 999999), $ranges);
        $records->create($this->elasticsearch);

        return $records;
    }
}
