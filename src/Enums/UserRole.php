<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Role of a user within an organization, and of the API key issued to them.
 */
enum UserRole: string
{
	case ADMIN = 'admin';
	case USER = 'user';
	case OWNER = 'owner';
}
