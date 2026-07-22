# ADR 0003: Numbered database migration runtime

- **Status:** Accepted
- **Date:** 2026-07-22
- **Ticket:** RT-101

## Context

TagCore needs eight custom tables during Milestone 1, but schema changes must
not run on public traffic or on every WordPress request. A failed or concurrent
upgrade must preserve the last verified schema version and remain safe to
retry. Routine code rollback and uninstall must preserve product records,
especially generated Tag IDs and audit history.

WordPress `dbDelta()` can assist with compatible table creation, but it does
not provide migration ordering, mutual exclusion, postcondition verification,
or release rollback policy. WordPress multisite also gives each site its own
table prefix and option scope, so network-wide activation cannot be treated as
one atomic schema operation.

## Decision

TagCore uses a numbered, forward-only migration runtime with these contracts:

- `Migration` supplies one positive contiguous version, a stable name, an
  idempotent `up()` operation, and explicit postcondition verification.
- `MigrationRegistry` rejects duplicate, out-of-order, missing, or unnamed
  migrations before database work begins.
- `returntag_schema_version` records only the last successfully verified
  migration as a non-autoloaded, site-scoped WordPress option. Missing or
  malformed values fail closed to version `0`.
- `MigrationRunner` re-reads the version after acquiring a database advisory
  lock, applies only pending migrations, verifies each one, and advances the
  option one version at a time. Failure leaves the failed version unapplied.
- `GET_LOCK()` serializes migrations. Its name hashes the current WordPress
  site ID and active `$wpdb->prefix`, and the lock is released in `finally`.
- `SchemaState` exposes current, target, and readiness values for future
  fail-closed product startup behavior.

Migrations may run only during single-site plugin activation, after WordPress
completes an update of TagCore, or from an `admin_init` compensation check by a
user with `activate_plugins`. Ordinary public requests do not execute DDL.
Network-wide activation is rejected in Milestone 1; a multisite operator must
activate TagCore separately for each site.

RT-101 registers an empty migration registry, so its target remains version
`0` and it creates neither an option nor a table. RT-102 through RT-108 add
versions `0001` through `0008`. Those migrations may use `dbDelta()` as a
creation helper, but numbered ordering and explicit schema verification remain
authoritative.

Milestone 1 uses no physical foreign keys. Integrity is enforced through
unique constraints, indexes, typed repositories, application checks, and
non-deletion policy. Schema releases use expand, migrate, and later contract;
there are no destructive production `down()` migrations.

Errors shown in WordPress are generic and contain no SQL, database credentials,
table details, or raw exception text. `MigrationReport` contains only starting,
ending, and applied version numbers. RT-101 does not enable the RT-008 logger.

## Consequences

- Fresh installs, partial upgrades, and retry after failure use one execution
  path.
- A completed version is never recorded before its postconditions pass.
- Direct ZIP replacement is repaired on a later authorized admin request while
  public traffic remains free of schema changes.
- Schema changes must remain compatible with the previous stable application
  during the documented rollback window.
- Rolling code back to `0.1.0` preserves `returntag_schema_version` and all
  ReturnTag tables; the older code does not use them.
- Multisite network activation needs a separately designed resumable rollout
  before it can be supported.
