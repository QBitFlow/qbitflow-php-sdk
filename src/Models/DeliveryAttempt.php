<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One attempt to deliver an event to an endpoint.
 */
final readonly class DeliveryAttempt extends Model
{
	/**
	 * The attempt's id.
	 */
	public string $uuid;

	/**
	 * The event (`evt_…`).
	 */
	public string $eventId;

	/**
	 * The event's type.
	 */
	public string $eventType;

	/**
	 * The endpoint.
	 */
	public string $endpointUuid;

	/**
	 * 1 for the first, then one more per retry or resend.
	 */
	public int $attempt;

	/**
	 * True when the endpoint answered with a 2xx.
	 */
	public bool $delivered;

	/**
	 * True when it was not posted (a member's endpoint with members.webhooks off).
	 */
	public ?bool $skipped;

	/**
	 * The endpoint's answer; null when it didn't answer.
	 */
	public ?int $statusCode;

	/**
	 * Why it wasn't delivered.
	 */
	public ?string $error;

	/**
	 * How long it took, in milliseconds.
	 */
	public int $durationMs;

	/**
	 * When it was made.
	 */
	public DateTimeImmutable $attemptedAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->eventId = Cast::string($data, 'eventId');
		$this->eventType = Cast::string($data, 'eventType');
		$this->endpointUuid = Cast::string($data, 'endpointUuid');
		$this->attempt = Cast::int($data, 'attempt');
		$this->delivered = Cast::bool($data, 'delivered');
		$this->skipped = Cast::nullableBool($data, 'skipped');
		$this->statusCode = Cast::nullableInt($data, 'statusCode');
		$this->error = Cast::nullableString($data, 'error');
		$this->durationMs = Cast::int($data, 'durationMs');
		$this->attemptedAt = Cast::date($data, 'attemptedAt');
	}
}
