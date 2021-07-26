<?php

namespace Tests\Unit;

use Matchish\ScoutElasticSearch\DataTransferObjects\DataTransferObject;
use Matchish\ScoutElasticSearch\DataTransferObjects\Test\Dummy;
use Matchish\ScoutElasticSearch\Exceptions\UnknownPropertyException;
use PHPUnit\Framework\TestCase;

class DataTransferObjectTest extends TestCase
{
    /** @test */
    public function it_creates_properties_from_associative_array()
    {
        $properties = ['name' => 'john'];
        $dummyDTO = new Dummy($properties);

        $this->assertEquals($properties['name'], $dummyDTO->name);
        $this->assertInstanceOf(DataTransferObject::class, $dummyDTO);
    }

    /** @test */
    public function it_throws_an_exception_if_the_property_does_not_exist()
    {
        $properties = ['name' => 'john', 'lname' => 'doe'];
        $dummyDTO = new Dummy($properties);

        $this->expectException(UnknownPropertyException::class);
        $this->assertEquals($properties['lname'], $dummyDTO->lname);
        $this->assertEquals($properties['name'], $dummyDTO->name);
        $this->assertInstanceOf(DataTransferObject::class, $dummyDTO);
    }
}