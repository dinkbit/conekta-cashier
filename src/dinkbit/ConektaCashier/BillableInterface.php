<?php namespace dinkbit\ConektaCashier;

use DateTime;

interface BillableInterface {

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
	 * @param  \dinkbit\ConektaCashier\PlanInterface|string|null  $plan
	 * @return \dinkbit\ConektaCashier\Builder
	 */
	public function subscription($plan = null);

	/**
	 * Invoice the billable entity outside of regular billing cycle.
	 *
	 * @return void
	 */
	public function invoice();

	/**
	 * Find an invoice by ID.
	 *
	 * @param  string  $id
	 * @return \dinkbit\ConektaCashier\Invoice|null
	 */
	public function findInvoice($id);

	/**
	 * Get an array of the entity's invoices.
	 *
	 * @return array
	 */
	public function invoices();

	/**
	 * Apply a coupon to the billable entity.
	 *
	 * @param  string  $coupon
	 * @return void
	 */
	public function applyCoupon($coupon);

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
	 * @param  \dinkbit\ConektaCashier\PlanInterface|string  $plan
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
	 * @param  bool  $active
	 * @return \dinkbit\ConektaCashier\BillableInterface
	 */
	public function setConektaIsActive($active = true);

	/**
	 * Set Conekta as inactive on the entity.
	 *
	 * @return \dinkbit\ConektaCashier\BillableInterface
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
	 * @param  string  $conekta_id
	 * @return \dinkbit\ConektaCashier\BillableInterface
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
	 * @param  string  $subscription_id
	 * @return \dinkbit\ConektaCashier\BillableInterface
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
	 * @return \dinkbit\ConektaCashier\BillableInterface
	 */
	public function setLastFourCardDigits($digits);

	/**
	 * Get the date on which the trial ends.
	 *
	 * @return \DateTime
	 */
	public function getTrialEndDate();

	/**
	 * Get the subscription end date for the entity.
	 *
	 * @return \DateTime
	 */
	public function getSubscriptionEndDate();

	/**
	 * Set the subscription end date for the entity.
	 *
	 * @param  \DateTime|null  $date
	 * @return void
	 */
	public function setSubscriptionEndDate($date);

}
