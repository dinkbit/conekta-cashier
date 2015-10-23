<?php

use Illuminate\Support\Facades\Request;

class WebhookControllerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Illuminate\Support\Facades\Facade::clearResolvedInstances();
    }

    public function testProperMethodsAreCalledBasedOnStripeEvent()
    {
        $_SERVER['__received'] = false;
        Request::shouldReceive('getContent')->andReturn(json_encode(['type' => 'charge.succeeded', 'id' => 'event-id']));
        $controller = new WebhookControllerTestStub();
        $controller->handleWebhook();

        $this->assertTrue($_SERVER['__received']);
    }

    public function testNormalResponseIsReturnedIfMethodIsMissing()
    {
        Request::shouldReceive('getContent')->andReturn(json_encode(['type' => 'foo.bar', 'id' => 'event-id']));
        $controller = new WebhookControllerTestStub();
        $response = $controller->handleWebhook();
        $this->assertEquals(200, $response->getStatusCode());
    }
}

class WebhookControllerTestStub extends Dinkbit\ConektaCashier\WebhookController
{
    public function handleChargeSucceeded()
    {
        $_SERVER['__received'] = true;
    }

    /**
     * Verify with Conekta that the event is genuine.
     *
     * @param string $id
     *
     * @return bool
     */
    protected function eventExistsOnConekta($id)
    {
        return true;
    }
}
