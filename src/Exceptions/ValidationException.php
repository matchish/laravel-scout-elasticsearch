<?php

namespace Matchish\ScoutElasticSearch\Exceptions;

use Exception;

final class ValidationException extends Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}