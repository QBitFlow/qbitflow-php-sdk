<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\EventType;
use QBitFlow\Support\Validator;

/**
 * Creates a webhook endpoint (`webhooks->endpoints->create()`).
 */
final readonly class CreateWebhookEndpointParams
{
	/**
	 * @param list<string>|null $events The event types to receive ({@see EventType}; at most 20, not
	 *                                  `webhook.test`); null or empty: every type.
	 */
	public function __construct(
		/** Required: where the events are posted (https and a public host in live mode). */
		public string $url,
		public ?array $events = null,
		/** Makes an organization endpoint receive its members' events too (null: true for an organization endpoint, false for a member's). */
		public ?bool $includeMembers = null,
		/** A note for the dashboard (at most 200 characters). */
		public ?string $description = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('url', $this->url)) {
			$v->url('url', $this->url);
		}
		$v->endpointEvents('events', array_values($this->events ?? []));
		$v->text('description', $this->description, 0, 200);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = ['url' => $this->url];
		if ($this->events !== null && $this->events !== []) {
			$out['events'] = array_values($this->events);
		}
		if ($this->includeMembers !== null) {
			$out['includeMembers'] = $this->includeMembers;
		}
		if ($this->description !== null && $this->description !== '') {
			$out['description'] = $this->description;
		}

		return $out;
	}
}
