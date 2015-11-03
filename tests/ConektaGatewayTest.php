<?php

use Carbon\Carbon;
use Dinkbit\ConektaCashier\ConektaGateway;
use Mockery as m;

class ConektaGatewayTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreatePassesProperOptionsToCustomer()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('mxn');
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,createConektaCustomer,updateLocalConektaData]', [$billable, 'plan']);
        $gateway->shouldReceive('createConektaCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan'                   => 'plan',
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->id = 'foo';
        $billable->shouldReceive('setConektaSubscription')->once()->with('sub_id');
        $gateway->shouldReceive('getConektaCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateLocalConektaData')->once();

        $gateway->create('token', []);
    }

    public function testCreatePassesProperOptionsToCustomerForTrialEnd()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('mxn');
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,createConektaCustomer,updateLocalConektaData]', [$billable, 'plan']);
        $gateway->shouldReceive('createConektaCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->shouldReceive('updateSubscription')->once()->with([
            'plan'                   => 'plan',
            'trial_end'              => Carbon::now()->toIso8601String(),
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->id = 'foo';
        $billable->shouldReceive('setConektaSubscription')->once()->with('sub_id');
        $gateway->shouldReceive('getConektaCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateLocalConektaData')->once();

        $gateway->skipTrial();
        $gateway->create('token', []);
    }

    public function testCreateUtilizesGivenCustomerIfApplicable()
    {
        $billable = $this->mockBillableInterface();
        $billable->shouldReceive('getCurrency')->andReturn('mxn');
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,createConektaCustomer,updateLocalConektaData,updateCard]', [$billable, 'plan']);
        $gateway->shouldReceive('createConektaCustomer')->never();
        $customer = m::mock('StdClass');
        $customer->shouldReceive('updateSubscription')->once()->andReturn($sub = (object) ['id' => 'sub_id']);
        $billable->shouldReceive('setConektaSubscription')->with('sub_id');
        $customer->id = 'foo';
        $gateway->shouldReceive('getConektaCustomer')->once()->with('foo');
        $gateway->shouldReceive('updateCard')->once();
        $gateway->shouldReceive('updateLocalConektaData')->once();

        $gateway->create('token', [], $customer);
    }

    public function testSwapCallsCreateWithProperArguments()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[create,getConektaCustomer,maintainTrial]', [$billable, 'plan']);
        $gateway->shouldReceive('getConektaCustomer')->once()->andReturn($customer = m::mock('StdClass'));
        $gateway->shouldReceive('maintainTrial')->once();
        $gateway->shouldReceive('create')->once()->with(null, null, $customer);

        $gateway->swap();
    }

    public function testCancellingOfSubscriptions()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->subscription = (object) ['billing_cycle_end' => $time = time(), 'trial_end' => null];
        $billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function ($value) use ($time) {
            $this->assertEquals($time, $value->getTimestamp());

            return $value;
        });
        $customer->shouldReceive('cancelSubscription')->once();
        $billable->shouldReceive('setConektaIsActive')->once()->with(false)->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once();

        $gateway->cancel();
    }

    public function testCancellingOfSubscriptionsWithTrials()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->subscription = (object) ['billing_cycle_end' => $trialTime = time() + 50, 'trial_end' => time()];
        $billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function ($value) use ($trialTime) {
            $this->assertEquals($trialTime, $value->getTimestamp());

            return $value;
        });
        $customer->shouldReceive('cancelSubscription')->once();
        $billable->shouldReceive('setConektaIsActive')->once()->with(false)->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once();

        $gateway->cancel();
    }

    public function testUpdatingCreditCardData()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,getLastFourCardDigits,getCardType]', [$billable, 'plan']);
        $gateway->shouldAllowMockingProtectedMethods();
        $gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
        $gateway->shouldReceive('getLastFourCardDigits')->once()->andReturn('1111');
        $gateway->shouldReceive('getCardType')->once()->andReturn('brand');
        $customer->subscription = (object) ['plan' => (object) ['id'  => 1]];
        $customer->shouldReceive('createCard')->once()->with(['token' => 'token'])->andReturn($card = m::mock('StdClass'));
        $card->id = 'card_id';
        $customer->shouldReceive('updateSubscription')->once()->with([
            'card'                   => $card->id,
        ])->andReturn((object) ['id' => 'sub_id']);
        $customer->shouldReceive('update')->once()->with(['default_card_id' => 'card_id']);

        $billable->shouldReceive('setLastFourCardDigits')->once()->with('1111')->andReturn($billable);
        $billable->shouldReceive('setCardType')->once()->with('brand')->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once();

        $gateway->updateCard('token');
    }

    public function testRetrievingACustomersConektaPlanId()
    {
        $billable = $this->mockBillableInterface();
        $gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', [$billable, 'plan']);
        $gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
        $customer->subscription = (object) ['plan_id' => 1];

        $this->assertEquals(1, $gateway->planId());
    }

    public function testUpdatingLocalConektaData()
    {
        $billable = $this->mockBillableInterface();
        $gateway = new ConektaGateway($billable, 'plan');
        $billable->shouldReceive('setConektaId')->once()->with('id')->andReturn($billable);
        $billable->shouldReceive('setConektaPlan')->once()->with('plan')->andReturn($billable);
        $billable->shouldReceive('setLastFourCardDigits')->once()->with('last-four')->andReturn($billable);
        $billable->shouldReceive('setCardType')->once()->with('brand-type')->andReturn($billable);
        $billable->shouldReceive('setConektaIsActive')->once()->with(true)->andReturn($billable);
        $billable->shouldReceive('setSubscriptionEndDate')->once()->with(null)->andReturn($billable);
        $billable->shouldReceive('saveBillableInstance')->once()->andReturn($billable);
        $customer = m::mock('StdClass');
        $customer->cards[0] = (object) ['id' => 'id', 'last4' => 'last-four', 'brand' => 'brand-type'];
        $customer->id = 'id';
        $customer->default_card_id = 'id';
        $customer->shouldReceive('getSubscriptionId')->andReturn('sub_id');

        $gateway->updateLocalConektaData($customer);
    }

    public function testGettingTheTrialEndDateForACustomer()
    {
        $time = time();
        $customer = (object) ['subscription' => (object) ['trial_end' => $time, 'status' => 'in_trial']];
        $gateway = new ConektaGateway($this->mockBillableInterface(), 'plan');

        $this->assertInstanceOf('Carbon\Carbon', $gateway->getTrialEndForCustomer($customer));
        $this->assertEquals($time, $gateway->getTrialEndForCustomer($customer)->getTimestamp());
    }

    protected function mockBillableInterface()
    {
        $billable = m::mock('Dinkbit\ConektaCashier\Contracts\Billable');
        $billable->shouldReceive('getConektaKey')->andReturn('key');

        return $billable;
    }
}
