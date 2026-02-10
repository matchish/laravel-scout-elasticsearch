<?php

if (PHP_VERSION_ID >= 80200) {
    class_alias(\Matchish\ScoutElasticSearch\Jobs\ProcessSearchable_PHP82::class, \Matchish\ScoutElasticSearch\Jobs\ProcessSearchable::class);
    class_alias(\Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSourceParallel_PHP82::class, \Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSourceParallel::class);
} else {
    class_alias(\Matchish\ScoutElasticSearch\Jobs\ProcessSearchable_PHP80::class, \Matchish\ScoutElasticSearch\Jobs\ProcessSearchable::class);
    class_alias(\Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSourceParallel_PHP80::class, \Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSourceParallel::class);
}
