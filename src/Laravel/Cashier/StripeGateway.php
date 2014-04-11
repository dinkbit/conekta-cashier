<?php namespace Laravel\Cashier;

use Carbon\Carbon;
use Conekta_Invoice, Conekta_Customer;

class ConektaGateway {

	/**
	 * The billable instance.
	 *
	 * @var \Laravel\Cashier\BillableInterface
	 */
	protected $billable;

	/**
	 * The name of the plan.
	 *
	 * @var string
	 */
	protected $plan;

	/**
	 * The coupon to apply to the subscription.
	 *
	 * @var string
	 */
	protected $coupon;

	/**
	 * Indicates if the plan change should be prorated.
	 *
	 * @var bool
	 */
	protected $prorate = true;

	/**
	 * Indicates the "quantity" of the plan.
	 *
	 * @var int
	 */
	protected $quantity = 1;

	/**
	 * The trial end date that should be used when updating.
	 *
	 * @var \Carbon\Carbon
	 */
	protected $trialEnd;

	/**
	 * Indicates if the trial should be immediately cancelled for the operation.
	 *
	 * @var bool
	 */
	protected $skipTrial = false;

	/**
	 * Create a new Conekta gateway instance.
	 *
	 * @param  \Laravel\Cashier\BillableInterface   $billable
	 * @param  string|null  $plan
	 * @return void
	 */
	public function __construct(BillableInterface $billable, $plan = null)
	{
		$this->plan = $plan;
		$this->billable = $billable;
	}

	/**
	 * Subscribe to the plan for the first time.
	 *
	 * @param  string  $token
	 * @param  string  $description
	 * @param  object|null  $customer
	 * @return void
	 */
	public function create($token, $description = '', $customer = null)
	{
		if ( ! $customer)
		{
			$customer = $this->createConektaCustomer($token, $description);
		}

		$this->billable->setConektaSubscription(
			$customer->updateSubscription($this->buildPayload())->id
		);

		$this->updateLocalConektaData($this->getConektaCustomer($customer->id));
	}

	/**
	 * Build the payload for a subscription create / update.
	 *
	 * @return array
	 */
	protected function buildPayload()
	{
		$payload = [
			'plan' => $this->plan, 'prorate' => $this->prorate,
			'quantity' => $this->quantity, 'trial_end' => $this->getTrialEndForUpdate(),
		];

		if ($this->coupon) $payload['coupon'] = $this->coupon;

		return $payload;
	}

	/**
	 * Swap the billable entity to a new plan.
	 *
	 * @param  int|null  $quantity
	 * @return void
	 */
	public function swap($quantity = null)
	{
		$customer = $this->getConektaCustomer();

		// If no specific trial end date has been set, the default behavior should be
		// to maintain the current trial state, whether that is "active" or to run
		// the swap out with the exact number of days left on this current plan.
		if (is_null($this->trialEnd))
		{
			$this->maintainTrial();
		}

		// Again, if no explicit quantity was set, the default behaviors should be to
		// maintain the current quantity onto the new plan. This is a sensible one
		// that should be the expected behavior for most developers with Conekta.
		if (isset($customer->subscription) && is_null($quantity))
		{
			$this->quantity(
				$customer->subscription->quantity
			);
		}

		return $this->create(null, null, $customer);
	}

	/**
	 * Swap the billable entity to a new plan and invoice immediately.
	 *
	 * @param  int|null  $quantity
	 * @return void
	 */
	public function swapAndInvoice($quantity = null)
	{
		$this->swap($quantity);

		$this->invoice();
	}

	/**
	 * Resubscribe a customer to a given plan.
	 *
	 * @param  string  $token
	 * @return void
	 */
	public function resume($token = null)
	{
		$this->noProrate()->skipTrial()->create($token, '', $this->getConektaCustomer());

		$this->billable->setTrialEndDate(null)->saveBillableInstance();
	}

	/**
	 * Invoice the billable entity outside of regular billing cycle.
	 *
	 * @return bool
	 */
	public function invoice()
	{
		try
		{
			$customer = $this->getConektaCustomer();

			Conekta_Invoice::create(['customer' => $customer->id], $this->getConektaKey())->pay();

			return true;
		}
		catch (\Conekta_InvalidRequestError $e)
		{
			return false;
		}
	}

	/**
	 * Find an invoice by ID.
	 *
	 * @param  string  $id
	 * @return \Laravel\Cashier\Invoice|null
	 */
	public function findInvoice($id)
	{
		try
		{
			return new Invoice($this->billable, Conekta_Invoice::retrieve($id, $this->getConektaKey()));
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	/**
	 * Get an array of the entity's invoices.
	 *
	 * @param  bool  $includePending
	 * @return array
	 */
	public function invoices($includePending = false)
	{
		$invoices = [];

		$conektaInvoices = $this->getConektaCustomer()->invoices();

		// Here we will loop through the Conekta invoices and create our own custom Invoice
		// instances that have more helper methods and are generally more convenient to
		// work with than the plain Conekta objects are. Then, we'll return the array.
		if ( ! is_null($conektaInvoices))
		{
			foreach ($conektaInvoices->data as $invoice)
			{
				if ($invoice->paid || $includePending)
				{
					$invoices[] = new Invoice($this->billable, $invoice);
				}

			}
		}

		return $invoices;
	}

	/**
	 * Get all invoices, including pending.
	 *
	 * @return array
	 */
	public function allInvoices()
	{
		return $this->invoices(true);
	}

	/**
	 * Increment the quantity of the subscription.
	 *
	 * @param  int  $count
	 * @return void
	 */
	public function increment($count = 1)
	{
		$customer = $this->getConektaCustomer();

		$this->updateQuantity($customer, $customer->subscription->quantity + $count);
	}

	/**
	 *  Increment the quantity of the subscription. and invoice immediately.
	 *
	 * @param  int|null  $quantity
	 * @return void
	 */
	public function incrementAndInvoice($quantity = null)
	{
		$this->increment($quantity);

		$this->invoice();
	}

	/**
	 * Decrement the quantity of the subscription.
	 *
	 * @param  int  $count
	 * @return void
	 */
	public function decrement($count = 1)
	{
		$customer = $this->getConektaCustomer();

		$this->updateQuantity($customer, $customer->subscription->quantity - $count);
	}

	/**
	 * Update the quantity of the subscription.
	 *
	 * @param  \Conekta_Customer  $customer
	 * @param  int  $quantity
	 * @return void
	 */
	public function updateQuantity($customer, $quantity)
	{
		$subscription = [
			'plan' => $customer->subscription->plan->id,
			'quantity' => $quantity,
		];

		if ($trialEnd = $this->getTrialEndForUpdate())
		{
			$subscription['trial_end'] = $trialEnd;
		}

		$customer->updateSubscription($subscription);
	}

	/**
	 * Cancel the billable entity's subscription.
	 *
	 * @return void
	 */
	public function cancel($atPeriodEnd = true)
	{
		$customer = $this->getConektaCustomer();

		if ($customer->subscription)
		{
			if ($atPeriodEnd)
			{
				$this->billable->setSubscriptionEndDate(
					Carbon::createFromTimestamp($this->getSubscriptionEndTimestamp($customer))
				);
			}
			else
			{
				$this->billable->setSubscriptionEndDate(Carbon::now());
			}
		}

		$customer->cancelSubscription(['at_period_end' => $atPeriodEnd]);

		if ($atPeriodEnd)
		{
			$this->billable->setConektaIsActive(false)->saveBillableInstance();
		}
		else
		{
			$this->billable->deactivateConekta()->saveBillableInstance();
		}

	}

	/**
	 * Cancel the billable entity's subscription at the end of the period.
	 *
	 * @return void
	 */
	public function cancelAtEndOfPeriod()
	{
		return $this->cancel(true);
	}

	/**
	 * Cancel the billable entity's subscription immediately.
	 *
	 * @return void
	 */
	public function cancelNow()
	{
		return $this->cancel(false);
	}

	/**
	 * Get the subscription end timestamp for the customer.
	 *
	 * @param  object  $customer
	 * @return int
	 */
	protected function getSubscriptionEndTimestamp($customer)
	{
		if ( ! is_null($customer->subscription->trial_end) && $customer->subscription->trial_end > time())
		{
			return $customer->subscription->trial_end;
		}
		else
		{
			return $customer->subscription->current_period_end;
		}
	}

	/**
	 * Update the credit card attached to the entity.
	 *
	 * @param  string  $token
	 * @return void
	 */
	public function updateCard($token)
	{
		$customer = $this->getConektaCustomer();

		$customer->updateSubscription([
			'plan' => $plan = $customer->subscription->plan->id,
			'card' => $token,
		]);

		$this->updateLocalConektaData($this->getConektaCustomer(), $plan);
	}

	/**
	 * Apply a coupon to the billable entity.
	 *
	 * @param  string  $coupon
	 * @return void
	 */
	public function applyCoupon($coupon)
	{
		$customer = $this->getConektaCustomer();

		$customer->coupon = $coupon;

		$customer->save();
	}

	/**
	 * Get the plan ID for the billable entity.
	 *
	 * @return string
	 */
	public function planId()
	{
		return $this->getConektaCustomer()->subscription->plan->id;
	}

	/**
	 * Update the local Conekta data in storage.
	 *
	 * @param  \Conekta_Customer  $customer
	 * @param  string|null  $plan
	 * @return void
	 */
	public function updateLocalConektaData($customer, $plan = null)
	{
		$this->billable
				->setConektaId($customer->id)
				->setConektaPlan($plan ?: $this->plan)
				->setLastFourCardDigits($this->getLastFourCardDigits($customer))
				->setConektaIsActive(true)
				->setSubscriptionEndDate(null)
				->saveBillableInstance();
	}

	/**
	 * Create a new Conekta customer instance.
	 *
	 * @param  string  $token
	 * @param  string  $description
	 * @return \Conekta_Customer
	 */
	public function createConektaCustomer($token, $description)
	{
		$customer = Conekta_Customer::create([
			'card' => $token,
			'description' => $description,

		], $this->getConektaKey());

		return $this->getConektaCustomer($customer->id);
	}

	/**
	 * Get the Conekta customer for entity.
	 *
	 * @return \Conekta_Customer
	 */
	public function getConektaCustomer($id = null)
	{
		$customer = Customer::retrieve($id ?: $this->billable->getConektaId(), $this->getConektaKey());

		if ($this->usingMultipleSubscriptionApi($customer))
		{
			$customer->subscription = $customer->findSubscription($this->billable->getConektaSubscription());
		}

		return $customer;
	}

	/**
	 * Deteremine if the customer has a subscription.
	 *
	 * @param  \Conekta_Customer  $customer
	 * @return bool
	 */
	protected function usingMultipleSubscriptionApi($customer)
	{
		return ! isset($customer->subscription) &&
                 count($customer->subscriptions) > 0 &&
                 ! is_null($this->billable->getConektaSubscription());
	}

	/**
	 * Get the last four credit card digits for a customer.
	 *
	 * @param  \Conekta_Customer  $customer
	 * @return string
	 */
	protected function getLastFourCardDigits($customer)
	{
		return $customer->cards->retrieve($customer->default_card)->last4;
	}

	/**
	 * The coupon to apply to a new subscription.
	 *
	 * @param  string  $coupon
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function withCoupon($coupon)
	{
		$this->coupon = $coupon;

		return $this;
	}

	/**
	 * Indicate that the plan change should be prorated.
	 *
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function prorate()
	{
		$this->prorate = true;

		return $this;
	}

	/**
	 * Indicate that the plan change should not be prorated.
	 *
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function noProrate()
	{
		$this->prorate = false;

		return $this;
	}

	/**
	 * Set the quantity to apply to the subscription.
	 *
	 * @param  int  $quantity
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function quantity($quantity)
	{
		$this->quantity = $quantity;

		return $this;
	}

	/**
	 * Indicate that no trial should be enforced on the operation.
	 *
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function skipTrial()
	{
		$this->skipTrial = true;

		return $this;
	}

	/**
	 * Specify the ending date of the trial.
	 *
	 * @param  \DateTime  $trialEnd
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function trialFor(\DateTime $trialEnd)
	{
		$this->trialEnd = $trialEnd;

		return $this;
	}

	/**
	 * Get the current trial end date for subscription change.
	 *
	 * @return \DateTime
	 */
	public function getTrialFor()
	{
		return $this->trialEnd;
	}

	/**
	 * Get the trial end timestamp for a Conekta subscription update.
	 *
	 * @return int
	 */
	protected function getTrialEndForUpdate()
	{
		if ($this->skipTrial) return 'now';

		return $this->trialEnd ? $this->trialEnd->getTimestamp() : null;
	}

	/**
	 * Maintain the days left of the current trial (if applicable).
	 *
	 * @return \Laravel\Cashier\ConektaGateway
	 */
	public function maintainTrial()
	{
		if ($this->billable->readyForBilling())
		{
			if ( ! is_null($trialEnd = $this->getTrialEndForCustomer($this->getConektaCustomer())))
			{
				$this->calculateRemainingTrialDays($trialEnd);
			}
			else
			{
				$this->skipTrial();
			}
		}

		return $this;
	}

	/**
	 * Get the trial end date for the customer's subscription.
	 *
	 * @param  object  $customer
	 * @return \Carbon\Carbon|null
	 */
	public function getTrialEndForCustomer($customer)
	{
		if (isset($customer->subscription) && isset($customer->subscription->trial_end))
		{
			return Carbon::createFromTimestamp($customer->subscription->trial_end);
		}
	}

	/**
	 * Calculate the remaining trial days based on the current trial end.
	 *
	 * @param  \Carbon\Carbon  $trialEnd
	 * @return void
	 */
	protected function calculateRemainingTrialDays($trialEnd)
	{
		// If there is still trial left on the current plan, we'll maintain that amount of
		// time on the new plan. If there is no time left on the trial we will force it
		// to skip any trials on this new plan, as this is the most expected actions.
		$diff = Carbon::now()->diffInHours($trialEnd);

		return $diff > 0 ? $this->trialFor(Carbon::now()->addHours($diff)) : $this->skipTrial();
	}

	/**
	 * Get the Conekta API key for the instance.
	 *
	 * @return string
	 */
	protected function getConektaKey()
	{
		return $this->billable->getConektaKey();
	}

}
