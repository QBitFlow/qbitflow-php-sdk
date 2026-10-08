<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Output formats supported by the accounting export endpoint.
 */
enum ExportFormat: string
{
	/** Returns a list of {@see \QBitFlow\Dto\AccountingEvent}. */
	case JSON = 'json';

	/** Returns the raw CSV document as a string. */
	case CSV = 'csv';
}
