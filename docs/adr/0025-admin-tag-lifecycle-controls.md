# ADR 0025: Audited administrator Tag lifecycle controls

- Status: Accepted
- Date: 2026-08-17
- Ticket: RT-327

## Context

RT-326 introduced bounded, read-only operational queries. Support and security
operators still need controlled Tag suspension, permanent retirement, Owner
removal, and administrator-directed Owner transfer. These mutations affect
ownership and recovery access, so a stale browser snapshot or partial write
must never leave conversations, secure links, notifications, or invitations
usable under the previous state.

## Decision

TagCore exposes four internal, cookie-authenticated POST routes under
`/tagcore/v1/admin/tags/{tag_id}`: `suspend`, `retire`, `remove-owner`, and
`transfer-owner`. Every route requires a valid `wp_rest` nonce, current Schema,
the dedicated `manage_returntag_tag_lifecycle` capability, and an exact typed
Tag ID confirmation. The capability contract advances from `3` to `4` and the
new capability is granted only to Administrators by default.

The default-disabled `returntag_admin_tag_lifecycle_enabled` option is an
independent operational kill switch. Disabling it leaves RT-326 queries
available and makes all four writes fail closed.

The state contract is:

- Suspend changes `unregistered` or `active` to `suspended` and preserves the
  Owner.
- Retire changes any non-`retired` Tag to permanent `retired` and preserves an
  existing Owner for audit.
- Remove Owner requires an Owner on an `active` or `suspended` Tag, clears that
  Owner, and commits `suspended`; it never reopens public activation.
- Transfer Owner requires an existing exact WordPress User ID whose valid email
  resolves to exactly one User. The target must differ from the current Owner.
  `active` remains `active` and `suspended` remains `suspended`. No account,
  email, or implicit unsuspension is created.

The database adapter locks the Tag, compares the submitted status and Owner
snapshot, performs a conditional update, closes active conversations, fails
queued or in-flight relay delivery, revokes access tokens, fails queued or
deferred prior-Owner notifications, cancels pending transfers, and appends the
audit Event in one transaction. A stale snapshot, repeated request, invalid
target identity, or dependency error fails without a partial commit.

Audit Events are `tag_suspended`, `tag_retired`, `tag_owner_removed`, and
`tag_transferred`. They record the operator in the standard actor columns and
allow only before/after Tag status and internal Owner User IDs in metadata. No
email, reason text, message, token, IP address, location, or private item name is
accepted.

## Consequences

The RT-326 Tag detail gains a capability-gated Danger Zone. It shows the
committed state, revocation impact, exact confirmation, and target User ID when
required. After success it discards confirmation inputs and reloads committed
server state. Theme code remains presentation-only and receives no lifecycle
responsibility.

RT-327 introduces no DDL, dependency, public API, notification, or version
change. Schema remains `13` and TagCore remains `0.5.0`. Retire is irreversible;
Remove Owner cannot be used as a public reactivation path. Dispute decisions,
bulk actions, unsuspension, user administration, and notification mail remain
outside this ADR.
