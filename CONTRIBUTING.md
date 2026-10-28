# Contributing

Thanks for helping improve the QBitFlow PHP SDK. Bug reports and pull requests are welcome on
[GitHub](https://github.com/QBitFlow/qbitflow-php-sdk). For a security problem, don't open an
issue: follow [SECURITY.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md).

## Set up

You need PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12` (the patched releases, see SECURITY.md)
and Composer 2.

```bash
git clone https://github.com/QBitFlow/qbitflow-php-sdk.git
cd qbitflow-php-sdk
composer install
```

## Gates

Every pull request must pass these, from the repository's root. They are offline (no API key,
no network) and are what CI runs on PHP 8.2 to 8.5:

```bash
composer validate --strict
vendor/bin/phpunit --testsuite Unit          # or: composer test
vendor/bin/phpstan analyse --memory-limit=1G # or: composer analyse
find src tests examples config -name '*.php' -print0 | xargs -0 -n1 php -l   # or: composer lint
```

`composer check` runs all four. The `Unit` suite is hermetic: every client is built on a mock
PSR-18 transport, so it gives the same result with or without `QBITFLOW_*` variables exported.

Two Unit tests depend on files kept outside this repository, by QBitFlow's maintainers, and **skip
themselves** when they are absent. That is expected in a clone of this repository, and they need
nothing from you:

- `ConformanceVectorsTest` runs the test vectors shared by QBitFlow's Go, JavaScript, Python and
  PHP SDKs, when `QBITFLOW_VECTORS_DIR` names their directory;
- `SnippetsTest` checks the website's code snippets (below), when the snippet tooling and `node`
  are available (`QBITFLOW_HUB_DIR`).

A local `phpunit.xml` (git-ignored) overrides `phpunit.xml.dist` if you need to set environment
variables for your own runs.

## Tests against a live API (optional)

The `Integration` suite talks to a real QBitFlow API. It is never run in CI. It skips itself when
`QBITFLOW_API_KEY` is unset, and fails when the key is set without `QBITFLOW_BASE_URL`: it never
falls back to a default server.

```bash
QBITFLOW_API_KEY=sk_… QBITFLOW_BASE_URL=https://… vendor/bin/phpunit --testsuite Integration
```

It only reads, unless you also set `QBITFLOW_LIVE_WRITES=1`. The write checks then create and
clean up their own products, customers, checkout sessions and webhook endpoints, and run only with
a test-mode key (`QBITFLOW_ALLOW_LIVE_MODE_WRITES=1` overrides that, for a dedicated test
server). Use a test-mode key of your own; never a production key.

## Code style

- `declare(strict_types=1);`, tabs, one class per file under `src/` (PSR-4, `QBitFlow\`).
- Every public class, method and constructor has a PHPDoc describing its behaviour and errors.
- Models are `readonly`; money and token amounts are decimal strings, never floats.
- A public behaviour change comes with tests and a CHANGELOG entry (Keep a Changelog), and keeps
  the README accurate.

## Examples and website snippets

The code blocks on qbitflow.app/docs and in the README are extracted from named regions of the
runnable programs in `examples/`, so they always run and never disagree.

- **Markers:** `// docs:start <id>` and `// docs:end <id>`, each on its own line (indentation
  allowed: the code is dedented). One region per id, in one file; regions never overlap or nest.
- **Each region reads on its own:** it assumes a ready client in `$client` (except
  `client-init` and `client-on-behalf-of`), names classes by their fully qualified names
  (`\QBitFlow\Params\…`, no `use` lines, no `<?php`, `declare` or `require`), and reads the ids
  it does not create from variables defined just outside it (`$paymentUuid`, `$subscriptionUuid`,
  `$sessionUuid`, `$memberUuid`, `$invitationUuid`). Keys only come from `QBITFLOW_API_KEY` and
  `QBITFLOW_WEBHOOK_SECRET`. Scaffolding (arguments, error handling for a rerun) stays outside.
- **Manifest:** `examples/snippets.manifest.json` maps every snippet id to the file holding its
  region (or lists it under `unsupported` with a reason).
- **README:** a `<!-- docs:snippet <id> -->` … `<!-- /docs:snippet -->` block is generated from
  its region: never edit its content by hand. If you change a region, say so in your pull request:
  a maintainer regenerates the README blocks with the snippet tooling (`embed-snippets.mjs`), which
  lives outside this repository.
- **Ids are a public contract with the website:** never rename or remove one.
