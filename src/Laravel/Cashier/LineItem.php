<?php namespace Laravel\Cashier;

class LineItem {

	/**
	 * The Conekta invoice line instance.
	 *
	 * @var object
	 */
	protected $conektaLine;

	/**
	 * Create a new line item instance.
	 *
	 * @param  object  $conektaLine
	 * @return void
	 */
	public function __construct($conektaLine)
	{
		$this->conektaLine = $conektaLine;
	}

	/**
	 * Get the total amount for the line item in dollars.
	 *
	 * @return string
	 */
	public function dollars()
	{
		if (starts_with($total = $this->total(), '-'))
		{
			return '-$'.ltrim($total, '-');
		}
		else
		{
			return '$'.$total;
		}
	}

	/**
	 * Get the total for the line item.
	 *
	 * @return float
	 */
	public function total()
	{
		return number_format($this->amount / 100, 2);
	}

	/**
	 * Get a human readable date for the start date.
	 *
	 * @return string
	 */
	public function startDateString()
	{
		if ($this->isSubscription())
		{
			return date('M j, Y', $this->period->start);
		}
	}

	/**
	 * Get a human readable date for the end date.
	 *
	 * @return string
	 */
	public function endDateString()
	{
		if ($this->isSubscription())
		{
			return date('M j, Y', $this->period->end);
		}
	}

	/**
	 * Determine if the line item is for a subscription.
	 *
	 * @return bool
	 */
	public function isSubscription()
	{
		return $this->type == 'subscription';
	}

	/**
	 * Get the Conekta line item instance.
	 *
	 * @return object
	 */
	public function getConektaLine()
	{
		return $this->conektaLine;
	}

	/**
	 * Dynamically access the Conekta line item instance.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->conektaLine->{$key};
	}

	/**
	 * Dynamically set values on the Conekta line item instance.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return mixed
	 */
	public function __set($key, $value)
	{
		$this->conektaLine->{$key} = $value;
	}

}
