<?php

namespace Dinkbit\ConektaCashier\Contracts;

interface Billable
{
    /**
     * Get the name that should be shown on the entity's invoices.
     *
     * @return string
     */
    public function getBillableName();

    /**
     * Write the entity to persistent storage.
     *
     * @return void
     */
    public function saveBillableInstance();

    /**
     * Get a new billing builder instance for the given plan.
     *
     * @param string|null $plan
     *
     * @return \Dinkbit\ConektaCashier\Builder
     */
    public function subscription($plan = null);

    /**
     * Determine if the entity is within their trial period.
     *
     * @return bool
     */
    public function onTrial();

    /**
     * Determine if the entity has an active subscription.
     *
     * @return bool
     */
    public function subscribed();

    /**
     * Determine if the entity's trial has expired.
     *
     * @return bool
     */
    public function expired();

    /**
     * Determine if the entity is on the given plan.
     *
     * @param string $plan
     *
     * @return bool
     */
    public function onPlan($plan);

    /**
     * Determine if billing requires a credit card up front.
     *
     * @return bool
     */
    public function requiresCardUpFront();

    /**
     * Determine if the entity is a Conekta customer.
     *
     * @return bool
     */
    public function readyForBilling();

    /**
     * Determine if the entity has a current Conekta subscription.
     *
     * @return bool
     */
    public function conektaIsActive();

    /**
     * Set whether the entity has a current Conekta subscription.
     *
     * @param bool $active
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function setConektaIsActive($active = true);

    /**
     * Set Conekta as inactive on the entity.
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function deactivateConekta();

    /**
     * Get the Conekta ID for the entity.
     *
     * @return string
     */
    public function getConektaId();

    /**
     * Set the Conekta ID for the entity.
     *
     * @param string $conekta_id
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function setConektaId($conekta_id);

    /**
     * Get the current subscription ID.
     *
     * @return string
     */
    public function getConektaSubscription();

    /**
     * Set the current subscription ID.
     *
     * @param string $subscription_id
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function setConektaSubscription($subscription_id);

    /**
     * Get the last four digits of the entity's credit card.
     *
     * @return string
     */
    public function getLastFourCardDigits();

    /**
     * Set the last four digits of the entity's credit card.
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function setLastFourCardDigits($digits);

    /**
     * Get the brand of the entity's credit card.
     *
     * @return string
     */
    public function getCardType();

    /**
     * Set the brand of the entity's credit card.
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function setCardType($type);

    /**
     * Get the date on which the trial ends.
     *
     * @return \DateTime
     */
    public function getTrialEndDate();

    /**
     * Set the date on which the trial ends.
     *
     * @param \DateTime|null $date
     *
     * @return \Dinkbit\ConektaCashier\Contracts\Billable
     */
    public function setTrialEndDate($date);

    /**
     * Get the subscription end date for the entity.
     *
     * @return \DateTime
     */
    public function getSubscriptionEndDate();

    /**
     * Set the subscription end date for the entity.
     *
     * @param \DateTime|null $date
     *
     * @return void
     */
    public function setSubscriptionEndDate($date);

    /**
     * Get the Conekta supported currency used by the entity.
     *
     * @return string
     */
    public function getCurrency();

    /**
     * Get the locale for the currency used by the entity.
     *
     * @return string
     */
    public function getCurrencyLocale();

    /**
     * Format the given currency for display, without the currency symbol.
     *
     * @param int $amount
     *
     * @return mixed
     */
    public function formatCurrency($amount);

    /**
     * Add the currency symbol to a given amount.
     *
     * @param string $amount
     *
     * @return string
     */
    public function addCurrencySymbol($amount);

    /**
     * Get the Conekta API key.
     *
     * @return string
     */
    public static function getConektaKey();

    /**
     * Deteremine if the entity has a Conekta customer ID.
     *
     * @return bool
     */
    public function hasConektaId();
}
