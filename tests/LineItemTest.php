<?php

use Dinkbit\ConektaCashier\LineItem;
use Mockery as m;

class LineItemTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testReceivingDollarTotal()
    {
        $line = new LineItem((object) ['amount' => 10000]);
        $this->assertEquals(100.00, $line->total());
    }
}
