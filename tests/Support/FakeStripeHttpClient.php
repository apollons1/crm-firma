<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Client HTTP fals pentru Stripe SDK — înlocuiește CurlClient în teste prin
 * Stripe\ApiRequestor::setHttpClient(), ca să putem testa StripeService
 * fără să lovim rețeaua/API-ul real Stripe.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{status: int, body: array<string, mixed>}> */
    private array $queuedResponses = [];

    /** @var array<int, array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /**
     * @param  array<string, mixed>  $body
     */
    public function queueResponse(array $body, int $status = 200): void
    {
        $this->queuedResponses[] = ['status' => $status, 'body' => $body];
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        $response = array_shift($this->queuedResponses) ?? ['status' => 200, 'body' => []];

        return [json_encode($response['body']), $response['status'], []];
    }
}
