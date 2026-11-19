<?php

namespace App\Services\Operations;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ResendRequestClient implements PsrClient
{
    public function __construct(
        private readonly PsrClient $client,
        private readonly EmailDeliveryService $delivery,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $record = $this->delivery->current;
        if ($record !== null && $request->getMethod() === 'POST' && $request->getUri()->getPath() === '/emails') {
            if ($record->request_payload === null) {
                $record->update(['request_payload' => (string) $request->getBody()]);
            }

            $request = $request
                ->withHeader('Idempotency-Key', 'prism/'.$record->id)
                ->withBody(Utils::streamFor($record->request_payload));
        }

        $response = $this->client->sendRequest($request);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $this->delivery->failureCategory = match (true) {
                in_array($status, [401, 403], true) => 'provider_authentication',
                $status === 429 => 'provider_rate_limit',
                $status === 409 => 'provider_idempotency_conflict',
                $status >= 500 => 'provider_unavailable',
                $status === 408 => 'provider_timeout',
                default => 'provider_rejected_request',
            };
            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body) && isset($body['error']) && is_array($body['error'])) {
                $body = $body['error'];
            }
            $name = is_array($body) ? ($body['name'] ?? '') : '';
            $this->delivery->permanentFailure = ! in_array($status, [408, 429], true)
                && $status < 500
                && ! ($status === 409 && $name === 'concurrent_idempotent_requests');
            $retryAfter = $response->getHeaderLine('Retry-After');
            if (ctype_digit($retryAfter)) {
                $this->delivery->retryDelay = max(60, (int) $retryAfter);
            } elseif ($retryAfter !== '' && ($retryAt = strtotime($retryAfter)) !== false) {
                $this->delivery->retryDelay = max(60, $retryAt - time());
            }
            $response->getBody()->rewind();
        }

        return $response;
    }
}
