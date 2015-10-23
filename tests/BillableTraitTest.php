<?php

use Illuminate\Support\Facades\Config;
use Mockery as m;

class BillableTraitTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testOnTrialMethodReturnsTrueIfTrialDateGreaterThanCurrentDate()
    {
        $billable = m::mock('BillableTraitTestStub[getTrialEndDate]');
        $billable->shouldReceive('getTrialEndDate')->andReturn(Carbon\Carbon::now()->addDays(5));

        $this->assertTrue($billable->onTrial());
    }

    public function testOnTrialMethodReturnsFalseIfTrialDateLessThanCurrentDate()
    {
        $billable = m::mock('BillableTraitTestStub[getTrialEndDate]');
        $billable->shouldReceive('getTrialEndDate')->andReturn(Carbon\Carbon::now()->subDays(5));

        $this->assertFalse($billable->onTrial());
    }

    public function testSubscribedChecksStripeIsActiveIfCardRequiredUpFront()
    {
        $billable = new BillableTraitCardUpFrontTestStub();
        $billable->conekta_active = true;
        $billable->subscription_ends_at = null;
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitCardUpFrontTestStub();
        $billable->conekta_active = false;
        $billable->subscription_ends_at = null;
        $this->assertFalse($billable->subscribed());

        $billable = new BillableTraitCardUpFrontTestStub();
        $billable->conekta_active = false;
        $billable->subscription_ends_at = Carbon\Carbon::now()->addDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitCardUpFrontTestStub();
        $billable->conekta_active = false;
        $billable->subscription_ends_at = Carbon\Carbon::now()->subDays(5);
        $this->assertFalse($billable->subscribed());
    }

    public function testSubscribedHandlesNoCardUpFront()
    {
        $billable = new BillableTraitTestStub();
        $billable->trial_ends_at = null;
        $billable->conekta_active = null;
        $billable->subscription_ends_at = null;
        $this->assertFalse($billable->subscribed());

        $billable = new BillableTraitTestStub();
        $billable->conekta_active = 0;
        $billable->trial_ends_at = Carbon\Carbon::now()->addDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitTestStub();
        $billable->conekta_active = true;
        $billable->trial_ends_at = Carbon\Carbon::now()->subDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitTestStub();
        $billable->conekta_active = false;
        $billable->trial_ends_at = Carbon\Carbon::now()->subDays(5);
        $billable->subscription_ends_at = null;
        $this->assertFalse($billable->subscribed());

        $billable = new BillableTraitTestStub();
        $billable->trial_ends_at = null;
        $billable->conekta_active = null;
        $billable->subscription_ends_at = Carbon\Carbon::now()->addDays(5);
        $this->assertTrue($billable->subscribed());

        $billable = new BillableTraitTestStub();
        $billable->trial_ends_at = null;
        $billable->conekta_active = null;
        $billable->subscription_ends_at = Carbon\Carbon::now()->subDays(5);
        $this->assertFalse($billable->subscribed());
    }

    public function testReadyForBillingChecksStripeReadiness()
    {
        $billable = new BillableTraitTestStub();
        $billable->conekta_id = null;
        $this->assertFalse($billable->readyForBilling());

        $billable = new BillableTraitTestStub();
        $billable->conekta_id = 1;
        $this->assertTrue($billable->readyForBilling());
    }

    public function testGettingStripeKey()
    {
        Config::shouldReceive('get')->once()->with('services.conekta.secret')->andReturn('foo');
        $this->assertEquals('foo', BillableTraitTestStub::getConektaKey());
    }
}

class BillableTraitTestStub implements Dinkbit\ConektaCashier\Contracts\Billable
{
    use Dinkbit\ConektaCashier\Billable;
    public $cardUpFront = false;

    public function save()
    {
    }
}

class BillableTraitCardUpFrontTestStub implements Dinkbit\ConektaCashier\Contracts\Billable
{
    use Dinkbit\ConektaCashier\Billable;
    public $cardUpFront = true;

    public function save()
    {
    }
}
