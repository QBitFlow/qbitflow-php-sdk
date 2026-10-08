<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * An event's deliveries to one endpoint.
 */
final readonly class EndpointDelivery extends Model
{
	/**
	 * The endpoint.
	 */
	public string $endpointUuid;

	/**
	 * The endpoint's URL now.
	 */
	public string $url;

	/**
	 * True when one attempt was answered with a 2xx.
	 */
	public bool $delivered;

	/**
	 * Its attempts, oldest first.
	 *
	 * @var list<DeliveryAttempt>
	 */
	public array $attempts;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->endpointUuid = Cast::string($data, 'endpointUuid');
		$this->url = Cast::string($data, 'url');
		$this->delivered = Cast::bool($data, 'delivered');
		$this->attempts = Cast::listOf($data, 'attempts', DeliveryAttempt::fromArray(...));
	}
}
