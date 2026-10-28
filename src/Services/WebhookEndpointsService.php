<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\WebhookEndpoint;
use QBitFlow\Models\WebhookEndpointCreated;
use QBitFlow\Page;
use QBitFlow\Params\CreateWebhookEndpointParams;
use QBitFlow\Params\EndpointListParams;
use QBitFlow\Params\UpdateWebhookEndpointParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Manages the webhook endpoints (`/webhooks/endpoints…`). A member's key needs the
 * organization's `members.webhooks` policy (403 `policy_disabled`), as every endpoints and
 * events method.
 */
final class WebhookEndpointsService extends Service
{
	/**
	 * One page of the space's webhook endpoints (`GET /webhooks/endpoints`; newest first, page
	 * size 20 by default, at most 100; a space has at most 10 per mode).
	 *
	 * @return Page<WebhookEndpoint>
	 */
	public function list(?EndpointListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/webhooks/endpoints', Requester::page(WebhookEndpoint::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every endpoint `list()` returns, the pages fetched lazily, the page size kept.
	 *
	 * @return Generator<int,WebhookEndpoint>
	 */
	public function iterate(?EndpointListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new EndpointListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * Adds a webhook endpoint (`POST /webhooks/endpoints`, 201). The answer carries the endpoint's
	 * `secret` (`whsec_…`), shown only this once: store it to verify the deliveries. Sends an
	 * Idempotency-Key and is retried on transient failures (a replay of the same key within 24 h
	 * returns the same endpoint and secret). `includeMembers` null: true for an organization
	 * endpoint (a marketplace receives every seller's events), false for a member's.
	 *
	 * Errors: 400 validation_failed (live mode needs https and a public host); 409 `conflict`
	 * beyond 10 endpoints per space and mode.
	 */
	public function create(CreateWebhookEndpointParams $params, ?RequestOptions $options = null): WebhookEndpointCreated
	{
		$params->validate();

		return $this->requester->call('POST', '/webhooks/endpoints', Requester::one(WebhookEndpointCreated::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/** A webhook endpoint (`GET /webhooks/endpoints/:uuid`); its secret is never returned again. */
	public function get(string $uuid, ?RequestOptions $options = null): WebhookEndpoint
	{
		Validator::pathUuid('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/webhooks/endpoints/%s', $uuid), Requester::one(WebhookEndpoint::fromArray(...)), options: $options);
	}

	/**
	 * Changes a webhook endpoint (`PATCH /webhooks/endpoints/:uuid`): only the fields set change;
	 * `''` clears the description; `events` `[]` receives every type. `enabled` false pauses it,
	 * true enables it again (also after it was disabled for failing). Not retried.
	 */
	public function update(string $uuid, UpdateWebhookEndpointParams $params, ?RequestOptions $options = null): WebhookEndpoint
	{
		Validator::pathUuid('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PATCH', Requester::path('/webhooks/endpoints/%s', $uuid), Requester::one(WebhookEndpoint::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/** Deletes a webhook endpoint (`DELETE /webhooks/endpoints/:uuid`, 204): it receives nothing more. Not retried. */
	public function delete(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/webhooks/endpoints/%s', $uuid), options: $options);
	}
}
