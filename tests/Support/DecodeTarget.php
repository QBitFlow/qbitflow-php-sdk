<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use DateTimeImmutable;
use QBitFlow\Models\Model;
use QBitFlow\Support\Cast;

/**
 * A model with one field of each kind, for the decoding policy tests.
 */
final readonly class DecodeTarget extends Model
{
	public string $s;

	public int $n;

	public int $u;

	public float $f;

	public bool $b;

	public DateTimeImmutable $t;

	public ?DateTimeImmutable $p;

	/** @var list<int> */
	public array $l;

	public DecodeTargetChild $o;

	public string $enum;

	public string $dec;

	protected function __construct(array $data)
	{
		$this->s = Cast::string($data, 's');
		$this->n = Cast::int($data, 'n');
		$this->u = Cast::uint($data, 'u');
		$this->f = Cast::float($data, 'f');
		$this->b = Cast::bool($data, 'b');
		$this->t = Cast::date($data, 't');
		$this->p = Cast::nullableDate($data, 'p');
		$this->l = Cast::uintList($data, 'l');
		$this->o = Cast::object($data, 'o', DecodeTargetChild::fromArray(...));
		$this->enum = Cast::string($data, 'enum');
		$this->dec = Cast::string($data, 'dec');
	}
}
