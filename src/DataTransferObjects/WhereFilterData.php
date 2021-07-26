<?php

namespace Matchish\ScoutElasticSearch\DataTransferObjects;

use Matchish\ScoutElasticSearch\DataTransferObjects\DataTransferObject;
use Matchish\ScoutElasticSearch\ElasticSearch\Validation\WhereFilterDataValidation;

final class WhereFilterData extends DataTransferObject
{

    /**
     * @var string
     */
    public $field;

    /**
     * @var string
     */
    public $operator;

    /**
     * @var mixed
     */
    public $value;

    /**
     * The class that contains validation rules
     * @var string
     */
    protected $validation = WhereFilterDataValidation::class;

}