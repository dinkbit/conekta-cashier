# Laravel Conekta Cashier

[![Build Status](https://img.shields.io/travis/dinkbit/conekta-cashier.svg?style=flat-square)](https://travis-ci.org/dinkbit/conekta-cashier)
[![StyleCI](https://styleci.io/repos/22849643/shield)](https://styleci.io/repos/22849643)

[![image](https://s3.amazonaws.com/dinkbit/img/firmas/firma_dinkbit.png)](<http://dinkbit.com>)

Port of Stripe [Laravel Cashier](https://github.com/laravel/cashier) to Conekta

Please note the latest version of Laravel Cashier supports Laravel 5+, if you are looking for the Laravel 4 implementation see the [1.0](https://github.com/dinkbit/conekta-cashier/tree/1.0) branch.

___

# Laravel Cashier

- [Introduction](#introduction)
- [Configuration](#configuration)
- [Subscribing To A Plan](#subscribing-to-a-plan)
- [Single Charges](#single-charges)
- [Swapping Subscriptions](#swapping-subscriptions)
- [Cancelling A Subscription](#cancelling-a-subscription)
- [Resuming A Subscription](#resuming-a-subscription)
- [Checking Subscription Status](#checking-subscription-status)
- [Handling Failed Payments](#handling-failed-payments)
- [Handling Other Conekta Webhooks](#handling-other-conekta-webhooks)

<a name="introduction"></a>
## Introduction

Laravel Cashier provides an expressive, fluent interface to [Conekta's](https://conekta.com) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, subscription "quantities", cancellation grace periods, and even generate invoice PDFs.

<a name="configuration"></a>
## Configuration

#### Composer

First, add the Cashier package to your `composer.json` file:

	"dinkbit/conekta-cashier": "~2.0" (For Conekta 1.0.0 PHP-SDK 2.0)

#### Service Provider

Next, register the `Dinkbit\ConektaCashier\CashierServiceProvider` in your `app` configuration file.

#### Migration

Before using Cashier, we'll need to add several columns to your database. Don't worry, you can use the `conekta-cashier:table` Artisan command to create a migration to add the necessary column. For example, to add the column to the users table use `php artisan conekta-cashier:table users`. Once the migration has been created, simply run the `migrate` command.

#### Model Setup

Next, add the `Billable` trait and appropriate date mutators to your model definition:

	use Dinkbit\ConektaCashier\Billable;
	use Dinkbit\ConektaCashier\Contracts\Billable as BillableContract;

	class User extends Eloquent implements BillableContract {

		use Billable;

		protected $dates = ['trial_ends_at', 'subscription_ends_at'];

	}

#### Conekta Key

Finally, set your Conekta key in your `services.php` config file:

	'conekta' => [
		'model'  => 'User',
		'secret' => env('CONEKTA_API_SECRET'),
	],

Alternatively you can store it in one of your bootstrap files or service providers, such as the `AppServiceProvider`:

	User::setConektaKey('conekta-key');

## Subscribing To A Plan

Once you have a model instance, you can easily subscribe that user to a given Conekta plan:

	$user = User::find(1);

	$user->subscription('monthly')->create($creditCardToken);

You can also extend a subscription trial.

	$subscription = $user->subscription('monthly')->create($creditCardToken);

	$user->extendTrial(Carbon::now()->addMonth());

The `subscription` method will automatically create the Conekta subscription, as well as update your database with Conekta customer ID and other relevant billing information. If your plan has a trial configured in Conekta, the trial end date will also automatically be set on the user record.

If your plan has a trial period that is **not** configured in Conekta, you must set the trial end date manually after subscribing:

	$user->trial_ends_at = Carbon::now()->addDays(14);

	$user->save();

### Specifying Additional User Details

If you would like to specify additional customer details, you may do so by passing them as second argument to the `create` method:

	$user->subscription('monthly')->create($creditCardToken, [
		'email' => $email, 'name' => 'Joe Doe'
	]);

To learn more about the additional fields supported by Conekta, check out Conekta's [documentation on customer creation](https://www.conekta.io/es/docs/api#crear-cliente).

## Single Charges

If you would like to make a "one off" charge against a subscribed customer's credit card, you may use the `charge` method:

	$user->charge(100);

The `charge` method accepts the amount you would like to charge in the **lowest denominator of the currency**. So, for example, the example above will charge 100 cents, or $1.00, against the user's credit card.

The `charge` method accepts an array as its second argument, allowing you to pass any options you wish to the underlying Conekta charge creation:

	$user->charge(100, [
		'card' => $token,
	]);

The `charge` method will return `false` if the charge fails. This typically indicates the charge was denied:

	if ( ! $user->charge(100))
	{
		// The charge was denied...
	}

If the charge is successful, the full Conekta response will be returned from the method.

## Swapping Subscriptions

To swap a user to a new subscription, use the `swap` method:

	$user->subscription('premium')->swap();

If the user is on trial, the trial will be maintained as normal. Also, if a "quantity" exists for the subscription, that quantity will also be maintained.

## Cancelling A Subscription

Cancelling a subscription is a walk in the park:

	$user->subscription()->cancel();

When a subscription is cancelled, Cashier will automatically set the `subscription_ends_at` column on your database. This column is used to know when the `subscribed` method should begin returning `false`. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the `subscribed` method will continue to return `true` until March 5th.

## Resuming A Subscription

If a user has cancelled their subscription and you wish to resume it, use the `resume` method:

	$user->subscription('monthly')->resume($creditCardToken);

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Their subscription will simply be re-activated, and they will be billed on the original billing cycle.

## Checking Subscription Status

To verify that a user is subscribed to your application, use the `subscribed` command:

	if ($user->subscribed())
	{
		//
	}

The `subscribed` method makes a great candidate for a [route middleware](/docs/5.0/middleware):

	public function handle($request, Closure $next)
	{
		if ($request->user() && ! $request->user()->subscribed())
		{
			return redirect('billing');
		}

		return $next($request);
	}

You may also determine if the user is still within their trial period (if applicable) using the `onTrial` method:

	if ($user->onTrial())
	{
		//
	}

To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the `cancelled` method:

	if ($user->cancelled())
	{
		//
	}

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was scheduled to end on March 10th, the user is on their "grace period" until March 10th. Note that the `subscribed` method still returns `true` during this time.

	if ($user->onGracePeriod())
	{
		//
	}

The `everSubscribed` method may be used to determine if the user has ever subscribed to a plan in your application:

	if ($user->everSubscribed())
	{
		//
	}

The `onPlan` method may be used to determine if the user is subscribed to a given plan based on its ID:

	if ($user->onPlan('monthly'))
	{
		//
	}

## Handling Failed Payments

What if a customer's credit card expires? No worries - Cashier includes a Webhook controller that can easily cancel the customer's subscription for you. Just point a route to the controller:

	Route::post('conekta/webhook', 'Dinkbit\ConektaCashier\WebhookController@handleWebhook');

That's it! Failed payments will be captured and handled by the controller. The controller will cancel the customer's subscription after three failed payment attempts. The `conekta/webhook` URI in this example is just for example. You will need to configure the URI in your Conekta settings.

<a name="handling-other-conekta-webhooks"></a>
## Handling Other Conekta Webhooks

If you have additional Conekta webhook events you would like to handle, simply extend the Webhook controller. Your method names should correspond to Cashier's expected convention, specifically, methods should be prefixed with `handle` and the name of the Conekta webhook you wish to handle. For example, if you wish to handle the `invoice.payment_succeeded` webhook, you should add a `handleInvoicePaymentSucceeded` method to the controller.

	class WebhookController extends Dinkbit\ConektaCashier\WebhookController {

		public function handleInvoicePaymentSucceeded($payload)
		{
			// Handle The Event
		}

	}

> **Note:** In addition to updating the subscription information in your database, the Webhook controller will also cancel the subscription via the Conekta API.

### Todo

- [ ] Add Invoices support when Conekta has them.
