# Laravel Conekta-Cashier
by [dinkbit](<http://dinkbit.com>)

[![image](http://dinkbit.com/images/firmadinkbit.png)](<http://dinkbit.com>)

___

> **Warning**: Beta version.
>
> Based on [Laravel Cashier](https://github.com/laravel/cashier)

##### Todo

- [ ] Update and review tests
- [ ] Change StripeGateway Test for ConektaGateway Test
- [ ] Add Invoices support
___


- [Configuration](#configuration)
- [Subscribing To A Plan](#subscribing-to-a-plan)
- [No Card Up Front](#no-card-up-front)
- [Cancelling A Subscription](#cancelling-a-subscription)
- [Resuming A Subscription](#resuming-a-subscription)
- [Checking Subscription Status](#checking-subscription-status)
- [Handling Failed Payments](#handling-failed-payments)

<a name="configuration"></a>
## Configuration

> **Note:** Because of its use of traits, Conekta-Cashier requires PHP 5.4 or greater.

dinkbit Conekta-Cashier provides an expressive, fluent interface to [Conekta's](https://conekta.io) subscription billing services.

#### Composer

First, add the Conekta-Cashier package to your `composer.json` file:

	"dinkbit/conekta-cashier": "~0.8"

#### Service Provider

Next, register the `Dinkbit\ConektaCashier\CashierServiceProvider` in your `app` configuration file.

#### Migration

Before using Conekta-Cashier, we'll need to add several columns to your database. Don't worry, you can use the `cashier:table` Artisan command to create a migration to add the necessary column. Once the migration has been created, simply run the `migrate` command.

#### Model Setup

Next, add the BillableTrait and appropriate date mutators to your model definition:

```php
use Dinkbit\ConektaCashier\BillableTrait;
use Dinkbit\ConektaCashier\BillableInterface;

class User extends Eloquent implements BillableInterface {

	use BillableTrait;

	protected $dates = ['trial_ends_at', 'subscription_ends_at'];

}
```

#### Create the Conekta config file

Create the configuration file `app/config/conekta.php` and setup your keys and the Model on which you will use Cashier

```php
	return array(
		'secret_key' => 'conekta-key',
		'public_key' => 'public-conekta-key',
		'model' => 'User'
	);
```

#### Conekta Key

Finally, set your Conekta key in one of your bootstrap files:

```php
User::setConektaKey(Config::get('conekta.secret_key'));
```

<a name="subscribing-to-a-plan"></a>
## Subscribing To A Plan

Once you have a model instance, you can easily subscribe that user to a given Conekta plan:

```php
$user = User::find(1);

$user->subscription('monthly')->create($creditCardToken);
```

If you would like to apply a coupon when creating the subscription, you may use the `withCoupon` method:

```php
$user->subscription('monthly')
     ->create($creditCardToken);
```

The `subscription` method will automatically create the Conekta subscription, as well as update your database with Conekta customer ID and other relevant billing information. If your plan includes a trial, the trial end date will also automatically be set on the user record.

If your plan has a trial period, make sure to set the trial end date on your model after subscribing:

```php
$user->trial_ends_at = Carbon::now()->addDays(14);

$user->save();
```

<a name="no-card-up-front"></a>
## No Card Up Front

If your application offers a free-trial with no credit-card up front, set the `cardUpFront` property on your model to `false`:

```php
protected $cardUpFront = false;
```

On account creation, be sure to set the trial end date on the model:

```php
$user->trial_ends_at = Carbon::now()->addDays(14);

$user->save();
```



<a name="cancelling-a-subscription"></a>
## Cancelling A Subscription

Cancelling a subscription is a walk in the park:

```php
$user->subscription()->cancel();
```

When a subscription is cancelled, Conekta-Cashier will automatically set the `subscription_ends_at` column on your database. This column is used to know when the `subscribed` method should begin returning `false`. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the `subscribed` method will continue to return `true` until March 5th.

<a name="resuming-a-subscription"></a>
## Resuming A Subscription

If a user has cancelled their subscription and you wish to resume it, use the `resume` method:

```php
$user->subscription('monthly')->resume($creditCardToken);
```

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Their subscription will simply be re-activated, and they will be billed on the original billing cycle.

<a name="checking-subscription-status"></a>
## Checking Subscription Status

To verify that a user is subscribed to your application, use the `subscribed` command:

```php
if ($user->subscribed())
{
	//
}
```

The `subscribed` method makes a great candidate for a route filter:

```php
Route::filter('subscribed', function()
{
	if (Auth::user() && ! Auth::user()->subscribed())
	{
		return Redirect::to('billing');
	}
});
```

You may also determine if the user is still within their trial period (if applicable) using the `onTrial` method:

```php
if ($user->onTrial())
{
	//
}
```

To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the `cancelled` method:

```php
if ($user->cancelled())
{
	//
}
```

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was scheduled to end on March 10th, the user is on their "grace period" until March 10th. Note that the `subscribed` method still returns `true` during this time.

```php
if ($user->onGracePeriod())
{
	//
}
```

The `everSubscribed` method may be used to determine if the user has ever subscribed to a plan in your application:

```php
if ($user->everSubscribed())
{
	//
}
```

<a name="handling-failed-payments"></a>
## Handling Failed Payments

What if a customer's credit card expires? No worries - Conekta-Cashier includes a Webhook controller that can easily cancel the customer's subscription for you. Just point a route to the controller:

```php
Route::post('conekta/webhook', 'Dinkbit\ConektaCashier\WebhookController@handleWebhook');
```

That's it! Failed payments will be captured and handled by the controller. The controller will cancel the customer's subscription after three failed payment attempts. The `conekta/webhook` URI in this example is just for example. You will need to configure the URI in your Conekta settings.

If you have additional Conekta webhook events you would like to handle, simply extend the Webhook controller:

```php
class WebhookController extends Dinkbit\ConektaCashier\WebhookController {

	public function handleWebhook()
	{
		// Handle other events...

		// Fallback to failed payment check...
		return parent::handleWebhook();
	}

}
```

> **Note:** In addition to updating the subscription information in your database, the Webhook controller will also cancel the subscription via the Conekta API.
