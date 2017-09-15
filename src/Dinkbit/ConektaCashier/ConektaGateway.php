<?php

namespace Dinkbit\ConektaCashier;

use Carbon\Carbon;
use Conekta;
use Conekta_Charge;
use Conekta_Customer;
use Conekta_Error;
use Dinkbit\ConektaCashier\Contracts\Billable as BillableContract;
use InvalidArgumentException;

class ConektaGateway
{
    /**
     * The billable instance.
     *
     * @var \Dinkbit\ConektaCashier\Contracts\Billable
     */
    protected $billable;

    /**
     * The name of the plan.
     *
     * @var string
     */
    protected $plan;

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
     * @param \Dinkbit\ConektaCashier\Contracts\Billable $billable
     * @param string|null                                $plan
     *
     * @return void
     */
    public function __construct(BillableContract $billable, $plan = null)
    {
        $this->plan = $plan;
        $this->billable = $billable;

        Conekta::setApiKey($billable->getConektaKey());
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param int   $amount
     * @param array $options
     *
     * @return bool|mixed
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => 'mxn',
        ], $options);

        $options['amount'] = $amount;

        if (!array_key_exists('card', $options) && $this->billable->hasConektaId()) {
            $options['card'] = $this->billable->getConektaId();
        }

        if (!array_key_exists('card', $options)) {
            throw new InvalidArgumentException('No payment source provided.');
        }

        try {
            $response = Conekta_Charge::create($options);
        } catch (Conekta_Error $e) {
            return false;
        }

        return $response;
    }

    /**
     * Subscribe to the plan for the first time.
     *
     * @param string      $token
     * @param array       $properties
     * @param object|null $customer
     *
     * @return void
     */
    public function create($token, array $properties = [], $customer = null)
    {
        $freshCustomer = false;

        if (!$customer) {
            $customer = $this->createConektaCustomer($token, $properties);

            $freshCustomer = true;
        } elseif (!is_null($token)) {
            $this->updateCard($token);
        }

        $this->billable->setConektaSubscription(
            $customer->updateSubscription($this->buildPayload())->id
        );

        $customer = $this->getConektaCustomer($customer->id);

        if ($freshCustomer && $trialEnd = $this->getTrialEndForCustomer($customer)) {
            $this->billable->setTrialEndDate($trialEnd);
        }

        $this->updateLocalConektaData($customer);
    }

    /**
     * Build the payload for a subscription create / update.
     *
     * @return array
     */
    protected function buildPayload()
    {
        $payload = ['plan' => $this->plan];

        if ($trialEnd = $this->getTrialEndForUpdate()) {
            $payload['trial_end'] = $trialEnd;
        }

        return $payload;
    }

    /**
     * Swap the billable entity to a new plan.
     *
     * @return void
     */
    public function swap()
    {
        $customer = $this->getConektaCustomer();

        // If no specific trial end date has been set, the default behavior should be
        // to maintain the current trial state, whether that is "active" or to run
        // the swap out with the exact number of days left on this current plan.
        if (is_null($this->trialEnd)) {
            $this->maintainTrial();
        }

        return $this->create(null, [], $customer);
    }

    /**
     * Resubscribe a customer to a given plan.
     *
     * @param string $token
     *
     * @return void
     */
    public function resume($token = null)
    {
        $this->skipTrial()->create($token, [], $this->getConektaCustomer());

        $this->billable->setTrialEndDate(null)->saveBillableInstance();
    }

    /**
     * Cancel the billable entity's subscription.
     *
     * @return void
     */
    public function cancel($atPeriodEnd = true)
    {
        $customer = $this->getConektaCustomer();

        if ($customer->subscription) {
            if ($atPeriodEnd) {
                $this->billable->setSubscriptionEndDate(
                    Carbon::createFromTimestamp($this->getSubscriptionEndTimestamp($customer))
                );
            }

            $customer->cancelSubscription(['at_period_end' => $atPeriodEnd]);
        }

        if ($atPeriodEnd) {
            $this->billable->setConektaIsActive(false)->saveBillableInstance();
        } else {
            $this->billable->setSubscriptionEndDate(Carbon::now());

            $this->billable->deactivateConekta()->saveBillableInstance();
        }
    }

    /**
     * Extend a subscription trial end datetime.
     *
     * @param \DateTime $trialEnd
     *
     * @return void
     */
    public function extendTrial(\DateTime $trialEnd)
    {
        $customer = $this->getConektaCustomer();

        if ($customer->subscription) {
            $customer->updateSubscription(['trial_end' => $trialEnd->format(DateTime::ISO8601)]);

            $this->billable->setTrialEndDate($trialEnd)->saveBillableInstance();
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
     * @param \Conekta_Customer $customer
     *
     * @return int
     */
    protected function getSubscriptionEndTimestamp($customer)
    {
        if (!is_null($customer->subscription->trial_end) && $customer->subscription->trial_end > time()) {
            return $customer->subscription->trial_end;
        } else {
            return $customer->subscription->billing_cycle_end;
        }
    }

    /**
     * Get the current subscription period's end date.
     *
     * @return \Carbon\Carbon
     */
    public function getSubscriptionEndDate()
    {
        $customer = $this->getConektaCustomer();

        return Carbon::createFromTimestamp($this->getSubscriptionEndTimestamp($customer));
    }

    /**
     * Update the credit card attached to the entity.
     *
     * @param string $token
     *
     * @return void
     */
    public function updateCard($token)
    {
        $customer = $this->getConektaCustomer();

        $card = $customer->createCard(['token' => $token]);

        $customer->update(['default_card_id' => $card->id]);

        if ($customer->subscription) {
            $customer->updateSubscription(['card' => $card->id]);

            $this->billable
                    ->setLastFourCardDigits($this->getLastFourCardDigits($customer))
                    ->setCardType($this->getCardType($customer))
                    ->saveBillableInstance();
        }

        return $card;
    }

    /**
     * Get the plan ID for the billable entity.
     *
     * @return string
     */
    public function planId()
    {
        $customer = $this->getConektaCustomer();

        if (isset($customer->subscription)) {
            return $customer->subscription->plan_id;
        }
    }

    /**
     * Update the local Conekta data in storage.
     *
     * @param \Conekta_Customer $customer
     * @param string|null       $plan
     *
     * @return void
     */
    public function updateLocalConektaData($customer, $plan = null)
    {
        $this->billable
                ->setConektaId($customer->id)
                ->setConektaPlan($plan ?: $this->plan)
                ->setLastFourCardDigits($this->getLastFourCardDigits($customer))
                ->setCardType($this->getCardType($customer))
                ->setConektaIsActive(true)
                ->setSubscriptionEndDate(null)
                ->saveBillableInstance();
    }

    /**
     * Create a new Conekta customer instance.
     *
     * @param string $token
     * @param array  $properties
     *
     * @return \Conekta_Customer
     */
    public function createConektaCustomer($token, array $properties = [])
    {
        $customer = Conekta_Customer::create(
            array_merge(['cards' => [$token]], $properties), $this->getConektaKey()
        );

        return $this->getConektaCustomer($customer->id);
    }

    /**
     * Get the Conekta customer for entity.
     *
     * @return \Conekta_Customer
     */
    public function getConektaCustomer($id = null)
    {
        $customer = Customer::retrieve($id ?: $this->billable->getConektaId());

        return $customer;
    }

    /**
     * Get the last four credit card digits for a customer.
     *
     * @param \Conekta_Customer $customer
     *
     * @return string
     */
    protected function getLastFourCardDigits($customer)
    {
        if (empty($customer->cards[0])) {
            return;
        }

        if ($customer->default_card_id) {
            foreach ($customer->cards as $card) {
                if ($card->id == $customer->default_card_id) {
                    return $card->last4;
                }
            }

            return;
        }

        return $customer->cards[0]->last4;
    }

    /**
     * Get the last four credit card digits for a customer.
     *
     * @param \Conekta_Customer $customer
     *
     * @return string
     */
    protected function getCardType($customer)
    {
        if (empty($customer->cards[0])) {
            return;
        }

        if ($customer->default_card_id) {
            foreach ($customer->cards as $card) {
                if ($card->id == $customer->default_card_id) {
                    return $card->brand;
                }
            }

            return;
        }

        return $customer->cards[0]->brand;
    }

    /**
     * Indicate that no trial should be enforced on the operation.
     *
     * @return \Dinkbit\ConektaCashier\ConektaGateway
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param \DateTime $trialEnd
     *
     * @return \Dinkbit\ConektaCashier\ConektaGateway
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
     * Maintain the days left of the current trial (if applicable).
     *
     * @return \Dinkbit\ConektaCashier\ConektaGateway
     */
    public function maintainTrial()
    {
        if ($this->billable->readyForBilling()) {
            if (!is_null($trialEnd = $this->getTrialEndForCustomer($this->getConektaCustomer()))) {
                $this->calculateRemainingTrialDays($trialEnd);
            } else {
                $this->skipTrial();
            }
        }

        return $this;
    }

    /**
     * Get the trial end timestamp for a Conekta subscription update.
     *
     * @return int
     */
    protected function getTrialEndForUpdate()
    {
        if ($this->skipTrial) {
            return Carbon::now()->toIso8601String();
        }

        return $this->trialEnd ? $this->trialEnd->toIso8601String() : null;
    }

    /**
     * Get the trial end date for the customer's subscription.
     *
     * @param object $customer
     *
     * @return \Carbon\Carbon|null
     */
    public function getTrialEndForCustomer($customer)
    {
        if (isset($customer->subscription) && $customer->subscription->status == 'in_trial' && isset($customer->subscription->trial_end)) {
            return Carbon::createFromTimestamp($customer->subscription->trial_end);
        }
    }

    /**
     * Calculate the remaining trial days based on the current trial end.
     *
     * @param \Carbon\Carbon $trialEnd
     *
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

    /**
     * Get the currency for the billable entity.
     *
     * @return string
     */
    protected function getCurrency()
    {
        return $this->billable->getCurrency();
    }
}
