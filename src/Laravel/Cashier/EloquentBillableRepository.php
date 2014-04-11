<?php namespace Laravel\Cashier;

use Illuminate\Support\Facades\Config;

class EloquentBillableRepository implements BillableRepositoryInterface {

	/**
	 * Find a BillableInterface implementation by Conekta ID.
	 *
	 * @param  string  $conektaId
	 * @return \Laravel\Cashier\BillableInterface
	 */
	public function find($conektaId)
	{
		$model = $this->createCashierModel(Config::get('services.conekta.model'));

		return $model->where($model->getConektaIdName(), $conektaId)->first();
	}

	/**
	 * Create a new instance of the Auth model.
	 *
	 * @param  string  $model
	 * @return \Laravel\Cashier\BillableInterface
	 */
	protected function createCashierModel($class)
	{
		$model = new $class;

		if ( ! $model instanceof BillableInterface)
		{
			throw new \InvalidArgumentException("Model does not implement BillableInterface.");
		}

		return $model;
	}

}
