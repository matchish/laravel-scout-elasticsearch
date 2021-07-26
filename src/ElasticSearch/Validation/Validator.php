<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Validation;

use Matchish\ScoutElasticSearch\Exceptions\ValidationException;

final class Validator
{
    /**
     * @var array
     */
    protected $data;
    /**
     * @var array
     */
    protected $rules;
    /**
     * @var array
     */
    protected $messages;

    /**
     * @param array $data
     * @param array $rules
     * @param array $messages
     */
    public function __construct($data, $rules, $messages)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * @throws ValidationException
     * @return void
     */
    public function validate()
    {
        foreach ($this->rules as $attribute => $rule) {
            if (!$rule->passes($this->data[$attribute])) {
                $message = $this->messages[$attribute] . ' : ' . $this->data[$attribute];
                throw new ValidationException($message);
            }
        }
    }
}