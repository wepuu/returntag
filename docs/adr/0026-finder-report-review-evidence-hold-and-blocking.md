# ADR 0026: Finder Report review, evidence hold, and blocking

## Status

Accepted for RT-328.

## Context

RT-326 provides exact, privacy-bounded Finder Report queries and audited processed-evidence preview, but it does not let an operator preserve evidence or make a dispute decision. Ordinary retention can expire during a legitimate review, while the existing processing-time block is not an administrator decision contract.

## Decision

TagCore adds four internal Cookie-authenticated POST commands: place a 90-day evidence hold, release an active hold, resolve an active review with no action, and irreversibly block an eligible Finder Report. Each command requires the current Schema, a WordPress REST nonce, `manage_returntag_finder_report_decisions`, the default-off `returntag_admin_finder_report_decisions_enabled` control, an exact Report ID confirmation, and a submitted current-state snapshot.

Only `ready` or `notified` Reports with `ready` processed evidence are eligible. Hold duration is calculated by the server as 90 days. Release and no-action require an active hold; no-action clears the hold without changing Report state. Block changes the Report to `blocked`, applies a new 90-day hold, blocks the linked Conversation, revokes its access tokens, fails queued or in-flight messages, and fails Owner notification work that has not been sent. Already delivered mail cannot be recalled. Block has no unblock or appeal command in RT-328.

Schema 14 adds nullable `hold_until`, `hold_placed_at`, and `hold_placed_by` columns plus a cleanup-oriented index to `returntag_finder_report_media`. The three hold values are either all null or all present. Evidence cleanup excludes an active hold and resumes after release or expiry.

The existing `manage_returntag_disputes` capability remains read/preview permission. The new decision capability is independent and defaults only to Administrators. Preview of a blocked Report additionally requires both capabilities, an active hold, a ready Review derivative, and the existing sensitive-preview flag. Original evidence, Email derivatives, Finder Email, public URLs, downloads, exports, free-text reasons, user-defined durations, notifications, bulk actions, and Theme logic remain excluded.

When WooCommerce's customer-only admin redirect is active, any TagCore administration capability exempts that operator from the blanket redirect so the WordPress admin shell remains reachable. This exemption grants no page or API access: each TagCore menu, screen, and REST route continues to enforce its exact capability contract.

Successful commands append exactly one metadata-free Event: `finder_evidence_hold_placed`, `finder_evidence_hold_released`, `finder_report_review_no_action`, or `finder_report_blocked`. Events contain the operator identity, Finder Report target, result, and UTC time only.

## Consequences

Schema advances from 13 to 14 while TagCore remains 0.5.0. The migration is additive and retry-verifiable. Rollback first disables the decision flag; Schema 14 columns and audit Events remain. The previous code can ignore the additive columns, but evidence held only by RT-328 must not be cleaned by an older worker during rollback.
