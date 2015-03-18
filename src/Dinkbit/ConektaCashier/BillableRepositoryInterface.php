<?php

namespace Dinkbit\ConektaCashier;

interface BillableRepositoryInterface
{
    /**
     * Find a BillableInterface implementation by Conekta ID.
     *
     * @param string $conektaId
     *
     * @return \Dinkbit\ConektaCashier\BillableInterface
     */
    public function find($conektaId);
}
