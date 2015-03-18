<?php

namespace Dinkbit\ConektaCashier;

use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->loadViewsFrom(__DIR__.'/../../views', 'conekta-cashier');

        $this->publishes([
            __DIR__.'/../../views' => base_path('resources/views/vendor/conekta-cashier'),
        ]);
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('Dinkbit\ConektaCashier\BillableRepositoryInterface', function () {
            return new EloquentBillableRepository;
        });

        $this->app->bindShared('command.conekta.cashier.table', function ($app) {
            return new CashierTableCommand;
        });

        $this->commands('command.conekta.cashier.table');
	}
}
