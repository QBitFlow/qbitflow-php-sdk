<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A webhook endpoint of a space.
 */
readonly class WebhookEndpoint extends Model
{
	/**
	 * The endpoint's id.
	 */
	public string $uuid;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * Where the events are posted.
	 */
	public string $url;

	/**
	 * The event types it receives ({@see \QBitFlow\Enums\EventType}); empty: every type.
	 *
	 * @var list<string>
	 */
	public array $events;

	/**
	 * True for an organization endpoint that also receives its members' events.
	 */
	public bool $includeMembers;

	/**
	 * `v2` (the event envelope), or `v1` for an endpoint migrated from v1.
	 */
	public string $payloadVersion;

	/**
	 * A note for the dashboard.
	 */
	public string $description;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * When its secret last changed.
	 */
	public ?DateTimeImmutable $rotatedAt;

	/**
	 * When it was disabled; null while enabled.
	 */
	public ?DateTimeImmutable $disabledAt;

	/**
	 * Why: `failing`, `owner` or `closed` ({@see \QBitFlow\Enums\EndpointDisabledReason}).
	 */
	public ?string $disabledReason;

	/**
	 * When its deliveries started failing.
	 */
	public ?DateTimeImmutable $failingSince;

	/**
	 * Its last successful delivery.
	 */
	public ?DateTimeImmutable $lastDeliveredAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->test = Cast::bool($data, 'test');
		$this->url = Cast::string($data, 'url');
		$this->events = Cast::stringList($data, 'events');
		$this->includeMembers = Cast::bool($data, 'includeMembers');
		$this->payloadVersion = Cast::string($data, 'payloadVersion');
		$this->description = Cast::string($data, 'description');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->rotatedAt = Cast::nullableDate($data, 'rotatedAt');
		$this->disabledAt = Cast::nullableDate($data, 'disabledAt');
		$this->disabledReason = Cast::nullableString($data, 'disabledReason');
		$this->failingSince = Cast::nullableDate($data, 'failingSince');
		$this->lastDeliveredAt = Cast::nullableDate($data, 'lastDeliveredAt');
	}
}
