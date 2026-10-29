# Security Policy

## Supported versions

Only the **latest 3.x release** receives security fixes. Upgrade to it before reporting a
problem, and stay on it: fixes are released as a new 3.x version, never backported to 2.x or
to an older 3.x.

| Version | Security fixes |
|---------|----------------|
| latest 3.x | yes |
| older 3.x, 2.x and earlier | no: upgrade to the latest 3.x |

## Reporting a vulnerability

Report it **privately** by email to **security@qbitflow.app**. Please do not open a public
GitHub issue, pull request or discussion about it.

Include what you can of:

- the SDK version (`composer show qbitflow/qbitflow-php`), your PHP version, and the PSR-18 HTTP
  client and framework (Laravel version) you use;
- what the problem is and what an attacker could do with it;
- the steps or a minimal script that reproduce it, and the output you get;
- whether you believe it is being exploited.

Never send a live API key, a webhook secret or a customer's data: redact them, or use a test-mode
key you then revoke.

We acknowledge your report, keep you informed while we investigate and fix it, and agree with you
on when it is disclosed.

## Disclosure

Once a fix is released, the vulnerability is published as a
[GitHub Security Advisory](https://github.com/QBitFlow/qbitflow-php-sdk/security/advisories) of this
repository (with a CVE when one applies), and the release's entry in the
[CHANGELOG](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/CHANGELOG.md) lists it under
**Security**.

## How the SDK protects your credentials and your endpoint

These are properties of the code in this repository, each covered by its test suite.

- **The API key and the webhook secret never show up in a stack trace.** Every parameter that
  receives one (`new QBitFlow(apiKey: …)`, `QBitFlow::fromEnv()`, `Webhook::verify()`,
  `verifyRequest()`, `constructEvent()`, `sign()`, the router, the Laravel middleware, the
  `$client->webhooks` methods) carries `#[\SensitiveParameter]`, so PHP replaces its value with a
  `SensitiveParameterValue` in exception traces (the ones frameworks log or send to an error
  tracker).
- **They are not kept in plain object properties.** The client's transport, the webhook router
  and the Laravel middleware hold them wrapped in a `\SensitiveParameterValue`: `print_r()`,
  `var_dump()`, `var_export()` and an array cast of the client, a service or the router don't
  show them, and the client's own debug output shows the key as `***` followed by its last four
  characters only.
- **The client cannot be serialized.** `serialize()` of the client, of any of its services, of
  the webhook router or of the middleware throws (`SensitiveParameterValue` refuses serialization),
  so a key can never end up in a cache, a session or a queued job by accident. Build the client
  where you need it (in Laravel, resolve it from the container).
- **The key is checked before any request** (non-blank, `sk_…`, printable ASCII): a malformed
  value is refused at construction without being echoed in the error.
- **TLS is your HTTP client's.** Requests go through Guzzle (installed with the SDK) or the
  PSR-18 client you inject (Symfony HttpClient…), with its certificate verification. The SDK
  requires Guzzle 7.15.2 / 8.0.1 or later, the releases that fix Guzzle's 2026 advisories (proxy
  credentials sent to the origin, a silent HTTPS-proxy downgrade, host and cookie scoping). The
  default base URL is `https://api.qbitflow.app/v2`. A custom `baseUrl` must be an absolute `http`
  or `https` URL without credentials, query or fragment; use `http` only for a local test server.
- **Redirects are never followed.** The clients the SDK builds itself (Guzzle, Symfony) are
  configured not to follow them, so the `X-API-Key` header is never replayed to another host; a
  client you inject must be configured the same way.
- **Webhook signatures are verified in constant time.** `QBitFlow-Signature` is an HMAC-SHA256
  over `<timestamp>.<raw body>`; every `v1` signature in the header is compared with
  `hash_equals()` (during a secret rotation the header carries two), and a timestamp more than
  300 seconds (the default tolerance) from now is refused, which stops replays.
- **Webhook input is bounded.** Every entry point (`WebhookRouter::handle()`,
  `Webhook::constructEvent()`, `Webhook::parseEvent()`, the PSR-7, PSR-15, PHP-globals and
  Laravel adapters) refuses a body over 1 MiB before reading or parsing it, and JSON nested
  deeper than 64 levels.
- **No telemetry, no logging.** The SDK sends nothing but the API requests you make, to the base
  URL you configure, and writes no logs. See
  [COMPLIANCE.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/COMPLIANCE.md).

## PHP interpreter vulnerabilities (php2shell)

**CVE-2026-109618 and CVE-2026-109619** ("php2shell", CVSS 4.0 9.2, published 2026-10-21;
php-src advisories [GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
and [GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp))
are memory-corruption bugs **in the PHP interpreter**: an out-of-memory exit during request
start-up, and stale global state left by a fatal error, a timeout or an early diagnostic. They
allow unauthenticated remote code execution, before any application code runs on mod_php.

**Only upgrading PHP fixes them**: 8.2.35, 8.3.36, 8.4.27 or 8.5.12 (or later in the branch). PHP
7.4 to 8.1 have source backports only; 5.4 to 7.3 have no fix. No library can patch the
interpreter, so this SDK:

- **does not install on an unpatched interpreter**: `composer.json` requires
  `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`. Install with
  `composer require qbitflow/qbitflow-php:^3.0`: without the version, Composer on an unpatched PHP
  quietly falls back to 2.1.0 (the SDK for the previous API) instead of failing;
- **bounds the webhook input it reads**, as the advisories ask of applications: every entry point
  (`WebhookRouter::handle()`, `Webhook::constructEvent()`, `Webhook::parseEvent()`, the PSR-7,
  PSR-15, globals and Laravel adapters) refuses a body over 1 MiB before reading it, and JSON
  nesting deeper than 64 levels;
- **emits no runtime warnings or notices** (early diagnostics are one of the bugs' triggers).

**A distribution's PHP with the fixes backported** (Debian, Ubuntu, RHEL…) keeps its older version
number, so Composer treats it as unpatched. Once you have checked your distribution's advisory,
install with `composer require qbitflow/qbitflow-php:^3.0 --ignore-platform-req=php`, or declare
the fixed level in your project's `composer.json` (`"config": {"platform": {"php": "8.3.36"}}`).

Until you can upgrade, the advisories' partial mitigations are server settings, not application
code: `session.upload_progress.enabled=0`, `fatal_error_backtraces=0` on PHP 8.5, a conservative
`post_max_size`, and restarting every PHP worker after a change.
