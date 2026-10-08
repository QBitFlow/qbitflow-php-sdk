<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Invitation;
use QBitFlow\Models\InvitationCreated;
use QBitFlow\Page;
use QBitFlow\Params\CreateInvitationParams;
use QBitFlow\Params\InvitationListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Invites members to the organization (organization key).
 */
final class InvitationsService extends Service
{
	/**
	 * Invites someone to join as a member in the key's mode (`POST /invitations`, 201): the
	 * invitation and its link (also emailed). The SDK always sends role `user`. Sends an
	 * Idempotency-Key and is retried on transient failures. The person exists once they accepted:
	 * act on `member.joined`. 409 `already_joined`; 429 beyond 50 an hour.
	 */
	public function create(CreateInvitationParams $params, ?RequestOptions $options = null): InvitationCreated
	{
		$params->validate();

		return $this->requester->call('POST', '/invitations', Requester::one(InvitationCreated::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * One page of the organization's invitations (`GET /invitations`; page size 20 by default, at most 100).
	 *
	 * @return Page<Invitation>
	 */
	public function list(?InvitationListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/invitations', Requester::page(Invitation::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every invitation `list()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Invitation>
	 */
	public function iterate(?InvitationListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new InvitationListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/** Revokes an invitation (`DELETE /invitations/:uuid`) and returns it. Not retried. */
	public function revoke(string $uuid, ?RequestOptions $options = null): Invitation
	{
		Validator::pathUuid('uuid', $uuid);

		return $this->requester->call('DELETE', Requester::path('/invitations/%s', $uuid), Requester::one(Invitation::fromArray(...)), options: $options);
	}
}
