<?php namespace Laravel\Cashier;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller {

	/**
	 * Handle a Conekta webhook call.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handleWebhook()
	{
		$payload = $this->getJsonPayload();

		switch ($payload['type'])
		{
			case 'invoice.payment_failed':
				return $this->handleFailedPayment($payload);
		}
	}

	/**
	 * Handle a failed payment from a Conekta subscription.
	 *
	 * @param  array  $payload
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	protected function handleFailedPayment(array $payload)
	{
		if ($this->tooManyFailedPayments($payload))
		{
			$billable = $this->getBillable($payload['data']['object']['customer']);

			if ($billable) $billable->subscription()->cancel();
		}

		return new Response('Webhook Handled', 200);
	}

	/**
	 * Determine if the invoice has too many failed attempts.
	 *
	 * @param  array  $payload
	 * @return bool
	 */
	protected function tooManyFailedPayments(array $payload)
	{
		return $payload['data']['object']['attempt_count'] > 3;
	}

	/**
	 * Get the billable entity instance by Conekta ID.
	 *
	 * @param  string  $conektaId
	 * @return \Laravel\Cashier\BillableInterface
	 */
	protected function getBillable($conektaId)
	{
		return App::make('Laravel\Cashier\BillableRepositoryInterface')->find($conektaId);
	}

	/**
	 * Get the JSON payload for the request.
	 *
	 * @return array
	 */
	protected function getJsonPayload()
	{
		return (array) json_decode(Request::getContent(), true);
	}

}
