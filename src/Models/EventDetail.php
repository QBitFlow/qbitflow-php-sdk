<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Events\Event;
use QBitFlow\Support\Cast;

/**
 * An event of the log with its deliveries (`webhooks->events->get()`): the event's envelope and
 * typed `data` (as on {@see Event}), plus its `deliveries`.
 */
final readonly class EventDetail
{
	/** The event's id (`evt_…`). */
	public string $id;

	/** The event's type ({@see \QBitFlow\Enums\EventType}). */
	public string $type;

	/** The payload version (`v2`). */
	public string $version;

	/** When it happened. */
	public DateTimeImmutable $createdAt;

	/** True when it happened in test mode. */
	public bool $test;

	/** The member whose space it happened in; null for the organization's own. */
	public ?string $userUuid;

	/** The event's data, typed by its type (raw `array` for a type this SDK does not know). */
	public mixed $data;

	/**
	 * The event's data, raw.
	 *
	 * @var array<array-key,mixed>
	 */
	public array $rawData;

	/** The event, as its {@see Event} subclass. */
	public Event $event;

	/**
	 * Its deliveries to the space's endpoints.
	 *
	 * @var list<EndpointDelivery>
	 */
	public array $deliveries;

	private function __construct(Event $event, array $deliveries)
	{
		$this->event = $event;
		$this->id = $event->id;
		$this->type = $event->type;
		$this->version = $event->version;
		$this->createdAt = $event->createdAt;
		$this->test = $event->test;
		$this->userUuid = $event->userUuid;
		$this->data = property_exists($event, 'data') ? $event->data : $event->rawData;
		$this->rawData = $event->rawData;
		$this->deliveries = $deliveries;
	}

	/**
	 * @param array<string,mixed> $data The event, with its `deliveries`.
	 */
	public static function fromArray(array $data): self
	{
		return new self(Event::fromArray($data), Cast::listOf($data, 'deliveries', EndpointDelivery::fromArray(...)));
	}
}
