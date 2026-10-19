<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What the API key is, and where the request acts (`$client->me()`).
 */
final readonly class Me extends Model
{
	/**
	 * `apiKey` for an API key ({@see \QBitFlow\Enums\Credential}).
	 */
	public string $credential;

	/**
	 * The key's id.
	 */
	public ?string $apiKeyUuid;

	/**
	 * Who signed in, for a session; null for an API key (a key acts for its space, not for the
	 * person who created it).
	 */
	public ?string $userUuid;

	/**
	 * What the request may do in the space: `admin` (an organization key) or `user` (a member's
	 * key, or an organization key acting On-Behalf-Of a member); see {@see \QBitFlow\Enums\Role}.
	 */
	public ?string $role;

	/**
	 * The member an organization key acts as (On-Behalf-Of): the space is that member's. Null
	 * otherwise.
	 */
	public ?string $onBehalfOfUserUuid;

	/**
	 * The space the request reads and writes in (`space->test` says the mode).
	 */
	public ?MeSpace $space;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->credential = Cast::string($data, 'credential');
		$this->apiKeyUuid = Cast::nullableString($data, 'apiKeyUuid');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->role = Cast::nullableString($data, 'role');
		$this->onBehalfOfUserUuid = Cast::nullableString($data, 'onBehalfOfUserUuid');
		$this->space = Cast::nullableObject($data, 'space', MeSpace::fromArray(...));
	}
}
