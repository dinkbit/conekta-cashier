<?php

namespace Dinkbit\ConektaCashier;

use Conekta_Customer;
use Conekta_Subscription;

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
        if (is_null($this->subscription)) {
            return $this->_createSubscription($params);
        } else {
            $billable = $this->getBillable($this->id);

            if ($billable) {
                if ($billable->onTrial()) {
                    $this->subscription->resume();
                }

                if ($billable->expired() || $billable->cancelled()) {
                    return $this->_createSubscription($params);
                }
            }

            return $this->_updateSubscription($params);
        }
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
     * Get the user entity by Conekta ID.
     *
     * @param string $conektaId
     *
     * @return \Dinkbit\ConektaCashier\BillableInterface
     */
    protected function getBillable($conektaId)
    {
        return \App::make('Dinkbit\ConektaCashier\BillableRepositoryInterface')->find($conektaId);
    }
}
