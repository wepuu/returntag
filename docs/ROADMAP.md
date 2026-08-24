# ReturnTag v1.0 Delivery Roadmap

**Status:** Approved implementation path recorded by RT-330

**Baseline:** `origin/main` commit `ec60d38`, TagCore `0.5.0`, Schema `14`,
ForgeTag Theme `0.1.0`

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
| `Planned` | Scope and dependency are recorded, but implementation has not started. |
| `In progress` | A scoped Issue or local branch has active work that is not merged. |
| `Merged` | The scoped change is on `main` and its required CI completed successfully. |
| `Accepted` | The merged behavior also passed its required functional, privacy, accessibility, and actual-page acceptance. |
| `Release ready` | External runtime dependencies, immutable artifacts, staging verification, and rollback evidence are complete. |
| `Released` | The approved immutable artifact was deployed and post-deployment verification was recorded. |

Code that exists only in a dirty worktree is `In progress`, not `Merged`.
Documentation, unit tests, or a successful CI run do not by themselves make a
user-visible flow `Accepted`. A production feature flag remaining disabled is
not a defect when the required external dependency has not passed its release
gate.

## 3. Evidence-backed current baseline

| Area | Status | Current evidence and remaining boundary |
|---|---|---|
| Milestone 0: engineering | `Accepted` | Repository, Composer, tests, CI, artifacts, flags, and logging are on `main`. |
| Milestone 1: persistence | `Accepted` | Numbered migrations and repository boundaries are on `main`; Schema has since advanced to `14`. |
| Milestone 2: manufacturing | `Accepted` | Batch creation, secure Tag ID generation, audited export, lifecycle controls, search, and capacity coverage are on `main`. |
| Milestone 3: activation | `Accepted` | Canonical scan, OTP, passwordless Session, atomic activation, convergence, limits, and Smart Tag static guidance are on `main`. |
| Milestone 4: Owner | `In progress` | Owner Account and lifecycle services are on `main`; RT-324 consumer-page acceptance remains open. |
| Milestone 5: Finder relay | `In progress` | Evidence processing, one-way notification, optional email verification, Secure Reply, and participant safety are on `main`; RT-323 page acceptance and an approved production safety reviewer remain open. |
| Milestone 6: WooCommerce | `Planned` | Theme compatibility exists, but TagCore has no Completed Hook runtime beyond the directory contract. |
| Milestone 7: operations | `In progress` | RT-326 through RT-329 provide query, lifecycle, Finder Report decisions, roles, Audit, and Retention. The complete PRD 20.3 ownership-dispute case workflow remains open. |
| Milestone 8: release readiness | `In progress` | CI and artifact automation exist; production dependencies, full compatibility, security, backup/restore, rollback, and release-candidate acceptance remain open. |

The merged operations baseline is TagCore `0.5.0`, Schema `14`, and capability
contract version `6`. This describes implementation state only. It is not a
published Milestone 7 release and does not authorize production enablement.

## 4. Phase A: documentation baseline and unfinished-work protection

### [RT-330](https://github.com/wepuu/returntag/issues/82) - v1.0 roadmap reconciliation

Status: `Merged`

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
| [RT-320](https://github.com/wepuu/returntag/issues/66) | Recovery fingerprint remains at `9ebe744`; scoped work is ported to `feat/RT-320-global-shell-metadata-recovered` from `ec60d38` | The original worktree remains unchanged. The recovered branch contains only RT-320 Theme, tests, documentation, and privacy-reviewed QA evidence. |
| [RT-321](https://github.com/wepuu/returntag/issues/67) | `feat/RT-321-commerce-presentation` at `e125538` | Clean worktree with existing [Draft PR #68](https://github.com/wepuu/returntag/pull/68). Update it only after RT-320 is merged, then rebase or port onto the new baseline. |
| [RT-322](https://github.com/wepuu/returntag/issues/69) | `feat/RT-322-tag-entry-surfaces` at `9ebe744` | Behind `main` by five commits; 12 tracked files contain 336 insertions and 27 deletions. |
| [RT-323](https://github.com/wepuu/returntag/issues/70) | `feat/RT-323-activate-report-flow` at `9ebe744` | Behind `main` by five commits; nine tracked files contain 365 insertions and 50 deletions. |
| [RT-324](https://github.com/wepuu/returntag/issues/71) | `feat/RT-324-owner-account-surfaces` at `9ebe744` | Behind `main` by five commits; eight tracked files contain 331 insertions and 48 deletions. |

The counts above are recovery fingerprints, not estimates of completion. Before
mutation, compare the live worktree to this inventory and investigate any
difference instead of overwriting it.

## 5. Phase B: consumer frontend closure

Existing Issues remain authoritative and must not be replaced with duplicate
work items.

| Order | Work item | Status | Exit gate |
|---|---|---|---|
| 1 | [RT-319](https://github.com/wepuu/returntag/issues/65) actual-page audit and visual contract | `Accepted` | Audit report, page-state matrix, P0/P1/P2 findings, privacy-reviewed durable Chrome evidence, and reproduction steps are merged. |
| 2 | [RT-320](https://github.com/wepuu/returntag/issues/66) global shell, metadata, Search, and 404 | `In progress` | Consumer brand, metadata, fallback templates, responsive shell, and production-safe copy pass Theme checks and Chrome acceptance. |
| 3 | [RT-321](https://github.com/wepuu/returntag/issues/67) commerce presentation | `In progress` | The existing Draft PR is updated after RT-320; Shop, Product, Cart, and Checkout pass the WooCommerce and responsive matrix. |
| 4 | [RT-322](https://github.com/wepuu/returntag/issues/69) Tag entry surfaces | `In progress` | Desktop dialog, mobile input page, no-JavaScript fallback, focus behavior, and canonical `303` routing pass. |
| 5 | [RT-323](https://github.com/wepuu/returntag/issues/70) public Activate and Report flow | `In progress` | Canonical activation, Owner, Finder, evidence, and unavailable states pass controlled-fixture and actual-page acceptance. |
| 6 | [RT-324](https://github.com/wepuu/returntag/issues/71) Owner Account surfaces | `In progress` | Sign-in, Overview, My Tags, Tag Detail, Conversations, privacy boundaries, and responsive navigation pass. |
| 7 | [RT-325](https://github.com/wepuu/returntag/issues/72) regression rerun | `Merged` | Secure Reply remains compliant after RT-323 and RT-324 merge across all required states and viewports. |

Development-only ratings, sales figures, tenure statements, testimonials, and
recovery stories may remain in an explicit Demo fixture. They must be disabled
by default and excluded from the production consumer path unless their claims
and translations have approved evidence. Demo copy cannot become a runtime
authorization, business-state input, or release claim.

## 6. Phase C: remaining P0 business capability

### RT-331 - WooCommerce completed-order guidance

Status: `Planned`

Freeze the Completed Hook contract in ADR 0028 and implement it inside the
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

### RT-332 - staff-created ownership-dispute contract

Status: `Planned`

Freeze the phase-one ownership-dispute contract in ADR 0029 before persistence
or UI implementation. The only intake is a capability-protected staff console;
there is no public claimant portal in phase one. The ADR must define case
states, permitted evidence, encryption and private storage, retention, Hold,
operator separation, exact confirmation, concurrency, audit allowlists, and
the canonical results `reject`, `transfer_to_new_owner`,
`suspend_pending_review`, and `retire_tag`.

The design must reuse RT-327 lifecycle actions and RT-328 evidence custody where
their preconditions match. It must not duplicate state machines, reveal either
party's email, or accept unrestricted files or free-form operational logging.

### RT-333 - ownership-dispute runtime

Status: `Planned`

Implement the approved contract through additive, numbered persistence and
internal Cookie-authenticated Admin interfaces. Every mutation requires the
current Schema, a REST nonce, a dedicated capability, a default-disabled
incident control, exact identity confirmation, and committed-state recheck.
Decisions are transactional and append privacy-safe Events. Fresh install,
previous-Schema upgrade, retry, stale-browser, rollback compatibility, evidence
retention, and previous-Owner revocation are release gates.

## 7. Phase D: production dependencies and 0.9.0 candidate

### RT-334 - production Finder evidence safety adapter

Status: `Planned`

Select and integrate an approved safety reviewer behind the existing
`FinderEvidenceSafetyReviewer` interface. Provider unavailable, timeout,
malformed response, or uncertain result must fail closed. Provider credentials,
original evidence, storage paths, and raw responses must not enter the
repository, ordinary logs, Events, URLs, or public responses.

### RT-335 - operational readiness

Status: `Planned`

In an isolated staging environment, verify external key injection, private
media storage, WP Mail SMTP transport, sender-domain authentication, Action
Scheduler with real Cron or WP-CLI, queue monitoring, retention schedules, and
the documented feature-flag enablement and containment order. Tests that
intercept `wp_mail()` do not satisfy this gate. No production credential is
stored in Git or copied into acceptance evidence.

### RT-336 - TagCore 0.9.0 release candidate

Status: `Planned`

After all P0 work is merged and accepted, advance TagCore directly from
`0.5.0` to `0.9.0`; do not publish empty retrospective 0.6.0, 0.7.0, or 0.8.0
releases. ForgeTag Theme remains independently versioned. Build immutable
Plugin and Theme artifacts from approved tags, verify archive roots and
SHA-256 checksums, install them in staging, and begin a frozen release-candidate
window. Creating tags, publishing artifacts, and deploying remain separately
authorized operations.

## 8. Phase E: Milestone 8 and v1.0

### RT-337 - v1.0 release acceptance

Status: `Planned`

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

The current active work item is
[RT-320](https://github.com/wepuu/returntag/issues/66). After RT-320 is merged,
the next implementation work is RT-321 through RT-324 in the order above.
