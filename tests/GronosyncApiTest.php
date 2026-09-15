<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use NotificationChannels\Gronosync\Exceptions\CouldNotSendNotification;
use NotificationChannels\Gronosync\GronosyncApi;
use NotificationChannels\Gronosync\GronosyncMessage;

class GronosyncApiTest extends TestCase
{
    private array $history = [];

    private function api(array $responses): GronosyncApi
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new GronosyncApi('https://api.gronosync.test/', 'token-1', ['handler' => $stack]);
    }

    public function test_send_message_posts_body_with_bearer_token(): void
    {
        $api = $this->api([new Response(200, [], json_encode(['message_id' => 'm-1', 'sid' => 's-1']))]);

        $result = $api->sendMessage(GronosyncMessage::make()->contactId('c-1')->text('Hello'));

        $this->assertSame('m-1', $result['message_id']);

        /** @var Request $request */
        $request = $this->history[0]['request'];

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.gronosync.test/api/extern/message', (string) $request->getUri());
        $this->assertSame('Bearer token-1', $request->getHeaderLine('Authorization'));
        $this->assertSame(['contact_id' => 'c-1', 'message' => ['text' => 'Hello']], json_decode((string) $request->getBody(), true));
    }

    public function test_upsert_contact_sends_only_allowed_fields(): void
    {
        $api = $this->api([new Response(200, [], json_encode(['id' => 'c-1', 'created' => true, 'sid' => 's-1']))]);

        $api->upsertContact(['external_id' => '42', 'timezone' => 'Europe/Kyiv', 'password' => 'nope']);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);

        $this->assertSame(['external_id' => '42', 'timezone' => 'Europe/Kyiv'], $body);
    }

    public function test_error_response_exposes_status_message_and_code(): void
    {
        $message = str_repeat('Чат заблоковано — надсилати повідомлення контакту не можна. ', 3);

        $api = $this->api([new Response(422, [], json_encode(['message' => $message, 'code' => 'chat_blocked']))]);

        try {
            $api->sendMessage(GronosyncMessage::make()->contactId('c-1')->text('Hello'));
            $this->fail('Exception was not thrown.');
        } catch (CouldNotSendNotification $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('chat_blocked', $e->getErrorCode());
            $this->assertSame($message, $e->getResponse()['message']);
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_error_without_json_body_has_no_code(): void
    {
        $api = $this->api([new Response(500, [], 'Server Error')]);

        try {
            $api->sendMessage(GronosyncMessage::make()->contactId('c-1')->text('Hello'));
            $this->fail('Exception was not thrown.');
        } catch (CouldNotSendNotification $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertNull($e->getErrorCode());
            $this->assertNull($e->getResponse());
        }
    }

    public function test_network_error_has_no_status(): void
    {
        $api = $this->api([new ConnectException('Connection refused', new Request('POST', 'https://api.gronosync.test'))]);

        try {
            $api->sendMessage(GronosyncMessage::make()->contactId('c-1')->text('Hello'));
            $this->fail('Exception was not thrown.');
        } catch (CouldNotSendNotification $e) {
            $this->assertNull($e->getStatusCode());
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }
    }
}
