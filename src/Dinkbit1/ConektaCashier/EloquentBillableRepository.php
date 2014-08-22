<?php namespace dinkbit\ConektaCashier;

use Illuminate\Support\Facades\Config;

class EloquentBillableRepository implements BillableRepositoryInterface {

	/**
	 * Find a BillableInterface implementation by Conekta ID.
	 *
	 * @param  string  $conektaId
	 * @return \dinkbit\ConektaCashier\BillableInterface
	 */
	public function find($conektaId)
	{
		$model = $this->createCashierModel(Config::get('conekta.model'));

		return $model->where($model->getConektaIdName(), $conektaId)->first();
	}

	/**
	 * Create a new instance of the Auth model.
	 *
	 * @param  string  $model
	 * @return \dinkbit\ConektaCashier\BillableInterface
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
