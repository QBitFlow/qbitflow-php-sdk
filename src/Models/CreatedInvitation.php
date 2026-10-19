<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * An invitation just created (`invitations->create()`): the invitation's fields and its link,
 * only returned here.
 */
final readonly class CreatedInvitation extends Invitation
{
	/**
	 * The link to accept it, also emailed to the person: share it in your own onboarding if you prefer.
	 */
	public string $link;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->link = Cast::string($data, 'link');
	}
}
