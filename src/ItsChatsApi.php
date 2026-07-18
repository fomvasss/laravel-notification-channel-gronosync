<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use NotificationChannels\ItsChats\Exceptions\CouldNotSendNotification;

class ItsChatsApi
{
    protected HttpClient $client;

    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
        array $config = [],
    ) {
        $this->client = new HttpClient([
            'timeout' => (int) ($config['timeout'] ?? 15),
            'connect_timeout' => (int) ($config['connect_timeout'] ?? 10),
        ]);
    }

    /**
     * @throws CouldNotSendNotification
     */
    public function sendMessage(ItsChatsMessage $message): array
    {
        return $this->post('/api/extern/message', $message->toArray());
    }

    /**
     * @throws CouldNotSendNotification
     */
    public function upsertContact(array $data): array
    {
        $allowed = ['external_id', 'name', 'lastname', 'email', 'phone', 'birthday', 'gender', 'locale', 'comment', 'extra'];

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
                    'X-Widget-Token' => $this->token,
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
        } catch (GuzzleException $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($e->getMessage());
        }
    }
}
