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
the same version. Milestone 0 uses version `0.1.0`.

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

## 10. Foundation release status

The repository has a Composer package, PSR-4 autoloading, pinned dependencies,
CI, asset builds, unit and integration-test configuration, browser-test
configuration, tagged artifact assembly, the RT-007 read-only global feature
flag adapter, the RT-101 Migration runtime, the RT-102 batches table, and the
RT-103 tags and RT-104 batch export audit tables.
RT-008 also supplies a default-disabled sanitized operational logger, but no
production sink or retention configuration is selected. It is not a
production-ready product release because no workflow consumes the flags or
logger and there is no public route, repository, email-provider adapter,
security workflow, batch workflow, or other product business logic.
