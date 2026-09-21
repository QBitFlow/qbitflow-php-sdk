<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\Requests\WebhookRequests;
use QBitFlow\Tests\Support\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class WebhookMiddlewareTest extends TestCase
{
	/**
	 * @param array<string,string> $headers
	 */
	private function request(string $body = '{"uuid":"s1"}', array $headers = []): Request
	{
		$server = [];

		foreach ($headers as $name => $value) {
			$server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
		}

		return Request::create('/webhooks/qbitflow/transaction', 'POST', [], [], [], $server, $body);
	}

	private function signedRequest(string $body = '{"uuid":"s1"}'): Request
	{
		return $this->request($body, [
			WebhookRequests::HEADER_SIGNATURE => 'a-signature',
			WebhookRequests::HEADER_TIMESTAMP => '1767225600',
			WebhookRequests::HEADER_WEBHOOK_ID => 'evt_123',
		]);
	}

	private function middleware(): VerifyQBitFlowWebhook
	{
		return new VerifyQBitFlowWebhook($this->client());
	}

	#[Test]
	public function it_passes_a_verified_delivery_through_to_the_handler(): void
	{
		$this->http->push(['message' => 'verified']);
		$reached = false;

		$response = $this->middleware()->handle(
			$this->signedRequest(),
			function () use (&$reached): Response {
				$reached = true;

				return new JsonResponse(['received' => true]);
			},
		);

		$this->assertTrue($reached);
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('/v1/webhooks/verify', $this->http->lastPath());
	}

	#[Test]
	public function it_forwards_the_raw_body_for_verification(): void
	{
		// Re-encoding would reorder keys and break the signature, so the body must reach
		// the API exactly as it arrived.
		$raw = '{"z":1,"a":2}';
		$this->http->push(['message' => 'verified']);

		$this->middleware()->handle(
			$this->signedRequest($raw),
			fn (): Response => new JsonResponse([]),
		);

		$this->assertSame(['z' => 1, 'a' => 2], $this->http->lastBody()['payload']);
		$this->assertSame('a-signature', $this->http->lastBody()['receivedSignature']);
		$this->assertSame('1767225600', $this->http->lastBody()['receivedTimestamp']);
	}

	#[Test]
	public function it_rejects_a_delivery_the_api_does_not_recognise(): void
	{
		$this->http->push(['error' => 'Invalid signature'], 400);
		$reached = false;

		$response = $this->middleware()->handle(
			$this->signedRequest(),
			function () use (&$reached): Response {
				$reached = true;

				return new JsonResponse([]);
			},
		);

		$this->assertFalse($reached, 'The handler must not run for an unverified delivery.');
		$this->assertSame(400, $response->getStatusCode());
		$this->assertStringContainsString('Invalid webhook signature', (string) $response->getContent());
	}

	#[Test]
	public function it_answers_the_dashboard_test_probe_without_verifying_or_processing(): void
	{
		$reached = false;

		$response = $this->middleware()->handle(
			$this->request('{"fake":true}', [
				WebhookRequests::HEADER_WEBHOOK_ID => WebhookRequests::TEST_WEBHOOK_ID,
			]),
			function () use (&$reached): Response {
				$reached = true;

				return new JsonResponse([]);
			},
		);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertFalse($reached, 'A probe carries fake data and must skip normal processing.');
		$this->assertSame(0, $this->http->requestCount(), 'A probe needs no verification call.');
	}

	#[Test]
	public function it_rejects_a_delivery_with_no_signature_headers(): void
	{
		$response = $this->middleware()->handle(
			$this->request(),
			fn (): Response => new JsonResponse([]),
		);

		$this->assertSame(400, $response->getStatusCode());
		$this->assertStringContainsString('Missing webhook signature headers', (string) $response->getContent());
		$this->assertSame(0, $this->http->requestCount());
	}

	#[Test]
	public function an_outage_surfaces_rather_than_masquerading_as_a_bad_signature(): void
	{
		// Letting this bubble produces a 5xx, which makes QBitFlow retry the delivery —
		// the right outcome. Swallowing it would silently drop a real event.
		$this->http->pushFailure();

		$this->expectException(NetworkException::class);

		$this->middleware()->handle(
			$this->signedRequest(),
			fn (): Response => new JsonResponse([]),
		);
	}
}
