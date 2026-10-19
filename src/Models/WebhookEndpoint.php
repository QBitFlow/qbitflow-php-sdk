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
	 * The event types it receives ({@see \QBitFlow\Enums\EventType}; stored sorted, without
	 * duplicates); `[]`: every type, including types added later.
	 *
	 * @var list<string>
	 */
	public array $events;

	/**
	 * True for an organization endpoint that also receives its members' events.
	 */
	public bool $includeMembers;

	/**
	 * The payload it receives: `v2` (the event envelope), the only version.
	 */
	public string $payloadVersion;

	/**
	 * A note for the dashboard; null (absent) when none.
	 */
	public ?string $description;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * When its secret last changed.
	 */
	public ?DateTimeImmutable $rotatedAt;

	/**
	 * When it was disabled (it receives nothing): after 3 days without a successful delivery
	 * (live mode only), or by its owner; null while enabled. `update()` with `enabled` true
	 * enables it again.
	 */
	public ?DateTimeImmutable $disabledAt;

	/**
	 * Why: `failing` (live mode only: a test-mode endpoint is never disabled for failing),
	 * `owner` or `closed` ({@see \QBitFlow\Enums\EndpointDisabledReason}).
	 */
	public ?string $disabledReason;

	/**
	 * When its deliveries started failing (live mode: failing for 3 days, it is disabled).
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
		$this->description = Cast::nullableString($data, 'description');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->rotatedAt = Cast::nullableDate($data, 'rotatedAt');
		$this->disabledAt = Cast::nullableDate($data, 'disabledAt');
		$this->disabledReason = Cast::nullableString($data, 'disabledReason');
		$this->failingSince = Cast::nullableDate($data, 'failingSince');
		$this->lastDeliveredAt = Cast::nullableDate($data, 'lastDeliveredAt');
	}
}
