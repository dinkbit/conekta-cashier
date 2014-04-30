<?php namespace Laravel\Cashier;

use Conekta_Customer;
use Conekta_Subscription;

class Customer extends Conekta_Customer {

	/**
	 * The subscription being managed by Cashier.
	 *
	 * @var \Conekta_Subscription
	 */
	public $subscription;

	/**
	 * {@inheritdoc}
	 */
	public static function retrieve($id, $apiKey = null)
	{
		return self::_scpFind(get_called_class(), $id);
	}

	/**
	 * Get the current subscription ID.
	 *
	 * @return string|null
	 */
	public function getConektaSubscription()
	{
		return $this->subscription ? $this->subscription->id : null;
	}

	/**
	 * Find a subscription by ID.
	 *
	 * @param  string  $id
	 * @return \Conekta_Subscription|null
	 */
	public function findSubscription($id)
	{
		if ($this->subscription->id == $id) return $this->subscription;
	}

	/**
	 * Create the current subscription with the given data.
	 *
	 * @param  $params
	 * @return void
	 */
	public function _createSubscription($params = null)
	{
		return $this->subscription = $this->createSubscription(array('plan' => $params['plan']));
	}

	/**
	 * Update the current subscription with the given data.
	 *
	 * @param  array  $params
	 * @return \Conekta_Subscription
	 */
	public function updateSubscription($params = null)
	{
		if (is_null($this->subscription))
		{
			return $this->_createSubscription($params);
		}
		else
		{
			return $this->saveSubscription($params);
		}
	}

	/**
	 * Save the current subscription with the given parameters.
	 *
	 * @param  array  $params
	 * @return \Conekta_Subscription
	 */
	protected function saveSubscription($params)
	{
		foreach ($params as $key => $value)
		{
			$this->subscription->{$key} = $value;
		}

		$this->subscription->save();

		return $this->subscription;
	}

	/**
	 * Cancel the current subscription.
	 *
	 * @param  array  $params
	 * @return void
	 */
	public function cancelSubscription($params = null)
	{
		return $this->subscription->cancel($params);
	}

}
