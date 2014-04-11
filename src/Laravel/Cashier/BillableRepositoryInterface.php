<?php namespace Laravel\Cashier;

interface BillableRepositoryInterface {

	/**
	 * Find a BillableInterface implementation by Conekta ID.
	 *
	 * @param  string  $conektaId
	 * @return \Laravel\Cashier\BillableInterface
	 */
	public function find($conektaId);

}
