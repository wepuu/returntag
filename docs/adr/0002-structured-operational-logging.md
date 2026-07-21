# ADR 0002: Structured operational logging boundary

- **Status:** Accepted
- **Date:** 2026-07-20
- **Ticket:** RT-008

## Context

TagCore needs diagnostic logs that can be consumed by WordPress hosting and
future monitoring systems without coupling Application code to WordPress or
copying sensitive product data into ordinary logs. Operational diagnostics
also need a clear boundary from the immutable business audit events planned
for `returntag_events`.

The initial adapter must not imply that production log transport, retention,
monitoring, or a business event catalogue has already been approved.

## Decision

Application code depends on `ApplicationLogger`, a project-owned marker port
that extends the PSR-3 `LoggerInterface`, and on the separate
`LogContextSanitizer` contract. Infrastructure provides:

- `SensitiveLogContextSanitizer`, which recursively redacts sensitive keys,
  email addresses, Bearer credentials, and common secret assignments;
- bounded strings, array sizes, and nesting depth so untrusted context cannot
  produce unbounded records;
- exception handling that retains only the exception class and numeric code,
  never its message or trace; and
- `WordPressErrorLogLogger`, which emits a single `[TagCore] `-prefixed JSON
  line containing `channel`, PSR-3 `level`, sanitized `message`, and sanitized
  `context`.

The adapter is disabled by default. It accepts an injectable writer for tests;
its production default targets the configured PHP error log. It uses
`wp_json_encode()` at the WordPress edge and rejects non-standard log levels,
including while emission is disabled. The log sink owns transport and
timestamps.

RT-008 does not register the logger in the plugin bootstrap, introduce an
option or environment variable, or emit any product business event. A future
composition/configuration ticket must explicitly enable the adapter and define
transport, retention, access, monitoring, and incident procedures.

Operational logs and business audit events remain distinct:

- operational logs diagnose software and infrastructure behavior and may be
  sampled or rotated by the configured sink;
- audit events record approved actor, action, target, result, and UTC metadata
  in durable product storage under a later schema and application ticket.

The sanitizer is defense in depth. Callers are still forbidden from supplying
plaintext OTPs, tokens, credentials, full private message bodies, or
unnecessary email addresses to logging APIs.

## Consequences

- Application code has a provider-neutral PSR-3 contract and remains free of
  WordPress logging functions.
- Tests can capture records without writing to a machine-level error log.
- No log is emitted merely by loading the plugin or constructing the adapter.
- RT-008 creates no table, option, migration, hook, route, or public API.
- Enabling logging without a reviewed sink and retention configuration remains
  intentionally deferred.
