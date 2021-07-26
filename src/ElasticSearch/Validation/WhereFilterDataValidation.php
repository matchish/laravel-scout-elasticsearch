<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Validation;

use Matchish\ScoutElasticSearch\ElasticSearch\Validation\Rules\Operator;

final class WhereFilterDataValidation extends Validation
{

    /**
     * @var array $data
     */
    protected $data;

    /**
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return ['operator' => new Operator];
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'operator' => 'Invalid operator',
        ];
    }
}