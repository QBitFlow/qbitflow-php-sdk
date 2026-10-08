<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Events\Event;
use QBitFlow\Http\Requester;
use QBitFlow\Models\EventDetail;
use QBitFlow\Page;
use QBitFlow\Params\EventListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Validator;

/**
 * Reads the webhook event log (`/webhooks/events…`).
 */
final class WebhookEventsService extends Service
{
	/**
	 * One page of the space's event log (`GET /webhooks/events`; newest first, page size 20 by
	 * default, at most 100). The cursor is an event id (`evt_…`). Each event is typed by its type.
	 *
	 * @return Page<Event>
	 */
	public function list(?EventListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/webhooks/events', Requester::page(Event::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every event `list()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Event>
	 */
	public function iterate(?EventListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new EventListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/** An event of the log with its deliveries (`GET /webhooks/events/:id`; `evt_…`). */
	public function get(string $id, ?RequestOptions $options = null): EventDetail
	{
		Validator::pathRequired('id', $id);

		return $this->requester->call('GET', Requester::path('/webhooks/events/%s', $id),
			static fn (mixed $decoded): EventDetail => Cast::one($decoded, EventDetail::fromArray(...)), options: $options);
	}
}
