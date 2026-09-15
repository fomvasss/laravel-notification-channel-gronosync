<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use NotificationChannels\Gronosync\Exceptions\CouldNotSendNotification;

class GronosyncApi
{
    protected HttpClient $client;

    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
        array $config = [],
    ) {
        $this->client = new HttpClient(array_filter([
            'timeout' => (int) ($config['timeout'] ?? 15),
            'connect_timeout' => (int) ($config['connect_timeout'] ?? 10),
            'handler' => $config['handler'] ?? null,
        ]));
    }

    /**
     * @throws CouldNotSendNotification
     */
    public function sendMessage(GronosyncMessage $message): array
    {
        return $this->post('/api/extern/message', $message->toArray());
    }

    /**
     * @throws CouldNotSendNotification
     */
    public function upsertContact(array $data): array
    {
        $allowed = ['external_id', 'name', 'lastname', 'email', 'phone', 'birthday', 'gender', 'locale', 'timezone', 'comment', 'extra'];

        return $this->post('/api/extern/contacts', array_intersect_key($data, array_flip($allowed)));
    }

    /**
     * @throws CouldNotSendNotification
     */
    protected function post(string $path, array $body): array
    {
        try {
            $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . $path, [
                'headers' => [
                    'Authorization' => "Bearer {$this->token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $result = json_decode((string) $response->getBody(), true);

            if (!is_array($result)) {
                throw CouldNotSendNotification::serviceRespondedWithAnError('Invalid JSON response.');
            }

            return $result;
        } catch (RequestException $e) {
            // Guzzle truncates the body in its own message, so the API error (`message`, `code`) is read from the response
            $status = $e->getResponse()?->getStatusCode();
            $body = $e->getResponse() ? json_decode((string) $e->getResponse()->getBody(), true) : null;
            $body = is_array($body) ? $body : null;

            throw CouldNotSendNotification::serviceRespondedWithAnError(
                is_string($body['message'] ?? null) ? $body['message'] : $e->getMessage(),
                $status,
                $body,
            );
        } catch (GuzzleException $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($e->getMessage());
        }
    }
}
