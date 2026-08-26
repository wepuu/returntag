# ReturnTag v1.0 Delivery Roadmap

**Status:** Approved implementation path re-certified by RT-331 and aligned by RT-332

**Runtime implementation baseline:** canonical `main` commit `7593711`,
TagCore `0.5.0`, Schema `14`, ForgeTag Theme `0.1.0`

## 1. Purpose and authority

This document is the canonical delivery sequence from the current engineering
baseline to the ReturnTag and ForgeTag phase-one release. It records progress,
dependencies, and release gates; it does not replace product or security
requirements.

The authority order remains:

1. [PRD](PRD.md) for product behavior and frozen requirements;
2. accepted [ADRs](adr/) for approved decisions;
3. [Architecture](ARCHITECTURE.md), [Database](DATABASE.md), and
   [Security](SECURITY.md) for implementation boundaries;
4. [Release](RELEASE.md) for release, rollback, artifact, and deployment controls;
5. this roadmap for execution order and progress reporting.

If this roadmap conflicts with a source above it, stop and resolve the conflict
through the PRD and ADR process. A roadmap status change cannot weaken a frozen
requirement.

## 2. Status vocabulary

Every work item uses exactly one delivery status:

| Status | Meaning |
|---|---|
| `PLANNED` | Scope and dependency are recorded, but implementation has not started. |
| `IN_PROGRESS` | A scoped Issue or local branch has active work that is not merged. |
| `BLOCKED` | A recorded external decision, account, credential, or predecessor prevents implementation or acceptance. |
| `MERGED` | The scoped change is on `main` and its required CI completed successfully. |
| `ACCEPTED` | The merged behavior also passed its required functional, privacy, accessibility, and actual-page acceptance. |
| `RELEASE_READY` | External runtime dependencies, immutable artifacts, staging verification, and rollback evidence are complete. |

Code that exists only in a dirty worktree is `IN_PROGRESS`, not `MERGED`.
Documentation, unit tests, or a successful CI run do not by themselves make a
user-visible flow `ACCEPTED`. A production feature flag remaining disabled is
not a defect when the required external dependency has not passed its release
gate.

## 3. Evidence-backed current baseline

| Area | Status | Current evidence and remaining boundary |
|---|---|---|
| Milestone 0: engineering | `ACCEPTED` | Repository, Composer, tests, CI, artifacts, flags, and logging are on `main`. |
| Milestone 1: persistence | `ACCEPTED` | Numbered migrations and repository boundaries are on `main`; Schema has since advanced to `14`. |
| Milestone 2: manufacturing | `ACCEPTED` | Batch creation, secure Tag ID generation, audited export, lifecycle controls, search, and capacity coverage are on `main`. |
| Milestone 3: activation | `ACCEPTED` | Canonical scan, OTP, passwordless Session, atomic activation, convergence, limits, and Smart Tag static guidance are on `main`. |
| Milestone 4: Owner | `ACCEPTED` | Owner services and RT-324 Account presentation are on `main`; production enablement remains a separate release gate. |
| Milestone 5: Finder relay | `IN_PROGRESS` | RT-323 public presentation and the private Relay runtime are accepted. ADR 0028 defers content moderation; RT-337 delivery-bridge implementation is in PR #100 and still requires staging acceptance. |
| Milestone 6: WooCommerce | `PLANNED` | RT-321 commerce presentation is accepted, but TagCore has no Completed Order onboarding runtime. |
| Milestone 7: operations | `IN_PROGRESS` | RT-326 through RT-329 provide query, lifecycle, Finder Report decisions, roles, Audit, and Retention. The complete ownership-dispute workflow remains open. |
| Milestone 8: release readiness | `IN_PROGRESS` | CI and artifact automation exist; production dependencies, full compatibility, security, rollback, and release-candidate acceptance remain open. |

The merged operations baseline is TagCore `0.5.0`, Schema `14`, and capability
contract version `6`. This describes implementation state only. It is not a
published Milestone 7 release and does not authorize production enablement.

## 4. Phase A: documentation baseline and unfinished-work protection

### [RT-330](https://github.com/wepuu/returntag/issues/82) - v1.0 roadmap reconciliation

Status: `MERGED`

RT-330 establishes this roadmap and aligns status statements in the related
documents. It changes no runtime, Schema, dependency, Option, feature-flag
value, Theme behavior, artifact, or production configuration.

Before any unfinished frontend branch is rebased or rewritten:

1. verify its worktree path, branch, base commit, tracked diff, and untracked
   files;
2. make a lossless recovery copy outside the repository or an equivalent
   binary-safe patch plus checksum manifest;
3. keep the original worktree unchanged until the recovered branch is
   verified;
4. exclude user-provided `docs/design/dashboard.png` and
   `docs/design/html/` reference material from ticket commits unless that
   ticket explicitly documents a reviewed reference addition;
5. port only ticket-scoped files to the latest `main`, then run the ticket's
   checks before requesting Commit, Push, or PR authorization.

No unfinished frontend work may be discarded merely because its original base
predates RT-325 through RT-329.

### Recovery inventory recorded by RT-330

This repository record intentionally omits machine-specific absolute paths.
The original worktrees remain present and unchanged at the time of this audit.

| Work item | Branch and current head | Recovery facts |
|---|---|---|
| [RT-319](https://github.com/wepuu/returntag/issues/65) | Recovered independently and merged as `ec60d38` | The audit, page-state matrix, findings, and privacy-reviewed Chrome evidence are now on `main`; user reference assets remain unchanged. |
| [RT-320](https://github.com/wepuu/returntag/issues/66) | Merged as `61e6cbf` through PR #85 | Global shell, metadata, Search, and 404 acceptance are on canonical `main`. |
| [RT-321](https://github.com/wepuu/returntag/issues/67) | Merged as `9400ae9` through PR #89 | Commerce presentation passed the complete compatibility, accessibility, E2E, and Quality Gate matrix. Stale Draft PR #68 was closed as superseded. |
| [RT-322](https://github.com/wepuu/returntag/issues/69) | Merged as `4bbfe2c` through PR #86 | Desktop dialog, mobile entry, no-JavaScript fallback, and canonical redirect acceptance are on `main`. |
| [RT-323](https://github.com/wepuu/returntag/issues/70) | Merged as `f9f6adf` through PR #87 | Public Tag state, activation, Finder, privacy, and actual-page acceptance are on `main`. |
| [RT-324](https://github.com/wepuu/returntag/issues/71) | Merged as `446a71d` through PR #88 | Owner Account presentation and authorization regression acceptance are on `main`. |

The original RT-330 counts were recovery fingerprints, not estimates of
completion. The table now records their canonical merge evidence; historical
worktrees must not be treated as a newer implementation source than `main`.

## 5. Phase B: consumer frontend closure

Existing Issues remain authoritative and must not be replaced with duplicate
work items.

| Order | Work item | Status | Exit gate |
|---|---|---|---|
| 1 | [RT-319](https://github.com/wepuu/returntag/issues/65) actual-page audit and visual contract | `ACCEPTED` | Audit report, page-state matrix, P0/P1/P2 findings, privacy-reviewed durable Chrome evidence, and reproduction steps are merged. |
| 2 | [RT-320](https://github.com/wepuu/returntag/issues/66) global shell, metadata, Search, and 404 | `ACCEPTED` | Consumer brand, metadata, fallback templates, responsive shell, and production-safe copy passed Theme checks and Chrome acceptance. |
| 3 | [RT-322](https://github.com/wepuu/returntag/issues/69) Tag entry surfaces | `ACCEPTED` | Desktop dialog, mobile input page, no-JavaScript fallback, focus behavior, and canonical `303` routing passed. |
| 4 | [RT-323](https://github.com/wepuu/returntag/issues/70) public Activate and Report flow | `ACCEPTED` | Canonical activation, Owner, Finder, evidence, and unavailable states passed controlled-fixture and actual-page acceptance. |
| 5 | [RT-324](https://github.com/wepuu/returntag/issues/71) Owner Account surfaces | `ACCEPTED` | Sign-in, Overview, My Tags, Tag Detail, Conversations, privacy boundaries, and responsive navigation passed. |
| 6 | [RT-321](https://github.com/wepuu/returntag/issues/67) commerce presentation | `ACCEPTED` | Shop, Product, Cart, and Checkout passed the WooCommerce, responsive, accessibility, and complete E2E matrix. |
| 7 | [RT-325](https://github.com/wepuu/returntag/issues/72) regression rerun | `MERGED` | Secure Reply remains compliant across the required states and viewports. |

Development-only ratings, sales figures, tenure statements, testimonials, and
recovery stories may remain in an explicit Demo fixture. They must be disabled
by default and excluded from the production consumer path unless their claims
and translations have approved evidence. Demo copy cannot become a runtime
authorization, business-state input, or release claim.

## 6. Phase C: canonical baseline and remaining P0 business capability

### [RT-331](https://github.com/wepuu/returntag/issues/90) - canonical main re-certification

Status: `ACCEPTED`

Re-certify `origin/main@9400ae9` after the RT-320 through RT-324 frontend
sequence. Exit requires one reproducible baseline, matching Plugin, Schema,
capability, and Theme versions, current architecture/database/release evidence,
clean full checks, and explicit exclusion of local prototype assets. RT-331
changes no runtime, Schema, dependency, Option, feature flag, artifact, or
production configuration.

### Proposed RT-344 and RT-345 - WooCommerce completed-order guidance

Status: `PLANNED`

Freeze the Completed Hook contract in ADR 0030 and implement it inside the
TagCore `WooCommerce` layer. The workflow may use WooCommerce public CRUD APIs
to read an eligible completed order's billing email, find or safely create the
WordPress user, preserve existing passwords, enqueue activation guidance, and
record durable idempotency. It must remain HPOS-compatible and must never
generate, allocate, claim, release, suspend, transfer, or map a Tag ID to an
order, item, shipment, or tracking identifier.

Exit requires repeated-hook, Gift, password-preservation, queue-idempotency,
feature-flag, HPOS, and order-separation tests. The existing
`returntag_woocommerce_account_enabled` control remains default disabled until
staging acceptance.

### Proposed RT-341 - staff-created ownership-dispute contract

Status: `PLANNED`

Freeze the phase-one ownership-dispute contract in ADR 0031 before persistence
or UI implementation. The only intake is a capability-protected staff console;
there is no public claimant portal in phase one. The ADR must define case
states, participants, retention, Hold, operator separation, exact confirmation,
concurrency, audit allowlists, and the canonical results `reject`,
`transfer_to_new_owner`, `suspend_pending_review`, and `retire_tag`.

TagCore generates the internal dispute ID and the external support system may
reference that ID. TagCore must not store the external ticket body, evidence,
URL, or free text. The design must reuse RT-327 lifecycle actions where their
preconditions match. It must not duplicate state machines or reveal either
party's email.

### Proposed RT-342 - ownership-dispute runtime

Status: `PLANNED`

Implement the approved contract through additive, numbered persistence and
internal Cookie-authenticated Admin interfaces. Every mutation requires the
current Schema, a REST nonce, a dedicated capability, a default-disabled
incident control, exact identity confirmation, and committed-state recheck.
Decisions are transactional and append privacy-safe Events. Fresh install,
previous-Schema upgrade, retry, stale-browser, rollback compatibility, Hold and
record retention, and previous-Owner revocation are release gates.

## 7. Phase D: production dependencies and 0.9.0 candidate

### [RT-332](https://github.com/wepuu/returntag/issues/92) - defer Finder image moderation

Status: `ACCEPTED`

ADR 0028 makes the current behavior explicit: Finder images retain signature
and MIME validation, bounded decode/re-encoding, metadata removal, encrypted
private storage, rate limits, retention, Hold, idempotency, privacy controls,
and the default-disabled evidence kill switch, but ForgeTag does not currently
review image content. The runtime must not depend on reviewer availability and
the UI and Owner email must disclose the limitation without describing an image
as safe, approved, moderated, scanned, or reviewed.

PR #94 is the canonical merge and CI acceptance evidence.

AWS, Rekognition, thresholds, model versions, and moderation provider
configuration are not phase-one release dependencies. A future provider is an
uncommitted post-v1 candidate requiring a separate approved PRD, ADR, ticket,
calibration policy, staging evidence, and staged rollout. It is not on the v1
critical path.

[RT-333](https://github.com/wepuu/returntag/issues/93) and
[RT-334](https://github.com/wepuu/returntag/issues/95) explicitly own the
Resend and Cloudflare Turnstile account, DNS/hostname, least-privilege
credential, environment-separation, and rotation gates. They remain `BLOCKED`
until the approved external resources exist.

### [RT-336](https://github.com/wepuu/returntag/issues/96) through RT-343 - delivery, risk, privacy, and lifecycle gates

Status: `IN_PROGRESS`

This sequence covers the Resend Message-ID spike and webhook bridge, risk-based
Turnstile adapter, versioned privacy contract and export/deletion runtime,
ownership-dispute contract/runtime, and missing high-risk transactional
notifications. Each provider integration remains behind a provider-neutral
port and a default-off containment control. Privacy, signature, idempotency,
out-of-order delivery, fail-closed behavior, and PII-safe logging are release
gates. The stable external privacy-policy identifier and provider accounts are
explicit blockers.

RT-336 is merged through PR #98 at canonical `main@7593711`. ADR 0029 applies the approved binary decision:
the WP Mail SMTP `X-Msg-ID` implementation detail does not satisfy the stable
public-interface condition, so RT-337 uses one provider-neutral direct Resend
adapter. There is no permanent dual path and no automatic `wp_mail()` fallback.

RT-333 has partial account-readiness evidence: the sending domain, DKIM, SPF,
and monitoring-mode DMARC are present, open/click tracking is not configured,
the exposed credential was revoked, and a restricted staging replacement was
created and saved outside the repository. It remains incomplete until staging
runtime injection is verified, a separate production credential exists, the
signed webhook endpoint is registered, and synthetic dispatch, webhook,
revocation, and rotation evidence pass.

[RT-339](https://github.com/wepuu/returntag/issues/97) freezes the privacy data
map and is blocked for acceptance until the approved external policy has a
stable version identifier and accountable owner.

Because deferred content moderation did not change Schema 14, the fixed
additive migration sequence is:

```text
Schema 15 - email deliveries and webhook events
Schema 16 - privacy requests
Schema 17 - ownership disputes
```

RT-337 is `IN_PROGRESS` in [PR #100](https://github.com/wepuu/returntag/pull/100),
based directly on canonical `main@7593711`. The candidate repository now
contains the additive Schema 15 contract, provider-neutral gateway, direct
Resend adapter, environment-only configuration, signed webhook boundary,
event deduplication, out-of-order convergence, and pending-event worker. This
is implementation evidence only: acceptance remains blocked until the PR
merges, staging secrets are injected, the HTTPS webhook is registered, and the
synthetic delivery/failure matrix is recorded. No production enablement is
authorized.

No empty moderation migration is reserved. Each migration still requires fresh
install, previous-Schema upgrade, idempotent retry, previous-code compatibility,
and a feature-disable or rollback plan.

### Proposed RT-346 and RT-347 - Confirmed Recovery and history

Status: `PLANNED`

Freeze the Recovery persistence and concurrency contract, then implement the
current-Owner-only confirmation, required Finder Report or Conversation link,
atomic Lost Mode and Conversation closure, token/session revocation, and
privacy-minimized Owner/Admin history. No new Finder completion email is added.

### Proposed RT-348 - TagCore 0.9.0 release candidate

Status: `BLOCKED`

After all P0 work is merged and accepted, advance TagCore directly from
`0.5.0` to `0.9.0`; do not publish empty retrospective 0.6.0, 0.7.0, or 0.8.0
releases. ForgeTag Theme remains independently versioned. Build immutable
Plugin and Theme artifacts from approved tags, verify archive roots and
SHA-256 checksums, install them in staging, and begin a frozen release-candidate
window. Creating tags, publishing artifacts, and deploying remain separately
authorized operations.

## 8. Phase E: Milestone 8 and v1.0

### Proposed RT-349 - v1.0 release acceptance

Status: `PLANNED`

The final gate includes:

- activation contention and committed-state convergence;
- 100,000-Tag Batch and 1,000,000-Event query capacity;
- real staging email acceptance, deferral, bounce, complaint, and terminal
  failure behavior;
- Chrome at 1440, 1024, 816, 390, 320, and 200-percent equivalent zoom, plus
  keyboard, visible focus, announcements, no-JavaScript paths, overflow, and
  console or resource errors;
- supported PHP, WordPress, and WooCommerce HPOS compatibility and upgrade
  matrices;
- fresh installation, previous-Schema upgrade, idempotent migration retry,
  previous-code compatibility, and plugin rollback;
- backup restoration and post-restore critical-flow verification;
- security review of authentication, authorization, upload processing,
  privacy relay, administrative mutations, logs, headers, queues, and secrets;
- immutable artifact, checksum, release record, containment, and
  post-deployment runbook evidence.

The 1.0.0 candidate may proceed only with no open P0 product defect, no open
Critical or High security finding, all required staging dependencies healthy,
and a successful rollback and recovery rehearsal. Release Tag, production
deployment, and production feature-flag enablement each require explicit
authorization.

## 9. P1 backlog after v1.0

The PRD P1 backlog is intentionally excluded from the P0 release path:

- QR SVG and PNG manufacturing packages;
- rapid sequential and Sticker multi-activation;
- Spanish Finder pages;
- Trusted Contact and family sharing;
- Batch channel reporting;
- expanded support tickets and print-quality inspection;
- enterprise bulk asset management.

A P1 item must not enter the release branch unless it fixes a verified P0
regression or receives an explicit scope and release decision.

## 10. Reporting and change control

Each work item uses one Issue, one focused branch, and one primary PR. Update
this roadmap only when evidence changes a status or an approved decision changes
the sequence. Every update must link the relevant Issue or PR and preserve the
distinction between code merge, actual-page acceptance, operational readiness,
and production release.

RT-332 is the latest accepted work item and removes image content moderation
from the v1 critical path. RT-336 is the active engineering spike. The external-
service gates are RT-333 for Resend and RT-334 for
Cloudflare Turnstile; they remain `BLOCKED` until those resources exist.
RT-339 is also assigned but remains blocked for acceptance by the versioned
privacy policy. Contract-first P0 work may proceed in the dependency order
above; unassigned Proposed numbers remain placeholders until their
single-purpose Issues are created.
