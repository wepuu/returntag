# ReturnTag Repository Instructions

**File scope:** entire `returntag` repository  
**Project:** ReturnTag  
**WordPress plugin:** `tagcore`  
**Last updated:** 2026-08-04

This file defines mandatory repository-wide instructions for Codex and human contributors. Read it before planning, editing, testing, reviewing, or committing changes.

Nested `AGENTS.md` files may add stricter instructions for a subdirectory, but they must not weaken the frozen business rules, privacy rules, security rules, or release controls defined here.

---

## 1. Sources of truth

Read the relevant files before changing code:

1. `docs/PRD.md` - product scope, flows, states, acceptance criteria, and frozen business requirements.
2. `docs/adr/` - approved architectural and product decisions.
3. `docs/ARCHITECTURE.md` - system boundaries and dependency rules.
4. `docs/DATABASE.md` - schema, migrations, indexes, retention, and data ownership.
5. `docs/SECURITY.md` - authentication, authorization, privacy, abuse prevention, and incident controls.
6. `docs/RELEASE.md` - versioning, deployment, rollback, and operational procedures.
7. Automated tests - executable behavior already accepted by the project.

When sources conflict:

- Do not guess or silently choose one.
- Report the conflict with exact file references.
- Do not change a frozen requirement without an explicit PRD and ADR update.
- Do not modify product documents merely to make an implementation easier.

Do not edit `docs/PRD.md`, an ADR, or this file unless the task explicitly requests a documentation or policy change.

---

## 2. Canonical project naming

Use these names consistently:

| Item | Canonical value |
|---|---|
| Repository | `returntag` |
| Plugin display name | `TagCore` |
| Plugin directory | `plugin/tagcore` |
| Plugin bootstrap file | `plugin/tagcore/tagcore.php` |
| Plugin slug | `tagcore` |
| Text domain | `tagcore` |
| Composer package | `returntag/tagcore` |
| PHP namespace root | `ReturnTag\TagCore` |
| Database suffix prefix | `returntag_` |
| WordPress option prefix | `returntag_` |
| Action and filter prefix | `returntag_` |
| Global PHP function prefix | `returntag_` |
| Ticket prefix | `RT-` |
| Release artifact | `tagcore-v{version}.zip` |

Do not introduce the obsolete names `forgetag`, `forgetag-core`, `tag-core`, `ForgeTag\Core`, `wp_forgetag_*`, or `FT-*`.

PHP identifiers cannot contain hyphens. Use namespaces such as:

```php
namespace ReturnTag\TagCore\Domain\Tag;
```

Use plugin constants with the `RETURNTAG_TAGCORE_` prefix.

---

## 3. Frozen product invariants

The following requirements are mandatory and must not be changed without an explicit PRD and ADR update.

### 3.1 Product families

The only supported `tag_type` values are:

```text
sticker
classic_tag
smart_tag
```

Do not add aliases such as `classic`, `luggage_tag`, `smart`, or `network_tag` in persisted data.

### 3.2 Public Tag ID and activation

- Every physical tag has exactly one public six-character Tag ID.
- The public Tag ID is also the first-activation ID.
- The same ID is used for QR routing, manual entry, activation, finder scans, and support lookup.
- There is no separate Claim ID, claim secret, hidden package activation code, or pack claim flow.
- The allowed alphabet is exactly:

```text
23456789ABCDEFGHJKLMNPQRSTUVWXYZ
```

- IDs are uppercase and exactly six characters after normalization.
- Remove spaces and hyphens before validation.
- Generate IDs with a cryptographically secure random source.
- Never derive IDs from timestamps, database sequences, batch numbers, or predictable counters.
- Generated, exported, voided, suspended, or retired IDs must never be reused.
- Collision handling must retry safely against a database uniqueness constraint.

### 3.3 Manufacturing batches

- Tag IDs are created by production batch and requested quantity.
- New batches default to activation disabled.
- Large generation jobs run asynchronously in resumable chunks.
- Exported IDs are immutable.
- Re-exporting a batch returns the same IDs; it never regenerates them.
- CSV exports must be auditable by version, row count, operator, timestamp, and SHA-256 checksum.
- A batch error is handled by suspending or voiding the batch and creating a new batch, not by deleting and reusing IDs.

Canonical batch states are:

```text
draft
generating
generated
exported
released
suspended
voided
```

### 3.4 Order and logistics separation

Never create or infer any of these mappings:

```text
WooCommerce order -> Tag ID
WooCommerce order item -> Tag ID
Amazon order -> Tag ID
Shipment -> Tag ID
Tracking number -> Tag ID
```

Do not add order, shipment, or tracking identifiers to the Tag or Batch domain merely for convenience.

WooCommerce may create or locate a WordPress user and send activation guidance after an eligible order event, but it must not allocate, claim, release, suspend, or transfer a Tag ID.

### 3.5 Smart Tag parallel systems

Smart locating networks and ReturnTag QR recovery are separate systems.

Phase one must not:

- request Apple or Google login;
- read or store Apple or Google account identifiers;
- read or store device identifiers from those networks;
- read or store pairing state;
- read or store location, location history, or network battery state;
- claim that pairing has been verified by ReturnTag.

`owner_pairing_ack_at` may record only that the owner acknowledged a static setup guide. It is not proof of actual pairing.

Smart Tag QR activation and finder recovery must work independently of the smart locating network.

### 3.6 Identity and privacy relay

- Owners authenticate with passwordless email OTP in the supported flows.
- A Finder Report may notify the current Owner without Finder email
  verification only after its required evidence image passes the approved
  processing and safety controls.
- Finder email is optional for the initial one-way report. It must be verified
  before creating or opening a two-way conversation or delivering an Owner
  reply to the Finder.
- Owners must never see finder email addresses.
- Finders must never see owner email addresses.
- Do not expose the other party's address in HTML, text, headers, URLs, logs, exports, analytics, or `Reply-To` values.
- ReturnTag may process both addresses only to operate the private relay and related security controls.
- Do not claim end-to-end encryption unless the implementation and approved documentation explicitly support that claim.

### 3.7 Core commercial promise

Core activation, tag management, finder contact, and private relay functionality must not require a recurring subscription in phase one.

---

## 4. Canonical states and values

Use the documented values exactly. Do not invent near-duplicates.

### Tag status

```text
unregistered
active
suspended
retired
```

Lost Mode is independent of `tag_status` and is represented separately.

### Conversation status

```text
pending_verification
open
closed
blocked
expired
```

### Message sender role

```text
finder
owner
system
```

### Smart network descriptor

```text
none
apple_find_my
google_find_hub
other
```

The smart network descriptor is display and model metadata only. It does not represent an API integration.

### Delivery status

Use the approved delivery states documented in the PRD and database specification. Do not collapse provider acceptance into confirmed delivery.

---

## 5. Repository and plugin boundaries

All product functionality belongs in the independent WordPress plugin under:

```text
plugin/tagcore/
```

Do not place core business logic in:

- a WordPress theme or `functions.php`;
- Elementor snippets;
- page templates;
- WooCommerce email templates;
- ad hoc mu-plugins;
- a single monolithic bootstrap file.

The plugin bootstrap file should only perform minimal startup work:

- guard direct access;
- define stable constants;
- load Composer autoloading;
- register activation and deactivation hooks;
- start the application bootstrap.

Do not put domain workflows directly in `tagcore.php`.

---

## 6. Architecture and dependency rules

Use these logical layers:

```text
Domain
Application
Infrastructure
Admin
PublicSite
Account
WooCommerce
```

### 6.1 Domain

Domain code contains entities, value objects, policies, state transitions, validation, and business invariants.

Domain code must not directly depend on:

```text
$wpdb
wp_mail()
get_option()
update_option()
WC_Order
HTTP request globals
WordPress admin rendering
```

Keep pure logic independently testable.

### 6.2 Application

Application services coordinate use cases such as:

```text
GenerateBatchIds
ExportBatch
RequestOtp
VerifyOtp
ActivateTag
CreateFinderConversation
VerifyFinderEmail
ReplyToConversation
TransferTag
SuspendTag
RetireTag
```

Application services enforce authorization, idempotency, state transitions, and audit event creation through explicit interfaces.

### 6.3 Infrastructure

Infrastructure contains adapters for:

- WordPress APIs;
- `$wpdb` repositories;
- WooCommerce APIs;
- queues and scheduled tasks;
- transactional email providers;
- encryption and hashing;
- logs and metrics;
- clocks and random sources.

Keep provider-specific behavior behind interfaces where practical.

### 6.4 Admin, PublicSite, Account, and WooCommerce

These layers adapt requests and responses. They must not duplicate domain rules.

Controllers and hooks should be thin:

```text
validate request
check permission or identity
invoke application service
map result to response
```

Do not embed SQL, state machines, or email orchestration in page templates or hook callbacks.

---

## 7. PHP and WordPress implementation standards

- Minimum PHP version is the version declared by the plugin and Composer configuration; do not lower it casually.
- Use `declare(strict_types=1);` in project-owned PHP files unless a documented compatibility reason prevents it.
- Use PSR-4 under `ReturnTag\TagCore\`.
- Follow the repository PHPCS and WordPress coding standards configuration.
- Prefer typed parameters, return types, and small cohesive classes.
- Do not introduce global functions unless WordPress integration requires them; prefix all such functions with `returntag_`.
- Escape output at render time.
- Sanitize and validate input at the boundary, but preserve raw domain values only after explicit validation.
- Use WordPress internationalization functions for user-facing strings and the `tagcore` text domain.
- User-facing phase-one copy is US English and must remain translatable.
- Store timestamps in UTC and format them for display through WordPress locale and timezone APIs.
- Avoid hidden side effects in constructors.
- Do not suppress PHP errors to hide failures.
- Do not add dependencies without explaining necessity, licensing, maintenance, and bundle impact.

---

## 8. Database and migration rules

### 8.1 Table naming

Build every table name dynamically:

```php
$table = $wpdb->prefix . 'returntag_tags';
```

Never hard-code `wp_`.

Canonical phase-one tables include:

```text
returntag_batches
returntag_tags
returntag_batch_exports
returntag_auth_challenges
returntag_conversations
returntag_messages
returntag_access_tokens
returntag_events
returntag_finder_reports
returntag_finder_report_media
```

### 8.2 Migration policy

- Every schema change requires a numbered, version-controlled migration.
- Migrations must be idempotent or safely detect completed work.
- Maintain a schema version such as `returntag_schema_version`.
- Test fresh installation and upgrade from the immediately previous supported schema.
- Use forward-compatible `expand -> migrate -> contract` changes.
- Do not drop or rename data needed by the previous stable application version in the same release that stops using it.
- Do not rely on destructive production `down()` migrations.
- Long-running data migrations must be resumable, observable, and safe to retry.
- Do not execute schema changes on every normal page request.

### 8.3 Data integrity

- Use InnoDB-compatible atomic operations and transactions where required.
- Use a unique database constraint for `tag_id`.
- Tag activation must use an atomic conditional write; read-then-write alone is not sufficient.
- Important mutations must record an audit event.
- Use `$wpdb->prepare()` or a repository abstraction that safely parameterizes values.
- Do not construct SQL from untrusted identifiers or values.
- Do not physically delete generated Tag IDs to make a failed test or workflow pass.

### 8.4 Rollback compatibility

A database-related PR must document:

- schema version before and after;
- fresh-install behavior;
- upgrade behavior;
- idempotency and retry behavior;
- expected locking or batch impact;
- compatibility with the previous stable code release;
- operational rollback or feature-disable plan.

Never delete these as part of routine code rollback:

```text
generated Tag IDs
exported Tag IDs
batch export history
completed owner claims
audit events
accepted conversation messages
```

---

## 9. Authentication, authorization, and ownership

- OTP values must never be stored in plaintext.
- Store a secure hash of the OTP, a bounded expiry, attempt count, send count, and consumed state.
- OTP challenges must not rely only on WordPress Transients.
- Enforce resend, email, IP, and risk-based limits.
- Expired, consumed, revoked, or attempt-exhausted challenges must fail safely.
- Existing users must not be duplicated during passwordless login.
- Never overwrite an existing user's password as part of WooCommerce provisioning or OTP login.
- Ownership checks must occur server-side for every owner action.
- User-supplied owner IDs are not authorization evidence.
- Administrator actions require explicit capabilities and audit events.
- Sensitive actions require confirmation and should be idempotent.
- Ownership transfer must immediately remove the previous owner's access and revoke obsolete access paths or tokens.

Canonical capabilities should use the documented names, including:

```text
manage_returntag
manage_returntag_batches
manage_returntag_tags
manage_returntag_disputes
view_returntag_audit_logs
```

---

## 10. Security and privacy requirements

### 10.1 Secrets and sensitive values

Never commit, print, export, or log:

- production credentials;
- SMTP or email-provider secrets;
- encryption keys;
- plaintext OTPs;
- plaintext access tokens;
- full private message bodies in ordinary logs;
- unnecessary full email addresses;
- Apple or Google account, device, or location data.

Use environment or approved secret management for keys. Do not keep encryption keys in the same database as encrypted data.

### 10.2 Storage

- Store access-token hashes, not plaintext tokens.
- Encrypt finder email at rest.
- Use a keyed lookup value such as HMAC when equality lookup, throttling, or deduplication is required.
- Encrypt private message content at rest when the approved data design requires it.
- Keep retention and cleanup behavior explicit and testable.

### 10.3 Public input and output

- Validate and normalize every public input.
- Escape every rendered value for its output context.
- Use nonces for authenticated browser mutations.
- Use capability checks for administrative routes.
- Apply rate limits to activation, OTP, finder messages, token exchange, and dispute endpoints.
- Do not reveal whether arbitrary Tag IDs, emails, or users exist through bulk or differential responses.
- Reject unsafe HTML and scripts from messages and public labels.
- General attachments remain unsupported in phase one. The only approved
  exception is exactly one required Finder Report evidence image processed
  under the documented private-media contract; it is not a conversation
  attachment.

### 10.4 Secure links

- Generate high-entropy tokens with a cryptographically secure source.
- Store only token hashes.
- Tokens must have purpose, actor role, expiry, and revocation state.
- A GET request must not perform a destructive or one-time token consumption action.
- Email security scanners may prefetch links; require an explicit POST or equivalent confirmation before exchanging a token for a session.
- Remove sensitive tokens from the address bar as early as practical after validation.

Sensitive pages must use the approved no-cache, no-referrer, and no-index controls and must not load advertising pixels, session replay, or unnecessary third-party tracking.

### 10.5 Abuse and safety

- Initial Finder Report submission does not require email verification, but
  its required evidence image must pass validation, re-encoding, metadata
  removal, and content-safety review before owner notification.
- Finder email verification remains mandatory before two-way conversation or
  reply delivery.
- Limit optional Finder Report messages and conversation messages to their
  documented ranges.
- Owners and finders must be able to close or report conversations.
- Suspended and retired tags cannot open new conversations.
- Risk-based CAPTCHA may be used only through an approved adapter and must not become the sole protection.
- Preserve evidence required for legitimate dispute and abuse review according to the approved retention policy.

---

## 11. Email and background work

- Public requests must not block while sending transactional email.
- Persist the business action first, then enqueue email work.
- Queue handlers must be idempotent and safe to retry.
- Track provider message identifiers and delivery state through the email abstraction.
- Do not treat `wp_mail()` returning `true` as confirmed delivery.
- Keep transactional and marketing messages separate.
- Do not include the other party's private address in `From`, `Reply-To`, `CC`, `BCC`, subject, body, or links.
- Handle deferrals, hard bounces, complaints, and terminal failures explicitly.
- Do not retry terminal delivery failures indefinitely.
- Queue and cleanup jobs must expose useful operational status without logging message content or secrets.

---

## 12. WooCommerce rules

- Use WooCommerce public CRUD APIs and hooks.
- Maintain HPOS compatibility.
- Do not query or update order storage tables directly.
- The eligible completed-order workflow may only:
  - read the billing email through WooCommerce APIs;
  - locate or create the WordPress user safely;
  - leave existing passwords unchanged;
  - enqueue activation guidance;
  - record an idempotency or audit event.
- It must not generate, allocate, claim, release, suspend, transfer, or map Tag IDs.
- Repeated order events must not create duplicate users or duplicate message floods.
- Gift recipients may activate tags with their own verified email; the purchaser's order email is not proof of Tag ownership.

---

## 13. Routes, pages, and API behavior

The public scan route is conceptually:

```text
GET /t/{tag_id}
```

Route handling must normalize the ID, load the tag and batch, enforce global and batch feature controls, evaluate tag status, and render the correct public or owner experience.

Public and authenticated endpoints must explicitly define:

- request method;
- authentication requirement;
- authorization or ownership rule;
- nonce or CSRF protection when relevant;
- validation and normalization;
- rate limit;
- idempotency behavior;
- audit event behavior;
- safe error response;
- privacy-safe logging.

Do not let templates decide ownership or state transitions.

For UI changes:

- use semantic HTML;
- preserve keyboard navigation and visible focus;
- provide form labels and accessible validation messages;
- design mobile-first for QR scan flows;
- do not expose private `item_name` on finder pages;
- show only approved public fields such as `public_label`, product type, and safe Lost Mode messaging.

---

## 14. Feature flags and incident controls

Preserve these global controls:

```text
returntag_global_activation_enabled
returntag_finder_contact_enabled
returntag_email_dispatch_enabled
returntag_woocommerce_account_enabled
```

Finder evidence intake and processing adds this independent fail-closed
control, which must default disabled until the complete RT-315 media-safety
contract is implemented:

```text
returntag_finder_evidence_enabled
```

Batch activation has its own control:

```text
activation_enabled
```

Feature flags are operational safety controls, not substitutes for authorization or validation.

When adding behavior that can create external side effects, personal-data exposure, mass activation, or high email volume, identify the relevant kill switch or propose one in the design before implementation.

A suspended batch normally blocks new activation for its unregistered tags. It must not silently disable all already active owners unless the task explicitly implements a separate approved incident action.

---

## 15. Testing requirements

Do not claim a test passed unless the command was actually executed successfully in the current environment.

Use project-defined commands when available:

```text
composer validate
composer lint
composer analyse
composer test
composer test:integration
composer check
```

### 15.1 Unit tests

Unit-test pure domain behavior, including:

- Tag ID alphabet and exact length;
- normalization and validation;
- state transitions;
- permission policies;
- expiry rules;
- token and OTP decisions;
- idempotency decisions;
- message limits and public/private field rules.

### 15.2 Integration tests

Integration tests should cover:

- migrations on fresh installation;
- upgrades from the previous schema version;
- repository queries and indexes;
- atomic activation under contention;
- collision retry;
- batch generation resume and retry;
- export repeatability and checksum behavior;
- WordPress user creation and password preservation;
- WooCommerce hook idempotency and HPOS-compatible access;
- queue retries and terminal delivery state;
- token exchange that survives email-link prefetch.

### 15.3 End-to-end and security-critical flows

Prioritize end-to-end coverage for:

```text
scan -> OTP -> activation
finder evidence submit -> safe processing -> owner notification
finder optional email verification -> owner secure reply -> finder delivery
ownership transfer -> previous-owner access revoked
batch generation -> export -> release -> activation
```

Security tests must verify that owner and finder email addresses are not leaked through responses, headers, templates, logs, or URLs.

### 15.4 Regression discipline

Every bug fix requires a test that fails before the fix when practical.

Do not:

- delete or weaken tests to make CI pass;
- mark failing security tests as skipped without an approved reason;
- lower static-analysis or lint standards to hide a defect;
- replace meaningful assertions with snapshots that accept incorrect behavior.

---

## 16. Git and pull request workflow

### 16.1 Branch rules

- `main` must remain deployable.
- Never commit directly to `main`.
- Never force-push `main` or another shared protected branch.
- Use one issue, one focused branch, and one primary purpose per pull request.
- Use branch names such as:

```text
feat/RT-202-id-generator
fix/RT-401-token-expiration
hotfix/RT-901-email-disclosure
chore/RT-004-ci
refactor/RT-501-message-repository
```

- Do not mix unrelated refactors, formatting sweeps, dependency updates, and product changes.
- Do not amend or rewrite commits you did not create unless explicitly instructed.

### 16.2 Commit and push policy

By default:

- do not commit;
- do not push;
- do not open a pull request;
- do not tag a release.

Perform these actions only when the user or task explicitly requests them.

When asked to commit, stage only files in scope and use a clear Conventional Commit message, for example:

```text
feat(batch): add secure Tag ID generator
fix(auth): reject consumed OTP challenges
chore(ci): add PHP quality checks
```

Include the ticket reference when available.

### 16.3 Review expectations

Before requesting review:

- inspect `git status`;
- inspect the complete diff against the intended base branch;
- verify there are no secrets or unrelated files;
- run relevant checks;
- document database, privacy, security, and rollback impact;
- report any test that could not be run.

Already merged mistakes must be corrected through a new fix or revert pull request, not history rewriting.

---

## 17. Release and rollback rules

- Build production artifacts from an approved Git tag.
- Production deployment uses an immutable ZIP such as `tagcore-v1.0.0.zip` and its SHA-256 checksum.
- Do not deploy production with `git pull`.
- A release record must identify Git commit, Git tag, plugin version, schema version, artifact checksum, build time, and deployment time.
- Prefer disabling a risky feature flag before attempting a broad rollback.
- Code rollback is allowed only after confirming database compatibility with the previous stable version.
- Data repair should use a reviewed forward migration or repair command, not ad hoc destructive SQL.
- Never roll back by deleting generated IDs, export records, owner claims, audit events, or accepted messages.

The plugin ZIP must contain `tagcore/` at its root, not the outer `returntag/` repository.

---

## 18. Required task workflow for Codex

### Before editing

1. Read this file and the relevant source-of-truth documents.
2. Inspect the current branch, repository status, and existing implementation.
3. Restate the requested outcome and exclusions.
4. Identify affected layers, data, permissions, privacy, migrations, queues, and feature flags.
5. List assumptions, ambiguities, and material risks.
6. Produce a file-level implementation and test plan when the task is non-trivial.
7. If the request is plan-only, do not edit files.

Do not ask a question when the repository and documents already answer it. If a material ambiguity remains and work can proceed safely, choose the narrowest reversible interpretation and state it.

### During implementation

- Keep the diff minimal and ticket-scoped.
- Reuse existing abstractions and patterns before creating new ones.
- Do not refactor unrelated code.
- Do not silently change public APIs, database values, option names, hooks, or event names.
- Update tests with behavior changes.
- Add or update documentation when a public contract, migration, security control, operational procedure, or approved architecture changes.
- Preserve backward compatibility unless the task explicitly authorizes a breaking change.
- Stop and report before executing destructive commands or actions outside the repository scope.

### After implementation

1. Run formatting and lint checks.
2. Run static analysis.
3. Run relevant unit tests.
4. Run relevant integration or end-to-end tests.
5. Review the final diff against the base branch.
6. Check for secrets, PII leakage, unsafe logs, and unrelated changes.
7. Report:
   - files changed;
   - behavior implemented;
   - migrations or data impact;
   - security and privacy impact;
   - commands executed;
   - test results;
   - unresolved risks or follow-up work.

Do not say "all tests pass" if only a subset was run.

---

## 19. Change-specific completion requirements

### Database change

Must include:

- numbered migration;
- schema version update;
- fresh-install test;
- previous-version upgrade test;
- retry and idempotency explanation;
- previous-release compatibility analysis;
- rollback or feature-disable plan.

### Public route or form change

Must include:

- request validation;
- CSRF or nonce decision;
- authentication and authorization decision;
- rate limit;
- privacy-safe error behavior;
- output escaping;
- abuse cases;
- automated tests.

### Email change

Must include:

- queue and idempotency behavior;
- sender and recipient privacy review;
- no private email in headers or content;
- retry and terminal failure behavior;
- safe test fixtures;
- provider-independent tests where practical.

### WooCommerce change

Must include:

- HPOS-compatible API usage;
- repeated-hook test;
- no Tag ID allocation or mapping;
- no password overwrite;
- no direct order-table query.

### Ownership or token change

Must include:

- server-side authorization;
- expiry and revocation behavior;
- previous-owner access test when relevant;
- token hashing and log review;
- concurrency or replay test.

### UI change

Must include:

- mobile scan-flow review;
- keyboard and label checks;
- private/public field review;
- translatable strings;
- screenshots or reproducible verification steps when requested by the PR template.

---

## 20. Forbidden actions

Never do any of the following unless an explicitly approved emergency procedure says otherwise:

- connect to or modify production;
- use production credentials or real user messages in development;
- run destructive SQL against shared data;
- delete generated or exported Tag IDs;
- reuse voided, suspended, or retired Tag IDs;
- add a separate Claim ID;
- map an order, shipment, or tracking number to a Tag ID;
- add Apple or Google account, pairing, device, or location integration in phase one;
- expose owner or finder email addresses to each other;
- store plaintext OTPs or access tokens;
- consume one-time tokens through a GET request;
- hard-code the `wp_` database prefix;
- directly query WooCommerce order storage tables;
- put business logic in a theme or template;
- disable tests or security controls merely to make CI pass;
- commit secrets, generated production exports, or personal data;
- commit, push, merge, tag, deploy, or force-push without explicit authorization;
- claim a command or test succeeded when it was not run successfully.

---

## 21. Default completion report

Use this structure at the end of an implementation task:

```text
Summary
- What changed and why.

Files changed
- path: purpose

Behavior and contracts
- User-visible behavior
- Public API, hook, option, or schema impact

Security and privacy
- Authentication and authorization
- PII, tokens, logging, and abuse controls

Database and rollback
- Migration and schema version
- Compatibility and rollback/disable plan

Validation
- command: result

Remaining risks
- Known limitations or follow-up items
```

Keep the report factual. Distinguish completed work, unexecuted checks, assumptions, and recommendations.
