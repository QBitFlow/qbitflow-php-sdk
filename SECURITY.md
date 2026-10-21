# Security Policy

- **Reporting**: Report suspected vulnerabilities privately to _security@qbitflow.app_.
- **Supported Versions**: We support the latest minor release and the previous one.
- **Patch SLAs**: Critical issues patched within 72 hours; high within 7 days.
- **Disclosure**: Coordinated disclosure with CVE where appropriate; advisories posted in RELEASE_NOTES.md.
- **Integrity**: Official SDK builds are signed. Do not use unsigned or tampered builds in production.

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

- **refuses to install on an unpatched interpreter**: `composer.json` requires
  `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`;
- **bounds the webhook input it reads**, as the advisories ask of applications: every entry point
  (`WebhookRouter::handle()`, `Webhook::constructEvent()`, `Webhook::parseEvent()`, the PSR-7,
  PSR-15, globals and Laravel adapters) refuses a body over 1 MiB before reading it, and JSON
  nesting deeper than 64 levels;
- **emits no runtime warnings or notices** (early diagnostics are one of the bugs' triggers).

**A distribution's PHP with the fixes backported** (Debian, Ubuntu, RHEL…) keeps its older version
number, so Composer refuses it. Once you have checked your distribution's advisory, install with
`composer require qbitflow/qbitflow-php --ignore-platform-req=php`, or declare the fixed level in
your project's `composer.json` (`"config": {"platform": {"php": "8.3.36"}}`).

Until you can upgrade, the advisories' partial mitigations are server settings, not application
code: `session.upload_progress.enabled=0`, `fatal_error_backtraces=0` on PHP 8.5, a conservative
`post_max_size`, and restarting every PHP worker after a change.
