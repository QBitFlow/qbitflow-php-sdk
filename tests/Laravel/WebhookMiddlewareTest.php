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
use QBitFlow\Webhooks\WebhookVerifier;
use Symfony\Component\HttpFoundation\Response;

final class WebhookMiddlewareTest extends TestCase
{
	private const SECRET = 'whsec_test_0123456789abcdef';

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

	/**
	 * @param array<string,string> $headers Extra headers, merged over the signed defaults.
	 */
	private function signedRequest(string $body = '{"uuid":"s1"}', array $headers = []): Request
	{
		return $this->request($body, $headers + [
			WebhookRequests::HEADER_SIGNATURE => 'a-signature',
			WebhookRequests::HEADER_TIMESTAMP => '1767225600',
			WebhookRequests::HEADER_WEBHOOK_ID => 'evt_123',
		]);
	}

	/**
	 * A request carrying a signature that is genuinely valid under SECRET.
	 *
	 * @param array<string,string> $headers
	 */
	private function locallySignedRequest(string $body = '{"uuid":"s1"}', array $headers = []): Request
	{
		$timestamp = (string) time();

		return $this->request($body, $headers + [
			WebhookRequests::HEADER_SIGNATURE => WebhookVerifier::computeSignature(self::SECRET, $timestamp, $body),
			WebhookRequests::HEADER_TIMESTAMP => $timestamp,
			WebhookRequests::HEADER_WEBHOOK_ID => 'evt_123',
		]);
	}

	private function middleware(?string $secret = null): VerifyQBitFlowWebhook
	{
		return new VerifyQBitFlowWebhook($this->client(), $secret);
	}

	private function passThrough(bool &$reached): \Closure
	{
		return function () use (&$reached): Response {
			$reached = true;

			return new JsonResponse(['received' => true]);
		};
	}

	// -------------------------------------------------------------------------
	// API verification (no secret configured)
	// -------------------------------------------------------------------------

	#[Test]
	public function it_passes_a_verified_delivery_through_to_the_handler(): void
	{
		$this->http->push(['message' => 'verified']);
		$reached = false;

		$response = $this->middleware()->handle($this->signedRequest(), $this->passThrough($reached));

		$this->assertTrue($reached);
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('/v1/webhooks/verify', $this->http->lastPath());
	}

	#[Test]
	public function it_forwards_the_body_for_verification(): void
	{
		$raw = '{"z":1,"a":2}';
		$this->http->push(['message' => 'verified']);

		$this->middleware()->handle($this->signedRequest($raw), fn (): Response => new JsonResponse([]));

		$this->assertSame(['z' => 1, 'a' => 2], $this->http->lastBody()['payload']);
		$this->assertSame('a-signature', $this->http->lastBody()['receivedSignature']);
		$this->assertSame('1767225600', $this->http->lastBody()['receivedTimestamp']);
	}

	#[Test]
	public function it_rejects_a_delivery_the_api_does_not_recognise(): void
	{
		$this->http->push(['error' => 'signature mismatch'], 400);
		$reached = false;

		$response = $this->middleware()->handle($this->signedRequest(), $this->passThrough($reached));

		$this->assertFalse($reached, 'The handler must not run for an unverified delivery.');
		$this->assertSame(400, $response->getStatusCode());
		$this->assertStringContainsString('Invalid webhook signature', (string) $response->getContent());
	}

	#[Test]
	public function it_verifies_the_dashboard_test_probe_then_answers_without_processing(): void
	{
		// The probe is verified like any other delivery and only then short-circuited.
		// That ordering is the point of the dashboard button: it exercises the whole
		// verification path, so a broken secret shows up as a failed test rather than a
		// false success.
		$this->http->push(['message' => 'verified']);
		$reached = false;

		$response = $this->middleware()->handle(
			$this->signedRequest('{"fake":true}', [
				WebhookRequests::HEADER_WEBHOOK_ID => WebhookRequests::TEST_WEBHOOK_ID,
			]),
			$this->passThrough($reached),
		);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertFalse($reached, 'A probe carries fake data and must skip normal processing.');
		$this->assertSame(1, $this->http->requestCount(), 'A probe is verified like any delivery.');
	}

	#[Test]
	public function it_rejects_a_test_probe_whose_signature_does_not_verify(): void
	{
		$this->http->push(['error' => 'signature mismatch'], 400);

		$response = $this->middleware()->handle(
			$this->signedRequest('{"fake":true}', [
				WebhookRequests::HEADER_WEBHOOK_ID => WebhookRequests::TEST_WEBHOOK_ID,
			]),
			fn (): Response => new JsonResponse([]),
		);

		$this->assertSame(400, $response->getStatusCode());
	}

	#[Test]
	public function it_rejects_a_delivery_with_no_signature_headers(): void
	{
		$response = $this->middleware()->handle($this->request(), fn (): Response => new JsonResponse([]));

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

		$this->middleware()->handle($this->signedRequest(), fn (): Response => new JsonResponse([]));
	}

	#[Test]
	public function a_body_that_is_not_json_is_rejected_on_the_api_path_too(): void
	{
		// Rejected locally before any call: a 400, not a 500 that QBitFlow would retry.
		foreach (['not json', '"a string"'] as $body) {
			$reached = false;

			$response = $this->middleware()->handle($this->signedRequest($body), $this->passThrough($reached));

			$this->assertSame(400, $response->getStatusCode());
			$this->assertFalse($reached);
		}

		$this->assertSame(0, $this->http->requestCount());
	}

	#[Test]
	public function the_api_path_forwards_the_raw_body_so_empty_objects_survive(): void
	{
		$body = '{"uuid":"s1","meta":{}}';
		$this->http->push(['message' => 'webhook verified!']);
		$reached = false;

		$this->middleware()->handle($this->signedRequest($body), $this->passThrough($reached));

		$this->assertTrue($reached);
		$this->assertStringContainsString('"payload":' . $body, (string) $this->http->lastRequest()->getBody());
	}

	#[Test]
	public function without_a_client_the_api_path_explains_what_is_missing(): void
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('QBITFLOW_API_KEY');

		(new VerifyQBitFlowWebhook(null, null))->handle($this->signedRequest(), fn (): Response => new JsonResponse([]));
	}

	// -------------------------------------------------------------------------
	// Local verification (webhook secret configured)
	// -------------------------------------------------------------------------

	#[Test]
	public function with_a_secret_a_large_decimal_amount_verifies(): void
	{
		// A 1 ETH billing carries "1000000000000000000" (19 digits) as a string; it must
		// stay a string in the canonical form or every such webhook is rejected.
		$body = '{"type":"billing","data":{"amountMinUnits":"1000000000000000000","metadata":{"txAmounts":{"minUnits":{"merchant":"985000000000000000"}}}}}';
		$reached = false;

		$response = (new VerifyQBitFlowWebhook(null, self::SECRET))
			->handle($this->locallySignedRequest($body), $this->passThrough($reached));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertTrue($reached);
	}

	#[Test]
	public function with_a_secret_it_verifies_locally_and_never_calls_the_api(): void
	{
		$reached = false;

		$response = $this->middleware(self::SECRET)->handle($this->locallySignedRequest(), $this->passThrough($reached));

		$this->assertTrue($reached);
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(0, $this->http->requestCount(), 'Local verification makes no API call.');
	}

	#[Test]
	public function with_a_secret_a_reordered_body_still_verifies(): void
	{
		// The signature covers a canonical rendering, so a proxy that reorders keys does
		// not break verification.
		$timestamp = (string) time();
		$request = $this->request('{"b":2,"a":1}', [
			WebhookRequests::HEADER_SIGNATURE => WebhookVerifier::computeSignature(self::SECRET, $timestamp, '{"a":1,"b":2}'),
			WebhookRequests::HEADER_TIMESTAMP => $timestamp,
		]);
		$reached = false;

		$this->middleware(self::SECRET)->handle($request, $this->passThrough($reached));

		$this->assertTrue($reached);
	}

	#[Test]
	public function with_a_secret_a_tampered_delivery_is_rejected(): void
	{
		$timestamp = (string) time();
		$request = $this->request('{"uuid":"pay@EVIL"}', [
			WebhookRequests::HEADER_SIGNATURE => WebhookVerifier::computeSignature(self::SECRET, $timestamp, '{"uuid":"pay@good"}'),
			WebhookRequests::HEADER_TIMESTAMP => $timestamp,
		]);
		$reached = false;

		$response = $this->middleware(self::SECRET)->handle($request, $this->passThrough($reached));

		$this->assertFalse($reached);
		$this->assertSame(400, $response->getStatusCode());
		$this->assertSame(0, $this->http->requestCount());
	}

	#[Test]
	public function with_a_secret_a_replayed_delivery_is_rejected(): void
	{
		$old = (string) (time() - 600);
		$request = $this->request('{"uuid":"s1"}', [
			WebhookRequests::HEADER_SIGNATURE => WebhookVerifier::computeSignature(self::SECRET, $old, '{"uuid":"s1"}'),
			WebhookRequests::HEADER_TIMESTAMP => $old,
		]);

		$response = $this->middleware(self::SECRET)->handle($request, fn (): Response => new JsonResponse([]));

		$this->assertSame(400, $response->getStatusCode());
	}

	#[Test]
	public function with_a_secret_the_wrong_secret_is_rejected(): void
	{
		$response = $this->middleware('whsec_wrong')->handle(
			$this->locallySignedRequest(),
			fn (): Response => new JsonResponse([]),
		);

		$this->assertSame(400, $response->getStatusCode());
	}

	#[Test]
	public function with_a_secret_the_test_probe_is_verified_locally_then_acknowledged(): void
	{
		$reached = false;

		$response = $this->middleware(self::SECRET)->handle(
			$this->locallySignedRequest('{"fake":true}', [
				WebhookRequests::HEADER_WEBHOOK_ID => WebhookRequests::TEST_WEBHOOK_ID,
			]),
			$this->passThrough($reached),
		);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertFalse($reached);
		$this->assertSame(0, $this->http->requestCount());
	}
}
