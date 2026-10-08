<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use QBitFlow\Models\Model;
use QBitFlow\Support\Cast;

final readonly class DecodeTargetChild extends Model
{
	public int $x;

	protected function __construct(array $data)
	{
		$this->x = Cast::int($data, 'x');
	}
}
