<?php

namespace Dinkbit\ConektaCashier;

use Conekta_Customer;

class Customer extends Conekta_Customer
{
    /**
     * The subscription being managed by Conekta Cashier.
     *
     * @var \Conekta_Subscription
     */
    public $subscription;

    /**
     * {@inheritdoc}
     */
    public static function retrieve($id, $apiKey = null)
    {
        return self::_scpFind(get_called_class(), $id, $apiKey);
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
     * @param string $id
     *
     * @return \Conekta_Subscription|null
     */
    public function findSubscription($id)
    {
        if ($this->subscription->id == $id) {
            return $this->subscription;
        }
    }

    /**
     * Create the current subscription with the given data.
     *
     * @param array $params
     *
     * @return void
     */
    protected function _createSubscription(array $params)
    {
        return $this->subscription = $this->createSubscription($params);
    }

    /**
     * Update the current subscription with the given data.
     *
     * @param array $params
     *
     * @return \Conekta_Subscription
     */
    public function updateSubscription($params = null)
    {
        if (is_null($this->subscription) || $this->subscription->status == 'canceled') {
            return $this->_createSubscription($params);
        }

        return $this->_updateSubscription($params);
    }

    /**
     * Update the current subscription with the given data.
     *
     * @param  $params
     *
     * @return void
     */
    public function _updateSubscription($params = null)
    {
        return $this->subscription = $this->subscription->update($params);
    }

    /**
     * Cancel the current subscription.
     *
     * @param array $params
     *
     * @return void
     */
    public function cancelSubscription($params = null)
    {
        return $this->subscription->cancel($params);
    }

    /**
     * Pause the current subscription.
     *
     * @return void
     */
    public function pauseSubscription()
    {
        return $this->subscription = $this->subscription->pause();
    }

    /**
     * Resume the current subscription.
     *
     * @return void
     */
    public function resumeSubscription()
    {
        return $this->subscription = $this->subscription->resume();
    }
}
