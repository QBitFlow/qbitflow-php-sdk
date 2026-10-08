<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What the API key is (`$client->me()`).
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
	 * Who signed in, for a session (null for an API key).
	 */
	public ?string $userUuid;

	/**
	 * `admin` (organization key) or `user` (a member's key, or On-Behalf-Of), see {@see \QBitFlow\Enums\Role}.
	 */
	public ?string $role;

	/**
	 * The member an organization key acts as (On-Behalf-Of); null otherwise.
	 */
	public ?string $onBehalfOf;

	/**
	 * The space the request reads and writes in.
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
		$this->onBehalfOf = Cast::nullableString($data, 'onBehalfOf');
		$this->space = Cast::nullableObject($data, 'space', MeSpace::fromArray(...));
	}
}
