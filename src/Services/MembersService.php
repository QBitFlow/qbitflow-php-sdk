<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\HeldFunds;
use QBitFlow\Models\Member;
use QBitFlow\Models\MemberHeldFundsSummary;
use QBitFlow\Page;
use QBitFlow\Params\MemberListParams;
use QBitFlow\Params\UpdateMemberParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Manages the organization's members, their trust and their held funds (organization key).
 */
final class MembersService extends Service
{
	/**
	 * One page of the organization's members in the key's mode (`GET /members`; page size 20 by
	 * default, at most 100). Organization key only.
	 *
	 * @return Page<Member>
	 */
	public function list(?MemberListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/members', Requester::page(Member::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every member `list()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Member>
	 */
	public function iterate(?MemberListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new MemberListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/** A member by their user UUID (`GET /members/:userUuid`; the value `onBehalfOf` takes). */
	public function get(string $userUuid, ?RequestOptions $options = null): Member
	{
		Validator::pathUuid('userUuid', $userUuid);

		return $this->requester->call('GET', Requester::path('/members/%s', $userUuid), Requester::one(Member::fromArray(...)), options: $options);
	}

	/**
	 * Changes a member's organization fee in the key's mode (`PUT /members/:userUuid`). A checkout
	 * already created keeps its fee. Not retried.
	 */
	public function update(string $userUuid, UpdateMemberParams $params, ?RequestOptions $options = null): Member
	{
		Validator::pathUuid('userUuid', $userUuid);
		$params->validate();

		return $this->requester->call('PUT', Requester::path('/members/%s', $userUuid), Requester::one(Member::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/**
	 * Removes a member from the key's mode (`DELETE /members/:userUuid`): their API keys stop
	 * working and their checkouts close. 409 `held_funds_pending` while the organization holds
	 * their live funds. Not retried.
	 */
	public function remove(string $userUuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('userUuid', $userUuid);
		$this->requester->void('DELETE', Requester::path('/members/%s', $userUuid), options: $options);
	}

	/**
	 * Makes a member's new payments go to their own wallets (`POST /members/:userUuid/trust`);
	 * what is already held stays held until released in the dashboard. Not retried.
	 */
	public function trust(string $userUuid, ?RequestOptions $options = null): Member
	{
		Validator::pathUuid('userUuid', $userUuid);

		return $this->requester->call('POST', Requester::path('/members/%s/trust', $userUuid), Requester::one(Member::fromArray(...)), options: $options);
	}

	/**
	 * What the organization holds for each member in the key's mode (`GET /members/held-funds`).
	 *
	 * @return list<MemberHeldFundsSummary>
	 */
	public function listHeldFunds(?RequestOptions $options = null): array
	{
		return $this->requester->call('GET', '/members/held-funds', Requester::list(MemberHeldFundsSummary::fromArray(...)), options: $options);
	}

	/** What the organization holds for one member (`GET /members/:userUuid/held-funds`). */
	public function getHeldFunds(string $userUuid, ?RequestOptions $options = null): HeldFunds
	{
		Validator::pathUuid('userUuid', $userUuid);

		return $this->requester->call('GET', Requester::path('/members/%s/held-funds', $userUuid), Requester::one(HeldFunds::fromArray(...)), options: $options);
	}

	/**
	 * What the organization holds for the request's space (`GET /user/held-funds`): call it with
	 * a member's key, or `onBehalfOf` a member. Empty for the organization's own space.
	 */
	public function getOwnHeldFunds(?RequestOptions $options = null): HeldFunds
	{
		return $this->requester->call('GET', '/user/held-funds', Requester::one(HeldFunds::fromArray(...)), options: $options);
	}
}
