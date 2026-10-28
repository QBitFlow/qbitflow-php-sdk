<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\EventType;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookRouter;

/**
 * Bounds on attacker-controlled webhook input (the php2shell advisories: bound the size and
 * nesting of what reaches the interpreter). Every entry point caps the body at 1 MiB, the
 * string ones included, and JSON nesting at {@see Webhook::MAX_JSON_DEPTH}.
 */
final class WebhookHardeningTest extends TestCase
{
	private const SECRET = 'whsec_hardening';

	private static function oversized(): string
	{
		return str_repeat(' ', Webhook::MAX_BODY_BYTES) . '{}';
	}

	private static function assertBodyError(callable $call): void
	{
		try {
			$call();
		} catch (ValidationException $e) {
			self::assertSame('body', $e->fieldErrors[0]->field);

			return;
		}
		self::fail('expected a ValidationException on body');
	}

	#[Test]
	public function parseEventRejectsBodyOver1Mib(): void
	{
		self::assertBodyError(static fn () => Webhook::parseEvent(self::oversized()));
	}

	#[Test]
	public function constructEventRejectsBodyOver1Mib(): void
	{
		$body = self::oversized();
		self::assertBodyError(static fn () => Webhook::constructEvent($body, Webhook::sign($body, self::SECRET), self::SECRET));
	}

	#[Test]
	public function routerHandleAnswers413Over1Mib(): void
	{
		$ran = false;
		$router = (new WebhookRouter(self::SECRET))->onAny(static function () use (&$ran): void {
			$ran = true;
		});
		$body = self::oversized();

		$result = $router->handle($body, Webhook::sign($body, self::SECRET));

		self::assertSame(413, $result->status);
		self::assertSame('{"error":"body too large"}', $result->responseBody());
		self::assertFalse($ran);
	}

	#[Test]
	public function parseEventRejectsNestingDeeperThanTheLimit(): void
	{
		$event = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/events/payment.completed.json'), true);
		$deep = 'x';
		for ($i = 0; $i < Webhook::MAX_JSON_DEPTH + 5; $i++) {
			$deep = ['k' => $deep];
		}
		$event['data']['metadata'] = $deep;

		self::assertBodyError(static fn () => Webhook::parseEvent((string) json_encode($event, 0, 1024)));
	}

	#[Test]
	public function aBodyAtTheLimitIsStillAccepted(): void
	{
		$body = (string) file_get_contents(__DIR__ . '/../Fixtures/events/webhook.test.json');
		$padded = $body . str_repeat(' ', Webhook::MAX_BODY_BYTES - strlen($body));

		self::assertSame(EventType::WEBHOOK_TEST, Webhook::parseEvent($padded)->type);
	}

	/** @return iterable<string, array{string}> */
	public static function fixtures(): iterable
	{
		foreach (glob(__DIR__ . '/../Fixtures/events/*.json') ?: [] as $file) {
			yield basename($file) => [$file];
		}
	}

	#[Test]
	#[DataProvider('fixtures')]
	public function fixtureEventsStillParse(string $file): void
	{
		$event = Webhook::parseEvent((string) file_get_contents($file));

		self::assertNotSame('', $event->id);
	}
}
