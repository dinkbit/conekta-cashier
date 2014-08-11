<?php namespace dinkbit\ConektaCashier;

use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('dinkbit/conektacashier');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('dinkbit\ConektaCashier\BillableRepositoryInterface', function()
		{
			return new EloquentBillableRepository;
		});

		$this->app->bindShared('command.cashier.table', function($app)
		{
			return new CashierTableCommand;
		});

		$this->commands('command.cashier.table');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
