<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use DateTimeImmutable;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Validator;

/**
 * A webhook event: a v2 delivery's body, or an event of the log.
 *
 * {@see Event::fromArray()} returns the subclass of the event's `type`, whose `data` property is
 * typed ({@see PaymentCompletedEvent::$data} is a {@see \QBitFlow\Models\PaymentCompleted}, …);
 * an event of a type this SDK does not know is an {@see UnknownEvent} with its raw `data`.
 *
 * ```php
 * if ($event instanceof PaymentCompletedEvent) {
 *     fulfil($event->data->reference);
 * }
 * ```
 *
 * Deliveries are at least once: deduplicate on `id`, and answer 2xx fast (also to the events
 * you ignore). `userUuid` names the member whose space the event happened in: pass it to
 * `$client->onBehalfOf()` for follow-up reads.
 */
abstract readonly class Event
{
	/** The event's id (`evt_…`): the same on every retry, deduplicate on it. */
	public string $id;

	/** The event's type ({@see EventType}). */
	public string $type;

	/** The payload version (`v2`). */
	public string $version;

	/** When it happened. */
	public DateTimeImmutable $createdAt;

	/** True when it happened in test mode. */
	public bool $test;

	/** The member whose space it happened in; null for the organization's own. */
	public ?string $userUuid;

	/**
	 * The event's `data` object, raw, as an associative array.
	 *
	 * @var array<array-key,mixed>
	 */
	public array $rawData;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->id = Cast::string($data, 'id');
		$this->type = Cast::string($data, 'type');
		$this->version = Cast::string($data, 'version');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');

		$raw = Cast::plain($data['data'] ?? null);
		if ($raw !== null && ! is_array($raw)) {
			// Not an object nor a list: a body that is not an event's.
			throw new ServerException(sprintf('"data" must be an object, got %s', get_debug_type($raw)));
		}
		$this->rawData = $raw ?? [];
	}

	/**
	 * Builds the event of the envelope's `type` (an {@see UnknownEvent} for a type this SDK
	 * does not know). Used for API responses (the event log); webhook bodies go through
	 * {@see \QBitFlow\Webhooks\Webhook::parseEvent()}, which also checks the version.
	 *
	 * @param array<string,mixed> $data The envelope, decoded.
	 *
	 * @throws \QBitFlow\Exceptions\ServerException When a value has the wrong JSON type.
	 */
	public static function fromArray(array $data): self
	{
		$type = Cast::string($data, 'type');

		return match ($type) {
			EventType::PAYMENT_COMPLETED => new PaymentCompletedEvent($data),
			EventType::SUBSCRIPTION_CREATED => new SubscriptionCreatedEvent($data),
			EventType::SUBSCRIPTION_BILLED => new SubscriptionBilledEvent($data),
			EventType::SUBSCRIPTION_STATUS_CHANGED => new SubscriptionStatusChangedEvent($data),
			EventType::SUBSCRIPTION_ACTION_REQUIRED_CHANGED => new SubscriptionActionRequiredChangedEvent($data),
			EventType::SUBSCRIPTION_BILLING_FAILED => new SubscriptionBillingFailedEvent($data),
			EventType::SUBSCRIPTION_UPCOMING_BILL => new SubscriptionUpcomingBillEvent($data),
			EventType::REFUND_REQUESTED => new RefundRequestedEvent($data),
			EventType::REFUND_COMPLETED => new RefundCompletedEvent($data),
			EventType::REFUND_DENIED => new RefundDeniedEvent($data),
			EventType::MEMBER_JOINED => new MemberJoinedEvent($data),
			EventType::MEMBER_REMOVED => new MemberRemovedEvent($data),
			EventType::HELD_FUNDS_RELEASED => new HeldFundsReleasedEvent($data),
			EventType::CHECKOUT_EXPIRED => new CheckoutExpiredEvent($data),
			EventType::WEBHOOK_TEST => new WebhookTestEvent($data),
			default => new UnknownEvent($data),
		};
	}

	/**
	 * Decodes the event's data into another model, whatever the event's type (e.g. a
	 * `subscription.billingFailed` event's data as a plain {@see \QBitFlow\Models\Subscription}).
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T $factory e.g. `Subscription::fromArray(...)`
	 *
	 * @return T
	 *
	 * @throws ValidationException When the data does not fit the model.
	 */
	public function decodeData(callable $factory): mixed
	{
		try {
			/** @var array<string,mixed> $raw */
			$raw = $this->rawData;

			return $factory($raw);
		} catch (ServerException $e) {
			throw Validator::exception([new FieldError('data', 'data does not match the model: ' . $e->getErrorMessage())]);
		}
	}

	/** Whether an event's data is a subscription session's (`txType` `createSubscription`). */
	protected static function isSubscriptionSession(mixed $data): bool
	{
		$fields = $data === null ? [] : Cast::fields($data, 'data');

		return ($fields['txType'] ?? null) === TransactionType::CREATE_SUBSCRIPTION;
	}
}
