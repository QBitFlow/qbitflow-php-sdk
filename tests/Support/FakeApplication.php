<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Illuminate\Container\Container;

/**
 * The smallest container that Laravel's console layer will accept.
 *
 * `Illuminate\Console\Command` reaches for a handful of methods that live on the full
 * Foundation application rather than the base container. Implementing just those keeps the
 * test suite off `orchestra/testbench` and the whole framework.
 */
final class FakeApplication extends Container
{
	public function __construct(private readonly string $basePath = '')
	{
	}

	public function runningUnitTests(): bool
	{
		return true;
	}

	public function runningInConsole(): bool
	{
		return true;
	}

	public function environment(): string
	{
		return 'testing';
	}

	public function basePath(string $path = ''): string
	{
		return $this->join($this->basePath, $path);
	}

	public function configPath(string $path = ''): string
	{
		return $this->join($this->basePath . DIRECTORY_SEPARATOR . 'config', $path);
	}

	private function join(string $base, string $path): string
	{
		return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
	}
}
