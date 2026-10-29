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
	 * A link to accept it, to share in your own onboarding if you prefer (with `sendEmail` false,
	 * the only one). Signing up through it emails the person a confirmation first. The link
	 * QBitFlow emails has its own token, never returned: it differs from this one.
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
