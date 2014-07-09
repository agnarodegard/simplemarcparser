<?php namespace Scriptotek\SimpleMarcParser;


class RecordTest extends \PHPUnit_Framework_TestCase {

    public function testIsset()
    {
        $rec = new Record;
        $rec->key = "value";

        $this->assertFalse(isset($rec->someRandomStuff));
        $this->assertTrue(isset($rec->key));
        $this->assertEquals('value', $rec->key);
    }

    public function testSerializations()
    {
        $rec = new Record;
        $rec->key = "value";

        $this->assertEquals(array('key' => 'value'), $rec->toArray());
        $this->assertJsonStringEqualsJsonString(json_encode(array('key' => 'value')), $rec->toJson());

    }
}