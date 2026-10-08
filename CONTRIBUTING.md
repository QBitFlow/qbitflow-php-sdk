# Contributing

## Gates

Run these from the SDK's root before committing; all must pass:

```bash
composer validate --strict
vendor/bin/phpunit
find src tests examples -name '*.php' -print0 | xargs -0 -n1 php -l
node ../../scripts/embed-snippets.mjs --check .   # from the hub checkout, see below
```

`vendor/bin/phpunit` runs the hermetic `Unit` suite (a mock HTTP client, no network) and the
`Integration` suite, which skips itself without `QBITFLOW_API_KEY` (see the README's Testing
section): unset the `QBITFLOW_*` variables to stay offline.

## Website snippets

The code blocks on qbitflow.app/docs and in this README are extracted from named regions of the
runnable programs in `examples/`, so they always compile and never disagree.

- **Markers:** `// docs:start <id>` and `// docs:end <id>`, each on its own line (indentation
  allowed: the code is dedented). One region per id, in one file; regions never overlap or nest.
- **Ids** come from the hub's `snippets/catalog.json`, shared by the Go, JS, Python and PHP SDKs.
- **Each region reads on its own:** it assumes a ready client in `$client` (except
  `client-init` and `client-on-behalf-of`), names classes by their fully qualified names
  (`\QBitFlow\Params\…`, no `use` lines, no `<?php`, `declare` or `require`), uses the
  catalog's shared example values, and reads the ids it does not create from variables defined
  just outside it (`$paymentUuid`, `$subscriptionUuid`, `$sessionUuid`, `$memberUuid`,
  `$invitationUuid`). Keys only come from `QBITFLOW_API_KEY` and `QBITFLOW_WEBHOOK_SECRET`.
  Scaffolding (arguments, flags, error handling for a rerun) stays outside the region.
- **Manifest:** `examples/snippets.manifest.json` maps every catalog id to the file holding its
  region (or lists it under `unsupported` with a reason), and pins the hub commit of the catalog.
- **README:** a `<!-- docs:snippet <id> -->` … `<!-- /docs:snippet -->` block is filled by the
  script; never edit its content by hand.

From a hub checkout (this SDK in `sdk/qbitflow-php-sdk`):

```bash
node ../../scripts/embed-snippets.mjs .           # rewrite the README's snippet blocks
node ../../scripts/embed-snippets.mjs --check .   # change nothing; fail on any drift
```

`tests/SnippetsTest.php` runs the `--check` in `vendor/bin/phpunit`. It finds the hub through
`QBITFLOW_HUB_DIR`, else `../..`; it is skipped when there is no hub or no `node` (a lone clone of
this SDK), and fails when `QBITFLOW_HUB_DIR` is set to a directory that is not a hub.

### The standing rule

**Website snippets.** The code on qbitflow.app/docs and in this README comes from `// docs:start <id>` … `// docs:end <id>` regions in `examples/`. Every change keeps them true:
- **New public capability:** add a region, an id in the hub's `snippets/catalog.json` (the same id across all SDKs), and the manifest entry, plus the README block via `scripts/embed-snippets.mjs`.
- **Changed capability:** update the region's code. Never hand-edit an embedded README block: re-run the script.
- **Ids are a public contract with the website.** Never rename or remove one silently. To retire an id, keep it until the website no longer uses it, and note it in the CHANGELOG.
- **Before committing:** the examples run, the manifest check passes, and `embed-snippets --check` passes.
- **Unsupported ids:** an id the SDK can't support goes under `unsupported` with a reason. Never write placeholder code.
