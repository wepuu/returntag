# ReturnTag Release Baseline

**Status:** Engineering quality and artifact automation re-certified against
runtime baseline `main@9400ae9`, TagCore 0.5.0, Schema 14, capability contract
6, and ForgeTag Theme 0.1.0; the P0-to-v1.0 release sequence is tracked in the
[delivery roadmap](ROADMAP.md)

**Plugin artifact:** `tagcore-v{version}.zip`

**Theme artifact:** `forge-tag-v{version}.zip`

## 1. Purpose

This document defines versioning, quality gates, artifact, deployment, and
rollback procedures for TagCore and the independently versioned ForgeTag
Theme. Composer, Node build scripts, continuous integration, dependency
monitoring, TagCore tagged artifact assembly, and ForgeTag tagged artifact
assembly are present. The ForgeTag Theme engineering skeleton, design tokens,
pinned runtime assets, product-media baseline, and WooCommerce Template
baseline are present. The Stage 5 TagCore integration and independence gates
are also present. Consumer frontend closure, remaining P0 runtime work, and
the release-candidate matrix are tracked in the
[delivery roadmap](ROADMAP.md). Production publication and deployment remain
manual, explicitly authorized operations.

## 2. Versioning

TagCore uses semantic versioning:

- patch releases contain backward-compatible fixes;
- minor releases contain backward-compatible functionality;
- major releases may contain explicitly approved breaking changes.

The plugin header, release tag, artifact name, and release record must identify
the same version. Milestone 0 uses version `0.1.0`; Milestone 1 closes at
version `0.2.0` with Schema version `8`; Milestone 2 closes at version `0.3.0`
with Schema version `8`; Milestone 3 closes at version `0.4.0` with Schema
version `8`; and the current merged baseline remains TagCore `0.5.0` at Schema
`14`. After the remaining P0 gates close, the next release candidate advances
directly to `0.9.0`; empty retrospective 0.6.0, 0.7.0, and 0.8.0 releases are
not published. Version `1.0.0` requires the complete Milestone 8 gate.

The ForgeTag Theme uses independent semantic versioning. Its version is
declared in `theme/forge-tag/style.css` and must not be inferred from the
TagCore plugin version or Schema version. RT-314 Stages 1 through 5 establish
Theme version `0.1.0`, its design-system foundation, homepage and product-media
baseline, WooCommerce Template baseline, artifact automation, and TagCore
integration gates; this does not represent production release approval.

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

### 5.1 ForgeTag Theme artifact and Site Editor governance

The Theme identity is `theme/forge-tag/` with the `forge-tag` Text Domain.
TagCore and ForgeTag Theme versions are independent. The Theme release contract
is:

```text
Theme version source: theme/forge-tag/style.css
Git tag:              forge-tag-v{version}
Artifact:             forge-tag-v{version}.zip
Archive root:         forge-tag/
Checksum:             forge-tag-v{version}.zip.sha256
```

The Theme ZIP contains only runtime files required by WordPress. It excludes
tests, source design references, repository documentation outside the Theme,
Node dependencies, caches, logs, reports, local configuration, credentials,
and repository metadata. It must not contain any RT-313 `reference-only` or
`excluded-local` asset. Every included font, icon, and third-party runtime
asset must retain its approved license and recorded source checksum.

Before a Theme artifact is accepted, automation must verify that the Git tag,
`style.css` header, artifact name, and release record declare the same version;
that the ZIP expands with `forge-tag/` at its root; that runtime contents match
the approved allowlist; and that the SHA-256 checksum matches the uploaded ZIP.
Rebuilding a published tag must not replace an existing artifact. Any content
change requires a new Theme version and tag.

The supported RT-314 acceptance matrix is WordPress `6.9.5` and `7.0.2`, PHP
`8.3` through `8.5`, and WooCommerce `9.9.7` and `10.9.4` with HPOS enabled.
WooCommerce-disabled acceptance is also required for the brand shell and
TagCore entry links. Stage 3A adds the source-controlled homepage Patterns,
TagCore-owned entry placement, and responsive/accessibility regression
coverage on top of the Stage 2 asset and identity checks. Stage 3B adds pinned
official product sources through privacy-safe runtime copies and derivatives,
plus image-integrity and browser regression coverage. Stage 4 adds the four
WooCommerce Block Templates, commerce regression coverage, and the
tag-triggered artifact workflow. The workflow assembles and uploads an Actions
artifact only after an approved Theme tag is pushed; Stage 4 implementation
itself creates no tag, ZIP, checksum, GitHub Release, or deployment.

Stage 5 requires the Header and Hero entry blocks to remain ordinary same-site
links with TagCore-owned progressive enhancement. Acceptance verifies the exact
desktop/mobile breakpoint, no-JavaScript and failed-Script-Module fallback,
unique dialog relationships, and canonical redirect behavior. The compatibility
matrix must also prove that WooCommerce can be disabled without removing entry,
that TagCore manual and canonical routes survive switching to Twenty Twenty-Five,
and that disabling TagCore leaves the ForgeTag brand shell renderable without
hard-coded replacement links. Finder messaging and Owner Account completion are
not implied by these entry checks.

RT-322 acceptance repeats the Stage 5 checks after a CI-equivalent permalink
initialization: set the configured permalink structure, flush rewrite rules
once during environment setup, and verify `GET /tag/activate/` and
`GET /tag/report/` before exercising Header links. Ordinary requests must not
flush rules. At 1440, 1024/816, 390, 320, and 200% equivalent zoom, record the
dialog/standalone split, initial input focus, visible invalid feedback, Escape
focus restoration, no horizontal overflow, and the privacy headers. Repeat one
standalone submission with JavaScript unavailable and confirm the canonical
`303` redirect for a normalized valid ID. These presentation checks do not
enable activation, Finder contact, email dispatch, or any Owner workflow.

The source-controlled Theme is the production design source of truth. A Site
Editor Template, Template Part, Pattern, or Global Styles change intended for
production must be exported to the Theme, reviewed in Git, validated, and
released through the approved immutable artifact. A database-only editor
customization is not a production release and must not become the sole copy of
an approved design change.

WooCommerce compatibility does not make WooCommerce an activation dependency.
The Theme's brand content must remain renderable when WooCommerce is
unavailable, and disabling commerce must not alter TagCore ownership, manual
entry, Finder routing, QR routing, or account authorization.

Theme installation and verification occur first in an isolated acceptance or
staging environment:

1. Verify the ZIP checksum and `forge-tag/` archive root.
2. Install the Theme without activating it and confirm WordPress recognizes
   its declared version and Text Domain.
3. Activate it only in the acceptance environment.
4. Verify the brand shell, Site Editor templates, keyboard navigation,
   responsive layout, and approved Theme assets.
5. Verify TagCore canonical and manual-entry routes with and without
   JavaScript, then verify WooCommerce-enabled and disabled behavior.
6. Record the commit, tag, artifact checksum, environment matrix, approver,
   activation time, and verification result.

Rollback restores the previously approved immutable Theme artifact or the
previous active Theme. It must not modify TagCore code, routes, Options,
Schema, ownership, Tag IDs, conversations, audit Events, WooCommerce data, or
other persisted product state. Any production activation or rollback requires
separate authorization.

## 6. Release record

Record at minimum:

```text
Git commit
Git tag
component (`tagcore` or `forge-tag`)
component version
schema version when the component is `tagcore`
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

### RT-326 operations-console acceptance

RT-326 keeps TagCore `0.5.0` and Schema `13`, and advances only `returntag_capability_schema_version` from `2` to `3`. Activation or an authorized `admin_init` reconciliation grants the new dispute, user-view, and audit-view capabilities to Administrators. Rollback does not remove capabilities or audit Events; the previous stable build ignores the additive capabilities and view Events. Disable `returntag_admin_sensitive_preview_enabled` before code rollback or whenever private runtime dependencies are unavailable.

Release acceptance must verify Administrator and least-privilege combinations for Tag Operator, Dispute Operator, User Support, audit viewer, and an unprivileged account. Confirm exact-anchor requirements, POST-only email lookup, duplicate-email failure, 50/100 keyset limits, old-Schema refusal, REST nonce refusal, projection allowlists, and all private response headers. Confirm the existing `/tagcore/v1/tags` contract remains unchanged. With safe development fixtures and preview explicitly enabled, verify one message reveal and one processed Review derivative reveal each append one metadata-free Event; expired, deleted, disabled, or dependency-error paths reveal nothing.

Chrome acceptance covers 1440, 1024, 816, 390, 320 and 200% equivalent layouts, keyboard order, visible focus, error announcements, responsive result tables, Object URL revocation, and console/resource errors. Production preview remains disabled unless separately authorized after external key and private-storage verification.

### RT-327 administrator Tag lifecycle acceptance

RT-327 keeps TagCore `0.5.0` and Schema `13`, and advances only
`returntag_capability_schema_version` from `3` to `4`. Activation or authorized
administrative reconciliation grants `manage_returntag_tag_lifecycle` to
Administrators. `returntag_admin_tag_lifecycle_enabled` remains disabled by
default and must not be enabled in production by the code release.

Acceptance must verify Administrator, read-only Tag Operator, lifecycle-only
Operator, and unprivileged accounts. For all four actions verify REST nonce,
capability, current Schema, exact Tag ID confirmation, submitted state/Owner
snapshot, target User uniqueness where applicable, private response headers,
committed-state reload, and one bounded audit Event. Confirm Suspend preserves
Owner, Retire is terminal, Remove Owner commits suspended without reopening
activation, and Transfer preserves active or suspended status.

Verify in the same transaction that active conversations close, queued or
in-flight messages fail, access tokens are revoked, queued or deferred
prior-Owner notifications fail, and pending transfers are cancelled. Confirm
old Owner account and secure-link requests fail immediately after ownership
change. Chrome acceptance covers 1440, 1024, 816, 390, 320, and 200% equivalent
layouts, keyboard order, modal focus containment and return, visible focus,
error announcements, and console/resource errors.

Rollback first disables `returntag_admin_tag_lifecycle_enabled`, then restores
the previous code only after verifying no action is in progress. Capability
version `4` and completed Events may remain. No Schema rollback, Tag deletion,
Owner restoration, unsuspension, or retirement reversal is performed
automatically.
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
production-ready product release because the public scan route still has no
Tag-state resolution, owner activation workflow, email-provider adapter,
finder relay, or WooCommerce business workflow.

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

RT-210 updates the project and plugin version from `0.2.0` to `0.3.0` while
leaving Schema version `8` unchanged. Release acceptance includes the
`100,000`-Tag Batch input boundary and the dedicated capacity profile recorded
in `docs/PERFORMANCE.md`.

No data conversion, table lock, index build, or Migration occurs during
deployment. Code rollback to `0.2.0` is database-compatible and preserves all
Batches, Tags, Exports, Events, capability state, and Schema records. Before
rollback, use the existing generation and Batch incident controls to contain
active work; never delete or reuse generated identifiers.

RT-301 leaves project and plugin version `0.3.0` and Schema version `8`
unchanged. Deployment must confirm that `/t/{tag_id}` resolves after one
site-scoped rewrite refresh, returns the plugin-owned `503` page independent of
the active theme, rejects mutation methods, sends the documented privacy
headers, and does not disclose the raw path segment.

RT-301 performs no data migration, Tag or Batch query, write, Event, queue,
email, WooCommerce action, or external request. Code rollback is
database-compatible: deactivate TagCore or restore compatible code and flush
rewrite rules once to remove the route. No generated identifier, Batch,
Export, ownership record, or audit evidence may be deleted during rollback.

RT-302 leaves project and plugin version `0.3.0` and Schema version `8`
unchanged. Deployment must confirm that lowercase, whitespace-formatted, and
hyphenated `GET` or `HEAD` inputs redirect once to the canonical uppercase
six-character URL; canonical and invalid inputs retain the generic `503`;
mutation methods remain `405` without a redirect; and no Tag ID is reflected
in the response body.

RT-302 performs no Migration, query, write, Event, queue, email, WooCommerce
action, external request, or asset change. Code rollback removes canonical
redirect behavior while preserving the RT-301 route and all stored data.
Disabling TagCore remains the immediate containment action. No generated
identifier or audit evidence may be deleted or reused.

RT-303 leaves project and plugin version `0.3.0` and Schema version `8`
unchanged. Deployment must verify the invalid, activation, Owner, Finder,
suspended, retired, and fail-closed service states; `301` canonicalization;
`405` mutation rejection; privacy headers; current-user Owner recognition;
Finder feature control; escaping; and exclusion of private item and identity
data.

RT-303 adds one read-only primary-key Tag/Batch query and an updated local
public stylesheet. It performs no Migration, write, Event, queue, email,
WooCommerce action, token exchange, activation, or Finder message. Code
rollback restores the RT-302 generic response and is fully compatible with
Schema version `8`. Disabling TagCore remains the immediate containment
action, and rollback must not delete or reuse any Tag ID or audit evidence.

RT-304 leaves project/plugin version `0.3.0` and Schema version `8` unchanged.
Deployment must provide three independent versioned 32-byte Base64 keys for
email encryption, lookup HMAC, and OTP pepper before enabling global
activation and email dispatch. Acceptance must verify the activation form,
same-site/nonce rejection, persistent and atomic limits, challenge-ID-only
queue arguments, Worker-memory OTP generation, at-most-once dispatch, privacy
headers, and bounded retention cleanup.

Contain an incident first with `returntag_email_dispatch_enabled=0`, then
disable global activation if necessary. Code rollback removes the form,
Worker, and cleanup hooks without a database rollback. Existing challenge rows
and rate-limit Options remain opaque and may expire; never expose, decrypt for
support, or reconstruct OTP values. Action Scheduler requires a real Cron or
WP-CLI runner and monitoring of the `returntag-activation-otp` group.

RT-305 leaves project/plugin version `0.3.0` and Schema version `8` unchanged.
Acceptance must verify exact six-digit input, unissued and expired rejection,
five-attempt lockout, atomic success, replay rejection, generic failure copy,
separate durable verification budgets, and absence of client challenge
identifiers or reflected email values.

No key, Cron, queue, or Migration change is added beyond the RT-304
requirements. Contain a verification incident with
`returntag_global_activation_enabled=0`. Code rollback removes verification
behavior while retaining attempt, verified, and consumed state plus expiring
verification-limit Options; never reset those values to resurrect a code.

RT-306 leaves project/plugin version `0.3.0` and Schema version `8` unchanged.
Deployment must verify existing-user reuse without password or role changes,
single-account creation under ReturnTag concurrency, exact rejection of
ambiguous identities, the 100-byte WordPress email boundary, metadata-free
account audit, fresh non-persistent WordPress sessions, explicit cookie
attributes, and same-site `303` return to the canonical Tag route.

Acceptance must run with both the minimum WordPress environment and the
supported WooCommerce environment. New account creation must not send a
password or marketing email, create a WooCommerce order/customer mapping, or
change a Tag. Contain an incident with
`returntag_global_activation_enabled=0`. Code rollback preserves WordPress
users, User Meta, account audit Events, consumed OTP state, and existing
sessions; never delete an account or reset a challenge to simulate rollback.

RT-307 leaves project/plugin version `0.3.0` and Schema version `8` unchanged.
Acceptance must verify one-row first ownership, same-Owner idempotency,
different-Owner non-overwrite, released and enabled Batch enforcement, global
activation containment, and transaction rollback when the activation Event
cannot be persisted.

RT-307 adds no public activation POST, Migration, index, key, dependency,
queue, email, theme, or WooCommerce change. RT-309 must add durable attempt
limits before exposing the mutation through PublicSite. Code rollback removes
the unused activation service but preserves every committed Owner assignment,
activation timestamp, active status, and audit Event. Disable
`returntag_global_activation_enabled` before rollback; never clear ownership
or delete audit evidence.

RT-308 leaves project/plugin version `0.3.0` and Schema version `8` unchanged.
Acceptance must verify Owner convergence after first activation and same-Owner
retry, Finder convergence after another Owner wins, generic invalid state for
an absent Tag, and preservation of suspended, retired, Finder-disabled, and
activation-disabled explanation states.

No public POST, Migration, index, dependency, asset, key, email, queue, theme,
or WooCommerce change is added. Code rollback removes the unused convergence
composition while preserving all committed state and Events. RT-309 must
reserve durable activation-attempt budgets before exposing this use case.

RT-309 leaves project/plugin version `0.3.0` and Schema version `8` unchanged.
Deployment requires the existing RT-304 email-lookup HMAC key because
authenticated User email and direct-peer IP are converted to keyed limiter
scopes. Acceptance must verify the exact User, email, IP, Tag, and global
limits; same-site nonce enforcement; session-derived identity; generic
throttling; atomic activation; `303` convergence; and absence of private
values in HTML, URLs, headers, logs, Events, and Options.

The existing daily Action Scheduler maintenance hook now cleans expired
activation limiter Options in bounded work. Contain an incident with
`returntag_global_activation_enabled=0`. Code rollback removes the public
activation POST and leaves opaque counters to expire; it preserves every
Owner, activation timestamp, active state, and audit Event.

RT-310 updates the project and plugin version from `0.3.0` to `0.4.0` while
leaving Schema version `8` unchanged. Deployment must verify that the static
parallel-system guide appears only on eligible Smart Tag activation pages,
remains translatable and mobile-safe, retains the existing privacy headers and
local-only Content Security Policy, and does not appear on Sticker, Classic
Tag, unavailable, Owner, Finder, suspended, retired, invalid, or fail-closed
pages.

The guide includes no remote link, SDK, account connection, pairing check,
location feature, acknowledgement write, Event, queue, email, Option,
Migration, query, dependency, theme, or WooCommerce behavior. Code rollback to
`0.3.0` removes only the static presentation and is fully compatible with
Schema version `8`. Existing Tags, Owners, activation timestamps, challenges,
rate-limit Options, and audit Events remain untouched. Disabling global
activation remains the immediate containment action for the surrounding
activation flow.

RT-312 leaves project/plugin version `0.4.0` and Schema version `8` unchanged.
The release artifact must build and include the registered dynamic-block
editor script, public Script Module, shared plugin stylesheet, block metadata,
and server-rendered entry templates. Acceptance must verify desktop dialog
enhancement, mobile full-screen navigation, no-JavaScript link fallback,
nonce and same-site enforcement, canonical `303` routing, fixed response
headers, exact manual-entry budgets, and the absence of Tag/Batch state reads
before the canonical route.

No Theme, WooCommerce dependency, Migration, product-table write, Event,
queue, email, key-management change, lock-file change, or plugin-version bump
is introduced. Code rollback unregisters the block and manual-entry routes and
removes their runtime assets. Opaque non-autoloaded limiter Options may remain
until bounded cleanup; they contain only count, expiry, and hashed scope and
are compatible with the previous stable code. Canonical `/t/{tag_id}` QR
routing, existing ownership, challenges, messages, and audit records remain
untouched.

RT-315 Stage 1 leaves project/plugin version `0.4.0` and Theme version `0.1.0`
unchanged while advancing Schema `8 -> 10`. Fresh activation creates the two
new tables; upgrade applies contiguous expand Migrations `0009` and `0010`.
Retry is idempotent, an absent Schema-9 predecessor blocks Schema 10, and the
prior stable code safely ignores the added tables. Stage 1 creates no media
object, key, route, form, queue task, email, dependency, artifact, tag,
deployment, or production write path because its repositories are not composed.

Rollback to the prior code keeps the Schema option and both new tables. Do not
drop either table or introduce a destructive down Migration. Since no intake is
registered, no feature-disable action is required for Stage 1 alone.

RT-315 Stage 2 keeps project/plugin version `0.4.0`, Theme version `0.1.0`, and
Schema version `10`. It adds no route, Hook, Option, database write, queue,
email, dependency, artifact, tag, deployment, or production composition. Code
rollback removes only unregistered processing, dormant moderation, and encrypted-storage
classes; Schema 10 and stored business data remain untouched.

Before a later stage composes intake, release configuration must provide two
independent 32-byte Base64 keys through
`RETURNTAG_TAGCORE_PRIVATE_MEDIA_OBJECT_KEY_V1` and
`RETURNTAG_TAGCORE_PRIVATE_MEDIA_REFERENCE_KEY_V1`, plus an absolute private
storage root outside all web and WordPress content roots. Release acceptance
must verify GD and Fileinfo support, JPEG/PNG/WebP decode, encrypted round-trip,
purpose binding, tamper rejection, key separation, metadata stripping, and
derivative bounds. Release evidence must confirm that no moderation provider is
called and Finder/Owner copy accurately discloses that image content is not
currently reviewed.

No Finder evidence runtime may ship until private encrypted storage,
signature/MIME and decode validation, metadata-stripping re-encoding,
controlled derivatives, accurate no-content-review disclosure, atomic abuse
budgets, bounded retention, idempotent Owner notification, and the default-off
`returntag_finder_evidence_enabled` control have passed implementation and
release acceptance. Deployment must also prove that anonymous reports are
one-way, Owner reply remains unavailable until Finder email verification, and
neither party's address or private item data appears in content, headers, URLs,
logs, Events, or media references.

Content moderation, an AWS account, Rekognition, moderation thresholds, model
versions, and provider availability are not phase-one release gates. A future
integration requires separate product, security, architecture, staging, and
rollout approval and must not be enabled by production configuration alone.

The Stage 4 notification implementation adds no Migration, dependency,
lock-file, version bump, artifact, deployment, or production configuration.
Release acceptance must verify the exact Owner subject, local inline CID JPEG,
text alternative, current-Owner resolution, absence of cross-party headers and
private identifiers, conditional `queued -> sent|failed` transitions, stale-
claim fail-closed behavior, and 30-day notified retention. Mailer acceptance is
recorded as `sent`; provider delivery remains a separate concern.

The containment order is to disable Finder evidence intake, stop new
processing and notification claims, allow already claimed Workers to converge
without duplicate delivery, and then use the existing Finder-contact or email-
dispatch controls if broader containment is required. Rollback must preserve
Conversation compatibility, audit Events, accepted messages, ownership, Tags,
and Batch history. Private evidence is removed only by the approved bounded
retention/hold process; a derivative already delivered to a mailbox cannot be
recalled.

RT-315 Stage 5 advances Schema `10 -> 11` through additive Migration `0011`
and adds no dependency or lock-file change. Release configuration must provide
three independent 32-byte Base64 keys through
`RETURNTAG_TAGCORE_FINDER_EMAIL_ENCRYPTION_KEY_V1`,
`RETURNTAG_TAGCORE_FINDER_EMAIL_LOOKUP_KEY_V1`, and
`RETURNTAG_TAGCORE_FINDER_EMAIL_OTP_PEPPER_V1`. Missing or malformed keys fail
the optional continuation closed without disabling anonymous Finder Reports.

Release acceptance must cover fresh install, Schema-10 upgrade, retry,
challenge-ID-only queue payloads, ten-minute/five-attempt OTP behavior,
same-site and nonce checks, current-Owner resolution, suspended/retired Tag
blocking, one-report/one-Conversation linkage, and absence of either email in
HTML, URLs, logs, Events, and cross-party headers. Rollback removes the Stage 5
form and Worker but preserves Schema 11, consumed challenges, Conversations,
report links, accepted reports, evidence, and audit history.

## RT-315 Stage 6 release and rollback

Stage 6 advances Schema `11 -> 12` with additive Message dispatch-claim fields.
Release configuration must provide independent 32-byte Base64 keys through
`RETURNTAG_TAGCORE_CONVERSATION_MESSAGE_KEY_V1` for Message encryption and
`RETURNTAG_TAGCORE_CONVERSATION_TOKEN_KEY_V1` for Access Token hashing.
Acceptance covers Schema-11 upgrade,
fresh install, Token-prefetch safety, explicit POST exchange, 24-hour links,
30-minute sessions, current-Owner invalidation, role and Conversation message
limits, Message-ID-only queues, encrypted bodies, one-attempt delivery
convergence, generic failures, secure response headers, and absence of either
email from cross-party headers, HTML, URLs, logs, Events, and queue arguments.

Rollback begins with Finder Contact or Email Dispatch, removes Stage 6 route
and Worker registration, and preserves Schema 12, access-token hashes,
encrypted Messages, Conversations, reports, ownership, and Events. Do not run
a destructive down Migration or automatically resend stale claims.

## RT-316 Stage 7A release and rollback

Stage 7A keeps Schema `12`, TagCore `0.4.0`, and the existing dependency and
lock files. Release acceptance covers role separation, explicit confirmation,
same-site and nonce enforcement, current-Owner revalidation, atomic terminal
status/Token/message/Event convergence, retry idempotency, cookie clearing,
terminal access denial, generic responses, and absence of cross-party or
private identifiers from UI, URLs, logs, Events, queues, and email headers.

Rollback removes the participant controls but preserves `closed` and `blocked`
Conversations, revoked Tokens, failed queued Messages, accepted Messages,
Reports, evidence, ownership, and Events. Do not reopen, restore, or requeue
terminal data. Provider calls already started cannot be recalled.

## RT-317 Stage 0 release and rollback

RT-317 Stage 0 is documentation-only. It keeps Schema `12`, TagCore `0.4.0`,
ForgeTag Theme `0.1.0`, and the existing dependency and lock files. It adds no
route, query, write, Option value, Hook, queue, email, artifact, deployment, or
production configuration.

Future Account runtime must ship with `returntag_owner_account_enabled`
default disabled and prove passwordless non-enumeration, server-derived
ownership, current-Owner query plans, field separation, mutation atomicity,
metadata-minimal Events, secure Account-to-Conversation continuation, privacy
headers, responsive accessibility, and generic transferred/unauthorized
states before enablement.

Stage 0 rollback is a documentation revert. Future runtime containment begins
by disabling `returntag_owner_account_enabled` and removing Account adapters
while preserving Tags, ownership, Lost Mode data, Smart Setup acknowledgements,
Conversations, Tokens, Messages, and Events. Transfer, Retire, Test Email,
privacy export/deletion, release, and deployment remain separately approved
work.

### RT-317 Stage 1 operational containment

Stage 1 adds Account runtime without a Schema or dependency change. The
`returntag_owner_account_enabled` Option remains absent/default-disabled after
installation and upgrade; enabling it is a separate operational decision made
only after the Account OTP secrets, Action Scheduler, transactional email, and
Schema 12 health checks are available.

Containment begins by setting `returntag_owner_account_enabled` to a canonical
disabled value. This stops Account sign-in and Owner Tag reads without changing
WordPress users, Tag ownership, activation, Finder recovery, emailed Secure
Reply links, Conversations, Messages, Tokens, or Events. Code rollback removes
the Account adapter and rewrite rules while preserving existing Schema 12
challenge retention and all business data.

### RT-317 Stage 2 operational containment

Stage 2 keeps Schema `12`, TagCore `0.4.0`, ForgeTag Theme `0.1.0`, and the
existing dependency and lock files. Release acceptance must verify Tag-bound
nonces, same-site POST checks, session-derived Owner authorization, active-only
conditional writes, bounded plain-text validation, transactionally paired
metadata-free Events, Smart Setup idempotency, and read-only suspended and
retired states.

The mutation limiter stores hashed, non-autoloaded counters under the
`returntag_owner_tag_rate_` Option prefix; cleanup is registered with the
existing TagCore cleanup path. No submitted label or Lost Message is stored in
those counters, Events, queues, or ordinary logs.

Containment begins by disabling `returntag_owner_account_enabled`, which stops
new Account mutations as well as Account reads and sign-in. Code rollback may
remove the Stage 2 forms and mutation adapters, but it must preserve accepted
Tag metadata, Lost Mode values, Smart Setup acknowledgements, ownership and
Events. No database down Migration or data repair is required.

### RT-317 Stage 3 operational containment

Stage 3 keeps Schema `12`, TagCore `0.4.0`, ForgeTag Theme `0.1.0`, and the
existing dependency and lock files. Acceptance must verify a bounded
current-Owner summary projection, absence of cross-party emails and private
relay payloads, same-site nonce POST continuation, locked current-ownership
rechecks, complete Secure Reply eligibility, prior Owner-session revocation,
and an exact 30-minute role-bound replacement session.

Containment begins by disabling `returntag_owner_account_enabled`. This stops
new Account Conversation reads and continuation POSTs, but intentionally does
not revoke or disable an existing `/secure-reply/` session. The independent
Finder contact and email dispatch controls continue to govern their existing
workflows. If relay keys are unavailable, continuation fails closed while the
privacy-minimized summaries may remain readable.

Code rollback may remove the Account Conversation route, projection, form, and
continuation adapter. It must preserve Conversations, Access Token rows,
Messages, Finder Reports, evidence, ownership, and Events; no down Migration or
data repair is required. Previously issued Secure Reply sessions retain their
normal eligibility, expiry, and revocation behavior.

## RT-325 Secure Reply accessibility release gate

RT-325 keeps Schema `12`, TagCore `0.4.0`, ForgeTag Theme `0.1.0`, and the
existing dependency and lock files. It changes only the Secure Reply adapter,
server-rendered presentation, existing public stylesheet source, contract
tests, and release documentation. It adds no route, public API, Hook, Option,
table, Migration, queue payload, email contract, artifact, deployment, or
production configuration.

Release acceptance must cover clean bearer removal, explicit POST exchange,
Owner and Finder role separation, successful and failed message feedback,
confirmed terminal actions, unavailable/expired/consumed-link convergence,
dependency failure, no-JavaScript submission, keyboard order, visible focus,
status/alert announcement semantics, and horizontal-overflow checks at 1440,
1024, 816, 390, 320, and a 200-percent equivalent viewport. Acceptance must
also confirm that neither participant email, private item name, Tag ID, Token,
Message queue state, evidence identifier, filename, or provider delivery claim
appears in HTML, headers, URLs, logs, or cross-party content.

Containment begins with `returntag_finder_contact_enabled`; disable
`returntag_email_dispatch_enabled` as well when queued outbound delivery must
stop. Code rollback may remove the RT-325 feedback and presentation refinements
but must preserve existing Conversations, encrypted Messages, Access Token
hashes, reports, evidence, ownership, Events, terminal states, and previously
issued session eligibility. No down Migration or data repair is required.

## Milestone 4 v0.5.0 release and rollback

TagCore `0.5.0` advances Schema `12 -> 13` with the additive transfer table.
Fresh install, Schema-12 upgrade, and retry verification are required before
enablement. Keep `returntag_owner_lifecycle_enabled` disabled until Schema 13,
OTP keys, Action Scheduler, and WP Mail SMTP operational configuration are
healthy. Tests intercept `pre_wp_mail`; they do not use production SMTP.

Containment disables Owner Lifecycle first, then Email Dispatch if queued mail
must stop. Code rollback to `0.4.0` leaves Schema 13 in place. Do not drop the
table, reverse accepted transfers, reopen conversations, restore Tokens, or
reactivate retired Tags.

## RT-328 Finder Report decisions

RT-328 keeps TagCore `0.5.0`, advances Schema `13` to `14`, and advances
`returntag_capability_schema_version` from `4` to `5`. Keep
`returntag_admin_finder_report_decisions_enabled` disabled until Migration and
least-privilege role review complete. Rollback first disables the flag and
stops evidence cleanup; Schema 14 columns and Events are preserved. Do not run
an older cleanup worker while an active Hold exists.

Acceptance covers Administrator, Dispute Operator, a Finder Decision Operator
with composed read permission, and unprivileged users. Verify Nonce,
capability, old-Schema, flag, exact-confirmation, and stale-snapshot failures;
the fixed 90-day boundary; Hold-aware cleanup; release/no-action preconditions;
irreversible Block revocation; metadata-free Events; and private response
headers. Chrome review covers 1440, 1024, 816, 390, 320, and 200% equivalent
layouts, keyboard and focus behavior, modal confirmation, error announcement,
temporary Object URL revocation, and console/resource errors. Production
enablement, deployment, and Release Tag require separate approval.

## RT-329 operational governance console

RT-329 keeps TagCore `0.5.0` and Schema `14`, and advances only
`returntag_capability_schema_version` from `5` to `6`. Keep
`returntag_admin_retention_run_enabled` disabled until the fixed-role matrix,
Action Scheduler health, and retention audit path have been verified. Automatic
cleanup is independent and must remain scheduled.

Acceptance verifies all eight roles and Administrator, exact capability denial,
24-hour default/31-day maximum Audit windows, filter-bound keyset cursors,
projection and header allowlists, fixed retention task IDs, duplicate-run
refusal, Active Hold preservation, and one metadata-free request/completion
Event pair. Rollback disables manual runs first, restores code, and preserves
roles, capabilities, Schema 14, audit Events, and business records. Deployment,
production enablement, Release Tag, and version publication require separate
approval.

## RT-330 v1.0 delivery sequence

The [delivery roadmap](ROADMAP.md) is the canonical execution order, not a
release authorization. The current merged baseline remains TagCore `0.5.0` at
Schema `14`. It may advance directly to a `0.9.0` release candidate only after
the remaining P0 frontend, WooCommerce, ownership-dispute, evidence-safety, and
operational-readiness gates are accepted. Empty retrospective 0.6.0, 0.7.0,
and 0.8.0 releases are not published.

Version `1.0.0` additionally requires the complete Milestone 8 compatibility,
security, delivery, migration, rollback, backup, and recovery evidence. The
roadmap does not grant permission to create a tag, publish an artifact, enable
a production flag, or deploy. Those operations retain their separate approval
and immutable-artifact requirements.

## RT-323 Activate and Report presentation acceptance

RT-323 keeps TagCore `0.5.0`, Schema `14`, all public URLs and POST contracts,
and the existing dependency and lock files. It adds no Migration, Option,
Hook, API, queue, email behavior, artifact, deployment, or production
configuration. Release acceptance must exercise the canonical `/t/{tag_id}`
route for invalid, unavailable, unregistered, active Owner, active Finder,
suspended, and retired states.

For activation, verify the server-derived three-step progress at OTP request,
OTP verification, and explicit activation; a logged-in visitor must start at
the explicit activation action. After activation, resolve committed state and
confirm the current Owner receives `/account/tags/{tag_id}/` while another
visitor receives Finder recovery. HTML, URLs, browser history, and feedback
must not disclose email, OTP, Owner ID, or an internal challenge identifier.

For Finder recovery, verify the optional bounded message, one required private
evidence image, review-before-submit behavior, processing/safety disclosure,
and optional verified-email continuation. Confirm that no location field,
unsupported verification badge, other party email, private item name, scan
history, media reference, or internal processing state is exposed. Feature or
dependency failures must retain the existing fail-closed unavailable state.

Chrome acceptance uses `1440`, `1024`, `816`, `390`, `320`, and a
`720`-CSS-pixel 200-percent equivalent viewport. Check keyboard order, visible
focus, labels, error association and announcements, no-JavaScript submission,
Back/refresh behavior, image control layout, horizontal overflow, console
errors, and same-origin resource failures. External demo IDs are comparison
references only; they are not ForgeTag fixtures, and any optional photo,
location collection, or unsupported Owner-verification claim is intentionally
excluded.

Containment continues to use the existing activation, Finder contact, Finder
evidence, and email dispatch controls. Code rollback removes only the RT-323
presentation changes and preserves Schema 14, users, ownership, challenges,
Finder Reports, private evidence, Conversations, Messages, and Events.

## RT-321 Commerce presentation acceptance

RT-321 keeps TagCore `0.5.0`, Schema `14`, capability contract `6`, and
ForgeTag Theme `0.1.0`. It changes only source-controlled WooCommerce Shop,
Product, Cart, and Checkout presentation, responsive behavior, and associated
tests. It adds no Migration, Tag allocation, order-to-Tag mapping, Option,
feature flag, provider dependency, email behavior, artifact, or deployment.

Final CI run `32812393279` passed all fourteen required checks, including PHP
8.3 through 8.5, the supported WordPress/WooCommerce and database matrices,
complete browser E2E, and the Quality Gate. Acceptance includes WooCommerce
enabled and disabled behavior, keyboard and visible-focus checks, and no
horizontal overflow at the required responsive and 200-percent text layouts.
Rollback reverts only the Theme presentation files and preserves all Plugin,
Schema, order, Tag, ownership, and audit data.

## RT-324 Owner Account presentation acceptance

RT-324 keeps TagCore `0.5.0`, Schema `14`, all Account routes, Options, hooks,
POST contracts, dependencies, and lock files unchanged. Release acceptance
must verify that My Tags precedes the secondary email-delivery utility; active,
suspended, retired, unavailable, and empty states remain server driven; and the
Tag identity panel visibly distinguishes `Only you` from `Finder-visible`
fields without exposing either party's email, Messages, Tokens, evidence,
location, or internal processing state.

Review `/account/sign-in/`, `/account/`, `/account/tags/{tag_id}/`, and
`/account/conversations/` at `1440`, `1024`, `816`, `390`, `320`, and a
`720`-CSS-pixel 200-percent equivalent viewport. Confirm no horizontal
overflow, a stable navigation row at `320`, labelled controls, visible focus,
directional empty states, no-JavaScript form operation, and separate Transfer
and permanent Retire sections. Transferred Tag detail requests must remain
generic and suspended or retired Tags must remain read-only.

Containment and rollback remain unchanged: disable
`returntag_owner_account_enabled` or revert the RT-324 presentation files
without deleting accepted ownership, Tag data, Conversations, Messages,
Tokens, transfers, or audit Events.

## RT-331 canonical main re-certification

RT-331 records `origin/main@9400ae9` as the reproducible baseline after the
RT-320 through RT-324 sequence. The executable contract remains TagCore
`0.5.0`, Schema `14`, capability contract `6`, and ForgeTag Theme `0.1.0`.
This documentation-only re-certification adds no runtime, Migration,
dependency, Option, Hook, route, feature flag, artifact, or deployment.

Release authority is unchanged. The remaining production provider, privacy,
dispute, Recovery, WooCommerce onboarding, staging, and release-candidate gates
in the delivery roadmap must still close before `0.9.0` can be approved.

## RT-337 dark-deployment and rollback contract

RT-337 advances Schema `14 -> 15` without changing the TagCore plugin version.
The direct Resend path remains dark until staging has environment-specific send
and webhook credentials, a registered HTTPS webhook, tracking disabled, and
synthetic evidence for acceptance, delivery, delay, bounce, complaint,
duplicate, replay, out-of-order, revoked credential, and outage behavior.

Enable the delivery projection and verified webhook before business email
dispatch. The immediate kill switch is `returntag_email_dispatch_enabled`;
there is no `wp_mail()` fallback. Code rollback may retain Schema 15 because
previous stable code ignores both additive tables. Do not drop delivery or
webhook history during rollback.
