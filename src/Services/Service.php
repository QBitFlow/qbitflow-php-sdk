<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;

/**
 * Base class of the services: each groups the methods of one API area. Reach them through the
 * client's properties (`$client->products`, `$client->webhooks->endpoints`…).
 */
abstract class Service
{
	/**
	 * @internal Created by {@see \QBitFlow\QBitFlow}.
	 */
	public function __construct(protected readonly Requester $requester)
	{
	}
}
