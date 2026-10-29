<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * An event's deliveries to one endpoint, and whether they go on.
 *
 * While `pending`, QBitFlow still attempts the event on its own: `nextAttemptAt` is set when it is
 * retried after a failed attempt, `waitingFor` when it is queued behind an earlier event of the
 * same publication, and neither when its delivery is about to start (the last two have no
 * `attempts` yet). Once `pending` is false nothing more happens on its own, and `delivered` tells
 * how it ended (false: abandoned after its retries, or the endpoint was disabled or deleted).
 */
final readonly class EndpointDelivery extends Model
{
	/**
	 * The endpoint.
	 */
	public string $endpointUuid;

	/**
	 * The endpoint's URL now.
	 */
	public string $url;

	/**
	 * True when one attempt was answered with a 2xx.
	 */
	public bool $delivered;

	/**
	 * Its attempts, oldest first. Empty while the event waits for its first attempt (`pending`):
	 * check it before reading the last one.
	 *
	 * @var list<DeliveryAttempt>
	 */
	public array $attempts;

	/**
	 * True while QBitFlow still attempts the event on its own. False once delivered, abandoned
	 * after its retries (3 days in live mode, 3 hours in test mode), or when the endpoint was
	 * disabled or deleted. When QBitFlow can't read a delivery's progress, `pending` comes from the
	 * attempts: true while the retry schedule still attempts the event again (a delivery being
	 * retried never reads as abandoned), without `nextAttemptAt`; a delivery queued with no attempt
	 * yet isn't listed until its progress can be read: read the event again a moment later.
	 */
	public bool $pending;

	/**
	 * While `pending` after a failed attempt: when the next attempt comes.
	 */
	public ?DateTimeImmutable $nextAttemptAt;

	/**
	 * While `pending` behind an earlier event of the same publication: that event's id (`evt_…`).
	 * An endpoint gets a publication's events in order, each once the previous one is delivered or
	 * abandoned.
	 */
	public ?string $waitingFor;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->endpointUuid = Cast::string($data, 'endpointUuid');
		$this->url = Cast::string($data, 'url');
		$this->delivered = Cast::bool($data, 'delivered');
		$this->attempts = Cast::listOf($data, 'attempts', DeliveryAttempt::fromArray(...));
		$this->pending = Cast::bool($data, 'pending');
		$this->nextAttemptAt = Cast::nullableDate($data, 'nextAttemptAt');
		$this->waitingFor = Cast::nullableString($data, 'waitingFor');
	}
}
