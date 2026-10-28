<?php

declare(strict_types=1);

namespace QBitFlow\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The website's code snippets come from the `// docs:start <id>` regions in examples/: runs the
 * snippet tooling's `scripts/embed-snippets.mjs --check` over this SDK (regions, manifest, README
 * blocks). The tooling lives in QBitFlow's maintainers' workspace, outside this repository: it is
 * found through QBITFLOW_HUB_DIR, else two directories up (where the maintainers' checkout keeps
 * this SDK). The test is skipped when there is none, or no `node`. No network.
 */
final class SnippetsTest extends TestCase
{
	#[Test]
	public function snippetsMatchTheCatalogAndTheReadme(): void
	{
		$sdk = dirname(__DIR__);
		$hub = getenv('QBITFLOW_HUB_DIR');
		if (is_string($hub) && $hub !== '') {
			self::assertTrue(self::isHub($hub), "QBITFLOW_HUB_DIR={$hub} has no scripts/embed-snippets.mjs and snippets/catalog.json");
		} else {
			$hub = dirname($sdk, 2);
			if (! self::isHub($hub)) {
				self::markTestSkipped('The snippet tooling is not available (maintainers only): set QBITFLOW_HUB_DIR to check the snippets');
			}
		}

		$node = trim((string) shell_exec('command -v node 2>/dev/null'));
		if ($node === '') {
			self::markTestSkipped('node is not on the PATH: the snippet check needs Node.js >= 18');
		}

		$command = [$node, $hub . '/scripts/embed-snippets.mjs', '--check', $sdk];
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
		self::assertIsResource($process, 'cannot run ' . implode(' ', $command));
		$output = (string) stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		self::assertSame(0, proc_close($process), "embed-snippets --check failed (run: node <workspace>/scripts/embed-snippets.mjs .):\n" . $output);
	}

	private static function isHub(string $dir): bool
	{
		return is_file($dir . '/scripts/embed-snippets.mjs') && is_file($dir . '/snippets/catalog.json');
	}
}
