<?php
/**
 * Mixed Model
 *
 * @since     May 2022
 * @author    Haydar KULEKCI <haydarkulekci@gmail.com>
 */

namespace Matchish\ScoutElasticSearch;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;

class MixedModel extends Model
{
    use Searchable;
}
