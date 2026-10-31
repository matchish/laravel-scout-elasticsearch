<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use Matchish\ScoutElasticSearch\Jobs\ImportState;
use Tests\IntegrationTestCase;

final class ImportStateTest extends IntegrationTestCase
{
    public function test_complete_only_once_every_range_is_recorded(): void
    {
        $state = $this->created(3);

        $state->recordFinished($this->elasticsearch, 0);
        $state->recordFinished($this->elasticsearch, 2);
        $this->assertFalse($state->complete($this->elasticsearch), 'range 1 is missing');

        $state->recordFinished($this->elasticsearch, 1);
        $this->assertTrue($state->complete($this->elasticsearch));
    }

    public function test_recording_a_range_twice_changes_nothing(): void
    {
        $state = $this->created(2);

        $state->recordFinished($this->elasticsearch, 1);
        $state->recordFinished($this->elasticsearch, 1);

        $this->assertFalse($state->complete($this->elasticsearch), 'range 0 is still missing');
    }

    /**
     * Search would see a record only after a refresh. The check must
     * not depend on one, so refresh is switched off here.
     */
    public function test_a_record_counts_at_once_without_a_refresh(): void
    {
        $state = $this->created(2);
        $this->elasticsearch->indices()->putSettings([
            'index' => $state->alias(),
            'body' => ['index' => ['refresh_interval' => '-1']],
        ]);

        $state->recordFinished($this->elasticsearch, 0);
        $state->recordFinished($this->elasticsearch, 1);

        $this->assertTrue($state->complete($this->elasticsearch));
    }

    public function test_checks_more_ranges_than_one_request_holds(): void
    {
        $state = $this->created(1001);
        foreach (range(0, 999) as $range) {
            $state->recordFinished($this->elasticsearch, $range);
        }
        $this->assertFalse($state->complete($this->elasticsearch), 'range 1000 sits in a request of its own');

        $state->recordFinished($this->elasticsearch, 1000);
        $this->assertTrue($state->complete($this->elasticsearch));
    }

    public function test_checks_the_first_ranges_too(): void
    {
        $state = $this->created(1001);
        foreach (range(1, 1000) as $range) {
            $state->recordFinished($this->elasticsearch, $range);
        }
        $this->assertFalse($state->complete($this->elasticsearch), 'range 0 sits in the last request');

        $state->recordFinished($this->elasticsearch, 0);
        $this->assertTrue($state->complete($this->elasticsearch));
    }

    public function test_an_import_with_no_ranges_is_complete_while_its_state_exists(): void
    {
        $state = $this->created(0);
        $this->assertTrue($state->complete($this->elasticsearch));

        $state->delete($this->elasticsearch);
        $this->assertFalse($state->complete($this->elasticsearch));
    }

    public function test_a_removed_state_is_never_complete(): void
    {
        $state = ImportState::forImport('products_never_created', 1);

        $this->assertFalse($state->exists($this->elasticsearch));
        $this->assertFalse($state->complete($this->elasticsearch));
    }

    public function test_delete_removes_the_state_and_tolerates_a_second_call(): void
    {
        $state = $this->created(1);

        $state->delete($this->elasticsearch);
        $state->delete($this->elasticsearch);

        $this->assertFalse($state->exists($this->elasticsearch));
    }

    /**
     * Elasticsearch would create a missing index on write. A record that
     * arrives after the state was removed must not bring it back.
     */
    public function test_a_late_record_does_not_bring_back_removed_state(): void
    {
        $state = $this->created(1);
        $state->delete($this->elasticsearch);

        $state->recordFinished($this->elasticsearch, 0);
        $state->recordHandOff($this->elasticsearch, 'job');

        $this->assertFalse($state->exists($this->elasticsearch));
        $this->assertFalse(
            $this->elasticsearch->indices()->exists(['index' => $state->alias()])->asBool(),
            'no index may be created under the alias name either'
        );
    }

    public function test_only_the_first_job_to_claim_the_finish_gets_it(): void
    {
        $state = $this->created(1);

        $this->assertTrue($state->claimFinish($this->elasticsearch, 'job-a'));
        $this->assertFalse($state->claimFinish($this->elasticsearch, 'job-b'));
    }

    /**
     * A job that crashed after its claim is delivered again. It must be
     * able to start the finishing job, or nothing ever would.
     */
    public function test_the_claiming_job_gets_the_claim_again(): void
    {
        $state = $this->created(1);

        $state->claimFinish($this->elasticsearch, 'job-a');

        $this->assertTrue($state->claimFinish($this->elasticsearch, 'job-a'));
    }

    public function test_no_claim_to_finish_once_the_state_is_gone(): void
    {
        $state = $this->created(1);
        $state->delete($this->elasticsearch);

        $this->assertFalse($state->claimFinish($this->elasticsearch, 'job-a'));
    }

    public function test_keeps_the_reason_a_finish_failed(): void
    {
        $state = $this->created(1);
        $this->assertNull($state->failure($this->elasticsearch));

        $state->recordFailure($this->elasticsearch, 'the cluster is read-only');

        $this->assertSame('the cluster is read-only', $state->failure($this->elasticsearch));
    }

    public function test_no_failure_once_the_state_is_gone(): void
    {
        $state = $this->created(1);
        $state->delete($this->elasticsearch);

        $state->recordFailure($this->elasticsearch, 'too late');

        $this->assertNull($state->failure($this->elasticsearch));
        $this->assertFalse($state->exists($this->elasticsearch));
    }

    public function test_remembers_which_jobs_handed_on(): void
    {
        $state = $this->created(1);

        $this->assertFalse($state->handedOff($this->elasticsearch, 'job-a'));
        $state->recordHandOff($this->elasticsearch, 'job-a');

        $this->assertTrue($state->handedOff($this->elasticsearch, 'job-a'));
        $this->assertFalse($state->handedOff($this->elasticsearch, 'job-b'));
    }

    public function test_is_named_after_the_import_index(): void
    {
        $this->assertSame('products_1786738819-state', ImportState::forImport('products_1786738819', 1)->alias());
    }

    private function created(int $ranges): ImportState
    {
        $state = ImportState::forImport('products_'.random_int(1, 999999), $ranges);
        $state->create($this->elasticsearch);

        return $state;
    }
}
