<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Events\PaymentCompletedEvent;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use Symfony\Component\HttpFoundation\Response;

final class WebhookMiddlewareTest extends TestCase
{
	private const SECRET = 'whsec_test_secret';

	public static function body(string $version = 'v2'): string
	{
		return '{"id":"evt_1","type":"payment.completed","version":"' . $version . '","createdAt":"2026-10-01T12:00:00Z","test":true,"data":{"uuid":"pay@1","reference":"order-1"}}';
	}

	public static function signedRequest(string $body, string $secret = self::SECRET, ?int $t = null): Request
	{
		$t ??= time();
		$request = Request::create('/webhooks/qbitflow', 'POST', [], [], [], [], $body);
		$request->headers->set('QBitFlow-Signature', 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret));

		return $request;
	}

	private static function next(): \Closure
	{
		return static fn (Request $r): Response => new Response('passed', 200);
	}

	#[Test]
	public function a_signed_delivery_passes_with_its_event(): void
	{
		$request = self::signedRequest(self::body());
		$response = (new VerifyQBitFlowWebhook(self::SECRET))->handle($request, self::next());

		$this->assertSame('passed', $response->getContent());
		$event = $request->attributes->get(VerifyQBitFlowWebhook::EVENT_ATTRIBUTE);
		$this->assertInstanceOf(PaymentCompletedEvent::class, $event);
		$this->assertSame('order-1', $event->data->reference);
	}

	#[Test]
	public function a_bad_signature_is_refused(): void
	{
		$cases = [
			'other secret' => [self::signedRequest(self::body(), 'whsec_other'), 'noMatchingSignature'],
			'stale' => [self::signedRequest(self::body(), self::SECRET, time() - 3600), 'timestampOutsideTolerance'],
			'missing header' => [Request::create('/', 'POST', [], [], [], [], self::body()), 'missingHeader'],
		];
		foreach ($cases as $name => [$request, $reason]) {
			$response = (new VerifyQBitFlowWebhook(self::SECRET))->handle($request, self::next());
			$this->assertSame(400, $response->getStatusCode(), $name);
			$this->assertSame($reason, json_decode((string) $response->getContent(), true)['reason'], $name);
		}

		// A tampered body.
		$request = self::signedRequest(self::body());
		$tampered = Request::create('/', 'POST', [], [], [], [], str_replace('order-1', 'order-2', self::body()));
		$tampered->headers->set('QBitFlow-Signature', (string) $request->headers->get('QBitFlow-Signature'));
		$this->assertSame(400, (new VerifyQBitFlowWebhook(self::SECRET))->handle($tampered, self::next())->getStatusCode());
	}

	#[Test]
	public function a_signed_v1_body_is_refused(): void
	{
		$response = (new VerifyQBitFlowWebhook(self::SECRET))->handle(self::signedRequest(self::body('v1')), self::next());
		$this->assertSame(400, $response->getStatusCode());
		$this->assertStringContainsString('v2', (string) $response->getContent());
	}

	#[Test]
	public function the_tolerance_is_configurable(): void
	{
		$request = self::signedRequest(self::body(), self::SECRET, time() - 600);
		$this->assertSame(200, (new VerifyQBitFlowWebhook(self::SECRET, 3600))->handle($request, self::next())->getStatusCode());
	}

	#[Test]
	public function a_missing_secret_is_a_configuration_error(): void
	{
		$this->expectException(QBitFlowException::class);
		$this->expectExceptionMessage('QBITFLOW_WEBHOOK_SECRET');
		(new VerifyQBitFlowWebhook(null))->handle(self::signedRequest(self::body()), self::next());
	}
}
