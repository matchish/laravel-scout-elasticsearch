<?php

namespace Tests\Integration\Filters;

use Matchish\ScoutElasticSearch\DataTransferObjects\WhereFilterData;
use Matchish\ScoutElasticSearch\Exceptions\ValidationException;
use Tests\TestCase;

class WhereFilterTest extends TestCase
{

    /** @test */
    public function it_throws_an_exception_when_an_invalid_operator_is_passed()
    {
        $this->expectException(ValidationException::class);

        new WhereFilterData([
            'filed' => 'price',
            'operator' => '!',
            'value' => 5,
        ]);
    }
}