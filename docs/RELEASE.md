# ReturnTag Release Baseline

**Status:** Engineering quality and artifact automation available

**Artifact:** `tagcore-v{version}.zip`

## 1. Purpose

This document defines versioning, quality gates, artifact, deployment, and
rollback procedures for TagCore. Composer, Node build scripts, continuous
integration, dependency monitoring, and tagged artifact assembly are present.
Production publication and deployment remain manual, explicitly authorized
operations.

## 2. Versioning

TagCore uses semantic versioning:

- patch releases contain backward-compatible fixes;
- minor releases contain backward-compatible functionality;
- major releases may contain explicitly approved breaking changes.

The plugin header, release tag, artifact name, and release record must identify
the same version. Milestone 0 uses version `0.1.0`; Milestone 1 closes at
version `0.2.0` with Schema version `8`.

## 3. Git workflow

- `main` remains deployable and is never force-pushed.
- Work occurs on one focused branch per `RT-` ticket.
- Changes reach `main` through review after required checks pass.
- A task does not imply permission to commit, push, merge, open a pull request,
  or tag a release; each action requires explicit authorization.
- Merged mistakes are corrected by a new fix or revert, not history rewriting.

## 4. Release gates

Before a release candidate is approved:

1. Confirm the intended ticket and complete diff.
2. Confirm no secrets, personal data, production exports, or unrelated files.
3. Run `composer check` from `plugin/tagcore` and `npm run check` from the
   repository root.
4. Run the WordPress integration and Playwright suites when the change affects
   platform integration or user-visible behavior.
5. Run fresh-install and previous-schema upgrade tests for database changes.
6. Review authorization, privacy, abuse, email, queue, and feature-flag impact.
7. Confirm the previous stable code remains compatible with the deployed schema.
8. Record every check that could not be run; never report it as passing.

## 5. Artifact contract

Production receives an immutable ZIP named `tagcore-v{version}.zip`. The ZIP
contains `tagcore/` at its root, not the outer `returntag/` repository.

The artifact includes only runtime files required by the plugin. It excludes
development dependencies, tests unless explicitly needed, local configuration,
credentials, caches, logs, coverage, source exports, and repository metadata.

Every artifact receives a SHA-256 checksum. Rebuilding the same release tag
must not silently replace an already published artifact; publish a new version
when content changes.

An approved Git tag named `tagcore-v{version}` triggers the release-artifact
workflow. The workflow verifies that `{version}` matches the plugin header,
installs production-only Composer dependencies, builds assets, packages the
plugin with `tagcore/` at the ZIP root, and uploads the ZIP and checksum as
workflow artifacts. It does not publish or deploy them automatically.

## 6. Release record

Record at minimum:

```text
Git commit
Git tag
plugin version
schema version
artifact filename
artifact SHA-256
build timestamp in UTC
build environment
approver
deployment timestamp in UTC
post-deployment verification result
```

## 7. Deployment

- Build from an approved Git tag in a clean environment.
- Deploy the immutable ZIP through the approved WordPress deployment process.
- Do not deploy production using `git pull` or an uncommitted working tree.
- Back up affected systems and confirm recovery procedures before a migration.
- Apply schema changes through numbered migrations, not ad hoc SQL.
- Verify plugin version, schema version, critical routes, queues, and operational
  controls after deployment.

## 8. Database compatibility

Migrations use an expand, migrate, and later contract approach. A release that
stops writing a field or table must not drop it in the same release. The
previous stable application must remain able to read the schema during the
defined rollback window.

Never roll back by deleting generated or exported Tag IDs, batch export
history, completed owner claims, audit events, or accepted messages.

RT-101 establishes the forward-only execution path. It records only verified
versions in the non-autoloaded site option `returntag_schema_version`, uses a
site-specific advisory lock, and retains the last successful version on
failure. Activation, a completed TagCore update, and an authorized admin
compensation check are the only triggers; public requests do not run DDL.

RT-102 advances the target Schema from `0` to `1` and creates the dynamically
prefixed `returntag_batches` table. Deployment must back up the database and
verify version `1`, InnoDB, the complete column contract, the unique batch code,
and all compound indexes. A failed verification retains version `0` and leaves
the table available for diagnosis and safe retry. Code rollback within the
`0.1.0` line preserves the Schema option and batches table; previous stable
code does not read them.

RT-103 advances the target Schema from `1` to `2` and creates the dynamically
prefixed `returntag_tags` table. A fresh installation applies the contiguous
`0 -> 1 -> 2` chain; an upgrade from RT-102 preserves the batches table and
data while adding tags. Deployment must verify version `2`, both InnoDB tables,
the exact tag column contract, primary key, and compound indexes. Migration
`0002` fails closed if the predecessor batches contract has drifted. A failed
tags verification retains version `1` and leaves non-destructive state for
diagnosis and retry. Code rollback within the `0.1.0` line preserves the Schema
option and both tables; previous stable code does not read the tags table.

RT-104 advances the target Schema from `2` to `3` and creates the dynamically
prefixed `returntag_batch_exports` table. Fresh install applies
`0 -> 1 -> 2 -> 3`; upgrade preserves Batches and Tags data. Deployment must
verify version `3`, InnoDB, the exact column contract, the unique Batch/version
index, and the non-unique Batch/checksum index. Failure retains version `2`;
rollback preserves all three tables and export audit history. Version `0.1.0`
code does not read the new table.

RT-105 advances the target Schema from `3` to `4` and creates the dynamically
prefixed `returntag_auth_challenges` table. Fresh install applies
`0 -> 1 -> 2 -> 3 -> 4`; upgrade preserves the first three tables and their
data. Deployment must verify version `4`, InnoDB, the exact sensitive-column
contract, default counters, nullability, and all three compound indexes.
Failure retains version `3` and leaves non-destructive state for diagnosis and
retry. Rollback preserves all four tables and the Schema option; version
`0.1.0` does not read authentication challenges. No retention cleanup or
business write path is enabled by this ticket.

RT-106 advances the target Schema from `4` to `6` through independently
verified versions `5` and `6`, creating dynamically prefixed
`returntag_conversations` and `returntag_messages` tables. Fresh install applies
`0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6`; upgrade preserves all prior tables and data.
Deployment must verify version `6`, both InnoDB tables, their exact privacy
columns, binary collations, nullability, message default `queued`, and compound
index order. Failure before Conversations retains version `4`; failure during
Messages retains verified version `5` and retry resumes at `0006`. Rollback
preserves both new tables, encrypted records, and the Schema option. Version
`0.1.0` exposes no conversation or message business write path.

RT-107 advances the target Schema from `6` to `7` and creates the dynamically
prefixed `returntag_access_tokens` table. Fresh install applies
`0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7`; upgrade preserves all prior tables and
data. Deployment must verify version `7`, InnoDB, the exact hash-only column
contract, unique digest index, lifecycle nullability, binary collations, and
compound index order. Failure retains version `6` and non-destructive state for
diagnosis and retry. Rollback preserves all seven tables and the Schema option;
version `0.1.0` exposes no token generation, exchange, or business write path.

RT-108 advances the target Schema from `7` to `8` and creates the dynamically
prefixed `returntag_events` table. Fresh install applies
`0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8`; upgrade preserves all prior tables
and access-token data. Deployment must verify version `8`, InnoDB, the exact
privacy-safe column contract, ASCII binary identifiers, metadata nullability,
and all actor, target, type, correlation, and global-time index orders. Failure
retains version `7` and non-destructive state for diagnosis and retry. Rollback
preserves all eight tables, audit events, and the Schema option; version
`0.1.0` exposes no event writer or query path.

RT-109 leaves the target Schema at `8` and the plugin version at `0.1.0`. It
adds internal typed persistence records, non-interchangeable sensitive-value
objects, Repository ports, `$wpdb` adapters, bounded cursors, default-deny
Event identity/metadata validation, and a transaction boundary without adding
a Migration, DDL, Option, Hook, route, or product workflow. Correlation queries
use the existing index through an `event_id` cursor. Schema inspection errors
now stop before DDL instead of being classified as absent tables. Fresh install
and upgrade behavior are otherwise unchanged. Code rollback preserves all
eight tables, Schema version `8`, and stored data; the prior stable code does
not instantiate these adapters.

RT-110 updates the plugin and project version from `0.1.0` to `0.2.0` while
leaving Schema version `8` unchanged. Acceptance covers fresh activation,
upgrade from version `4`, reconciliation of a complete schema with a missing
Option, non-destructive uninstall, bounded Repository query plans, and the
MariaDB 10.11/MySQL 8.0 compatibility matrix. No dependency or lock resolution
changes are included.

Code rollback to the RT-109 `0.1.0` baseline preserves all eight tables,
`returntag_schema_version=8`, and stored records. That code understands the same
Schema and registers no product Repository consumer. RT-110 does not authorize
or create a Git tag, release ZIP, GitHub Release, publication, or deployment.

RT-201 leaves plugin version `0.2.0` and Schema version `8` unchanged. Fresh
activation installs the existing eight tables and capability contract version
`1`; upgrade or direct ZIP replacement reconciles administrator capabilities
on an authorized `admin_init` request. The only new write is an atomic draft
Batch plus `batch.created` Event. Code rollback may leave
`returntag_capability_schema_version=1` and the two administrator capabilities
in place; the previous `0.2.0` code does not use them. Disabling TagCore removes
the admin page and routes without deleting Batch or Event data.

RT-202 also leaves plugin version `0.2.0` and Schema version `8` unchanged. It
adds pure Domain/Application contracts and a PHP secure-random Infrastructure
adapter without activation work, DDL, Options, Hooks, routes, queues, external
side effects, or persisted data. Deployment requires only the normal PHP
quality gate. Code rollback removes the candidate generator while preserving
all existing tables, Options, Batches, Events, and Tag IDs; no data repair or
feature-flag action is required.

RT-203 leaves plugin version `0.2.0` and Schema version `8` unchanged. It adds
an Application collision-retry service and duplicate-key classification for
application-supplied primary-key inserts. There is no DDL, Option, Hook, route,
queue, UI, dependency, Batch-state, counter, or Event change. Rollback removes
the unused orchestration code while preserving every Tag row. If collision
exhaustion or unexpected persistence failures occur during later batch work,
disable that future worker; do not delete or reuse Tags.

RT-204 leaves plugin version `0.2.0` and Schema version `8` unchanged. It adds
one capability-protected REST command, the internal
`returntag_generate_batch_chunk` Action Scheduler hook, conditional Batch
progress writes, and two aggregate lifecycle Event types. It adds no DDL,
Option, dependency, export, activation, public route, email, or WooCommerce
behavior. Deployment must confirm Action Scheduler has a real Cron or WP-CLI
runner and failed-action monitoring.

Code rollback preserves every committed Tag, Batch counter, status, and Event.
Stop risk first by disabling TagCore or pausing the
`returntag-tag-generation` group. Restore compatible code and repost the
generation command to resume from committed progress. Never decrement the
counter, delete generated Tags, or reuse IDs. A Batch left in `generating`
after a queue failure is recoverable through the idempotent POST command.

RT-205 and RT-206 leave plugin version `0.2.0` and Schema version `8`
unchanged. RT-205 adds aggregate progress and queue visibility. RT-206 adds a
read-only, capability-protected Batch Tag inventory projection with no DDL,
Option, dependency, file, checksum, audit append, download, or external side
effect. Deployment must verify no-store headers, complete-Batch gating,
50/100-row keyset pagination, deterministic Tag ID order, and exclusion of
private Tag fields. Code rollback removes the projection while preserving all
Tags, Batches, Events, counters, and statuses.

RT-207 also leaves plugin version `0.2.0` and Schema version `8` unchanged. It
activates the existing Batch Export Repository for capability-protected CSV
creation and bounded audit history. Deployment must verify deterministic
`tag_id ASC` bytes, exact row count and SHA-256, first-export
`generated -> exported`, re-export digest equality, private temporary-file
cleanup, and exclusion of prohibited fields. Code rollback removes the route
and UI while preserving Export rows, Events, exported Batch state, and all Tag
IDs. Do not repair rollback by deleting audit history or changing an exported
Batch back to generated.

RT-208 leaves plugin version `0.2.0` and Schema version `8` unchanged. It adds
capability-protected lifecycle read, Release, Suspend, and Void routes plus
transactional Event append. Deployment must verify that release requires a
matching audited export, global activation remains authoritative, stale
expected states conflict, and Suspend/Void preserve active Tag rows.

Disable TagCore to remove the controls. Code rollback retains resulting Batch
states, activation controls, all Tags, Export rows, and Events. Never repair a
rollback by reverting generated counters, deleting audit evidence, or reusing
voided or suspended identifiers.

## 9. Incident response and rollback

When a release causes risk:

1. Contain impact using the relevant global or batch feature control.
2. Preserve logs and evidence without copying secrets or private messages.
3. Confirm database compatibility with the previous stable code.
4. Deploy the previous immutable artifact when compatible, or issue a reviewed
   forward fix when rollback is unsafe.
5. Use a reviewed forward migration or repair command for data repair; do not
   run destructive production SQL.
6. Verify the repaired flow and document the incident and follow-up actions.

Feature flags are containment tools, not substitutes for authorization,
validation, tests, or a permanent fix.

## 10. Milestone 1 release status

The repository has a Composer package, PSR-4 autoloading, pinned dependencies,
CI, asset builds, unit and integration-test configuration, browser-test
configuration, tagged artifact assembly, the RT-007 read-only global feature
flag adapter, the RT-101 Migration runtime, the RT-102 batches table, and the
RT-103 tags, RT-104 batch export audit, RT-105 authentication challenge, and
RT-106 conversation and message, RT-107 access token, and RT-108 business event
tables. RT-109 provides typed Repository contracts and `$wpdb` adapters for
those tables without registering a production consumer. RT-110 verifies the
production Migration composition and closes the data milestone at `0.2.0`.
RT-201 adds the first capability-protected Batch create/list/detail
administrative workflow without changing the release version or Schema.
RT-202 adds the canonical secure in-memory candidate ID generator without
changing the administration interface, release version, or Schema.
RT-203 adds bounded insert-first collision retry without changing the
administration interface, release version, or Schema.
RT-204 adds resumable 100-Tag Action Scheduler chunks, atomic Batch progress,
and audited start/completion transitions without changing release version or
Schema. RT-205 adds the second confirmation, authenticated aggregate progress
query, queue-health projection, bounded admin polling, and idempotent recovery
UI without changing release version or Schema.
RT-206 adds the authenticated deterministic Batch Tag inventory and paginated
admin list without enabling CSV export or changing release version or Schema.
RT-207 adds the capability-protected deterministic CSV download, immutable
export audit versions, SHA-256 verification, and generated-to-exported
transition without changing release version or Schema.
RT-208 adds audited Batch release, suspension, and permanent void controls
without changing release version, Schema, or Tag rows.
RT-008 also supplies a default-disabled sanitized operational logger, but no
production sink or retention configuration is selected. It is not a
production-ready product release because there is no public scan route, owner
activation workflow, email-provider adapter, finder relay, or WooCommerce
business workflow.

RT-209 leaves plugin version `0.2.0` and Schema version `8` unchanged. It
advances the non-autoloaded capability contract from version `1` to `2` and
adds `manage_returntag_tags` without downgrading future stored versions.
Deployment must verify the Tags submenu, exact Tag ID and Batch Code modes,
optional status filter, bounded filter-bound cursor, no-store headers, and
private-field exclusion. It must also verify that Tag status, Batch status, and
the server-derived activation availability are shown separately; suspended and
voided IDs remain searchable, unregistered IDs are blocked as appropriate, and
existing active Tags are described as retained.

Code rollback removes the page and route while preserving every Tag, Batch,
Export, Event, and Schema record. Capability version `2` and the inert
administrator capability may remain.
