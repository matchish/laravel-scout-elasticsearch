<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Validation;

use Matchish\ScoutElasticSearch\Exceptions\ValidationException;

abstract class Validation
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @return array
     */
    abstract public function rules(): array;

    /**
     * @return array
     */
    abstract public function messages(): array;

    /**
     * @throws ValidationException
     * @return void
     */
    public function validate(): void
    {
        $validator = new Validator(
            $this->data,
            $this->rules(),
            $this->messages()
        );

        $validator->validate();
    }
}