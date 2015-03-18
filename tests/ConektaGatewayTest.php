<?php

use Mockery as m;
use Dinkbit\ConektaCashier\ConektaGateway;
use Dinkbit\ConektaCashier\BillableInterface;

class ConektaGatewayTest extends PHPUnit_Framework_TestCase
{
	public function tearDown()
	{
		Mockery::close();
	}


	public function testCreatePassesProperOptionsToCustomer()
	{
		$billable = $this->mockBillableInterface();
		$billable->shouldReceive('getCurrency')->andReturn('gbp');
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,createConektaCustomer,updateLocalConektaData]', array($billable, 'plan'));
		$gateway->shouldReceive('createConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->shouldReceive('updateSubscription')->once()->with([
			'plan' => 'plan',
			'prorate' => true,
			'quantity' => 1,
			'trial_end' => null,
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
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,createConektaCustomer,updateLocalConektaData]', array($billable, 'plan'));
		$gateway->shouldReceive('createConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->shouldReceive('updateSubscription')->once()->with([
			'plan' => 'plan',
			'prorate' => true,
			'quantity' => 1,
			'trial_end' => 'now',
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
		$billable->shouldReceive('getCurrency')->andReturn('usd');
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,createConektaCustomer,updateLocalConektaData,updateCard]', array($billable, 'plan'));
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
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[create,getConektaCustomer,maintainTrial]', array($billable, 'plan'));
		$gateway->shouldReceive('getConektaCustomer')->once()->andReturn($customer = m::mock('StdClass'));
		$gateway->shouldReceive('maintainTrial')->once();
		$gateway->shouldReceive('create')->once()->with(null, null, $customer);

		$gateway->swap();
	}


	public function testUpdateQuantity()
	{
		$customer = m::mock('StdClass');
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];
		$customer->shouldReceive('updateSubscription')->once()->with([
			'plan' => 1,
			'quantity' => 5,
		]);

		$gateway = new ConektaGateway($this->mockBillableInterface(), 'plan');
		$gateway->updateQuantity($customer, 5);
	}


	public function testUpdateQuantityWithTrialEnd()
	{
		$customer = m::mock('StdClass');
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];
		$customer->shouldReceive('updateSubscription')->once()->with([
			'plan' => 1,
			'quantity' => 5,
			'trial_end' => 'now',
		]);

		$gateway = new ConektaGateway($this->mockBillableInterface(), 'plan');
		$gateway->skipTrial();
		$gateway->updateQuantity($customer, 5);
	}


	public function testUpdateQuantityAndForceTrialEnd()
	{
		$customer = m::mock('StdClass');
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];
		$customer->shouldReceive('updateSubscription')->once()->with([
			'plan' => 1,
			'quantity' => 5,
			'trial_end' => 'now',
		]);

		$gateway = new ConektaGateway($this->mockBillableInterface(), 'plan');
		$gateway->skipTrial();
		$gateway->updateQuantity($customer, 5);
	}


	public function testCancellingOfSubscriptions()
	{
		$billable = $this->mockBillableInterface();
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['current_period_end' => $time = time(), 'trial_end' => null];
		$billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function($value) use ($time)
		{
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
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['current_period_end' => $time = time(), 'trial_end' => $trialTime = time() + 50];
		$billable->shouldReceive('setSubscriptionEndDate')->once()->with(m::type('Carbon\Carbon'))->andReturnUsing(function($value) use ($trialTime)
		{
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
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,getLastFourCardDigits]', array($billable, 'plan'));
		$gateway->shouldAllowMockingProtectedMethods();
		$gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$gateway->shouldReceive('getLastFourCardDigits')->once()->andReturn('1111');
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];
		$customer->cards = m::mock('StdClass');
		$customer->cards->shouldReceive('create')->once()->with(['card' => 'token'])->andReturn($card = m::mock('StdClass'));
		$card->id = 'card_id';
		$customer->shouldReceive('save')->once();

		$billable->shouldReceive('setLastFourCardDigits')->once()->with('1111')->andReturn($billable);
		$billable->shouldReceive('saveBillableInstance')->once();

		$gateway->updateCard('token');
	}


	public function testApplyingCoupon()
	{
		$billable = $this->mockBillableInterface();
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->shouldReceive('save')->once();

		$gateway->applyCoupon('coupon-code');
		$this->assertEquals('coupon-code', $customer->coupon);
	}


	public function testRetrievingACustomersConektaPlanId()
	{
		$billable = $this->mockBillableInterface();
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer]', array($billable, 'plan'));
		$gateway->shouldReceive('getConektaCustomer')->andReturn($customer = m::mock('StdClass'));
		$customer->subscription = (object) ['plan' => (object) ['id' => 1]];

		$this->assertEquals(1, $gateway->planId());
	}


	public function testUpdatingLocalConektaData()
	{
		$billable = $this->mockBillableInterface();
		$gateway = new ConektaGateway($billable, 'plan');
		$billable->shouldReceive('setConektaId')->once()->with('id')->andReturn($billable);
		$billable->shouldReceive('setConektaPlan')->once()->with('plan')->andReturn($billable);
		$billable->shouldReceive('setLastFourCardDigits')->once()->with('last-four')->andReturn($billable);
		$billable->shouldReceive('setConektaIsActive')->once()->with(true)->andReturn($billable);
		$billable->shouldReceive('setSubscriptionEndDate')->once()->with(null)->andReturn($billable);
		$billable->shouldReceive('saveBillableInstance')->once()->andReturn($billable);
		$customer = m::mock('StdClass');
		$customer->cards = m::mock('StdClass');
		$customer->id = 'id';
		$customer->shouldReceive('getSubscriptionId')->andReturn('sub_id');
		$customer->default_card = 'default-card';
		$customer->cards->shouldReceive('retrieve')->once()->with('default-card')->andReturn((object) ['last4' => 'last-four']);

		$gateway->updateLocalConektaData($customer);
	}


	public function testMaintainTrialSetsTrialToHoursLeftOnCurrentTrial()
	{
		$billable = $this->mockBillableInterface();
		$billable->shouldReceive('readyForBilling')->once()->andReturn(true);
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,getTrialEndForCustomer]', [$billable, 'plan']);
		$gateway->shouldReceive('getConektaCustomer')->once()->andReturn($customer = m::mock('StdClass'));
		$gateway->shouldReceive('getTrialEndForCustomer')->once()->with($customer)->andReturn(Carbon\Carbon::now()->addHours(2));
		$gateway->maintainTrial();

		$this->assertEquals(2, Carbon\Carbon::now()->diffInHours($gateway->getTrialFor()));
	}


	public function testMaintainTrialDoesNothingIfNotOnTrial()
	{
		$billable = $this->mockBillableInterface();
		$billable->shouldReceive('readyForBilling')->once()->andReturn(true);
		$gateway = m::mock('Dinkbit\ConektaCashier\ConektaGateway[getConektaCustomer,getTrialEndForCustomer]', [$billable, 'plan']);
		$gateway->shouldReceive('getConektaCustomer')->once()->andReturn($customer = m::mock('StdClass'));
		$gateway->shouldReceive('getTrialEndForCustomer')->once()->with($customer)->andReturn(null);
		$gateway->maintainTrial();

		$this->assertNull($gateway->getTrialFor());
	}


	public function testGettingTheTrialEndDateForACustomer()
	{
		$time = time();
		$customer = (object) ['subscription' => (object) ['trial_end' => $time]];
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
