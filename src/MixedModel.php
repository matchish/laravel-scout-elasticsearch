<?php

namespace Matchish\ScoutElasticSearch;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class MixedModel extends Model
{
    use Searchable;
}
