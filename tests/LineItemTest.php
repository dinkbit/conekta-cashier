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
        $line = new LineItem($billable = m::mock('Dinkbit\ConektaCashier\Contracts\Billable'), (object) ['amount' => 10000]);
        $billable->shouldReceive('formatCurrency')->andReturn(100.00);
        $this->assertEquals(100.00, $line->total());
    }
}
