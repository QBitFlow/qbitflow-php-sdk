<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\CreatedInvitation;
use QBitFlow\Models\Invitation;
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
	 * Invites someone to join the organization as a member in the key's mode
	 * (`POST /invitations`, 201) and returns the invitation with its link (QBitFlow also emails the
	 * person a link of their own, unless `sendEmail` is false). The SDK always sends role `user`: the team is invited from the
	 * dashboard. Sends an Idempotency-Key and is retried on transient failures. Organization key
	 * only; rate limited (50 an hour per organization, emailed or not: beyond, a
	 * {@see \QBitFlow\Exceptions\RateLimitException} at once, the wait being over the SDK's 60 s
	 * retry cap).
	 *
	 * The person exists in the organization once they accepted: act on the `member.joined`
	 * webhook (`MemberJoined::$invitationUuid`). Errors: 409 `already_joined` for someone already
	 * a member in that mode.
	 */
	public function create(CreateInvitationParams $params, ?RequestOptions $options = null): CreatedInvitation
	{
		$params->validate();

		return $this->requester->call('POST', '/invitations', Requester::one(CreatedInvitation::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * One page of the organization's member invitations of the key's mode (`GET /invitations`;
	 * newest first, page size 20 by default, at most 100), optionally filtered by `status`. The
	 * team's invitations are not listed: the team is managed from the dashboard. Organization key
	 * only.
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

	/**
	 * Revokes an invitation (`DELETE /invitations/:uuid`, 204): its link no longer works.
	 * Organization key only. Not retried.
	 */
	public function revoke(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/invitations/%s', $uuid), options: $options);
	}
}
