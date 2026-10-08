<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\Event;

/**
 * Dispatched for every verified webhook, whatever its type (an
 * {@see \QBitFlow\Events\UnknownEvent} for a type this SDK does not know yet), after the event of
 * its type. Listen to it to store every delivery, or to handle the types you add yourself.
 */
final class WebhookReceived
{
	public function __construct(
		/** The verified webhook event. */
		public readonly Event $event,
	) {
	}
}
