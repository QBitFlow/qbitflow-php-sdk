<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Role of a user within an organization, and of the API key issued to them.
 *
 * The hierarchy is `HANDLE < USER < ADMIN < OWNER`. `HANDLE` and `OWNER` are read-only:
 * the API accepts only `admin` or `user` when creating a user.
 */
enum UserRole: string
{
	/** Lowest authenticated tier — a special case of a user, backing the second frontend app. */
	case HANDLE = 'handle';
	case USER = 'user';
	case ADMIN = 'admin';
	/** Organization owner. */
	case OWNER = 'owner';

	/** Whether this role can be assigned when creating a user (the API binds `oneof=admin user`). */
	public function isAssignableOnCreate(): bool
	{
		return $this === self::ADMIN || $this === self::USER;
	}
}
