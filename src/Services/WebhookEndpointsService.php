<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\WebhookEndpoint;
use QBitFlow\Models\WebhookEndpointCreated;
use QBitFlow\Params\CreateWebhookEndpointParams;
use QBitFlow\Params\UpdateWebhookEndpointParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Manages the webhook endpoints (`/webhooks/endpoints…`). A member's key needs the
 * organization's `members.webhooks` policy (403 `policy_disabled`).
 */
final class WebhookEndpointsService extends Service
{
	/**
	 * The space's webhook endpoints (`GET /webhooks/endpoints`; at most 10, not paginated).
	 *
	 * @return list<WebhookEndpoint>
	 */
	public function list(?RequestOptions $options = null): array
	{
		return $this->requester->call('GET', '/webhooks/endpoints', Requester::list(WebhookEndpoint::fromArray(...)), options: $options);
	}

	/**
	 * Adds a webhook endpoint (`POST /webhooks/endpoints`, 201). The answer carries the endpoint's
	 * `secret` (`whsec_…`), shown only this once: store it. Sends an Idempotency-Key and is
	 * retried on transient failures. 409 `conflict` beyond 10 endpoints per space and mode.
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
	 * Changes a webhook endpoint (`PUT /webhooks/endpoints/:uuid`): only the fields set change.
	 * `enabled` false pauses it; `payloadVersion` `v2` moves an endpoint migrated from v1. Not retried.
	 */
	public function update(string $uuid, UpdateWebhookEndpointParams $params, ?RequestOptions $options = null): WebhookEndpoint
	{
		Validator::pathUuid('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PUT', Requester::path('/webhooks/endpoints/%s', $uuid), Requester::one(WebhookEndpoint::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/** Deletes a webhook endpoint (`DELETE /webhooks/endpoints/:uuid`). Not retried. */
	public function delete(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/webhooks/endpoints/%s', $uuid), options: $options);
	}
}
