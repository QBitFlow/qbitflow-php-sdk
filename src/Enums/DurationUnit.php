<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Units accepted by {@see \QBitFlow\Support\Duration}.
 */
enum DurationUnit: string
{
	case SECONDS = 'seconds';
	case MINUTES = 'minutes';
	case HOURS = 'hours';
	case DAYS = 'days';
	case WEEKS = 'weeks';
	case MONTHS = 'months';
	case YEARS = 'years';
}
