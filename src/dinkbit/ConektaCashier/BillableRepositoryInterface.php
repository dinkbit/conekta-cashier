<?php namespace dinkbit\ConektaCashier;

interface BillableRepositoryInterface {

	/**
	 * Find a BillableInterface implementation by Conekta ID.
	 *
	 * @param  string  $conektaId
	 * @return \dinkbit\ConektaCashier\BillableInterface
	 */
	public function find($conektaId);

}
