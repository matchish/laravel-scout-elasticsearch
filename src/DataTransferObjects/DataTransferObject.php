<?php

namespace Matchish\ScoutElasticSearch\DataTransferObjects;

use Matchish\ScoutElasticSearch\ElasticSearch\Validation\Validation;
use Matchish\ScoutElasticSearch\Exceptions\UnknownPropertyException;

abstract class DataTransferObject
{

    /**
     * The class that contains validation rules
     * @var string
     */
    protected $validation;

    /**
     * @param array $properties
     */
    public function __construct($properties)
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, (string) $property)) {
                $this->{$property} = $value;
            }
        }

        if (isset($this->validation)) {
            (new $this->validation(get_object_vars($this)))->validate();
        }

    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        } else {
            throw new UnknownPropertyException("The {$key} property does not exist.");
        }
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function get($key): mixed
    {
        return $this->$key;
    }

    /**
     * @param string $key
     * @return bool
     */
    private function has($key): bool
    {
        return property_exists($this, $key);
    }

    /**
     * @return Validation|null
     */
    protected function validation()
    {
        return null;
    }

    /**
     * Transform the given where value.
     *
     * @param  mixed $value
     *
     * @return mixed
     */
    private function transform($value)
    {
        /*
         * Casts carbon instances to timestamp.
         */
        if ($value instanceof \Illuminate\Support\Carbon) {
            $value = $value->getTimestamp();
        }

        return $value;
    }

}