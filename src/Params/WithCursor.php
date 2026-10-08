<?php

declare(strict_types=1);

namespace QBitFlow\Params;

/**
 * The `withCursor()` of the paginated list params: a copy pointing at another page.
 *
 * @internal
 */
trait WithCursor
{
	/** A copy of these params with another cursor (null: the first page). */
	public function withCursor(?string $cursor): static
	{
		return new static(...[...get_object_vars($this), 'cursor' => $cursor]);
	}
}
