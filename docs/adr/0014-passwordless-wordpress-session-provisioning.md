# ADR 0014: Passwordless WordPress session provisioning

**Status:** Accepted for RT-306

**Date:** 2026-07-30

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.3.0 -> 0.3.0`

## Context

RT-305 atomically verifies and consumes an activation OTP but deliberately
creates no browser handoff credential, WordPress user, session, ownership
relationship, or Tag mutation. RT-306 must therefore compose verification and
login in the same POST while the canonical email remains only in process
memory.

WordPress user storage is a separate identity boundary. In the supported
WordPress 7.0 environment, `wp_users.user_email` is limited to 100 characters
and its email and login indexes are not unique database constraints.
Application-level `email_exists()` followed by `wp_insert_user()` is not by
itself a sufficient concurrency boundary.

Account creation and WordPress hooks also cannot be rolled back safely as part
of the RT-305 challenge transaction. OTP consumption, account provisioning,
audit append, cookie issuance, and redirect therefore require an explicit
one-way failure model.

## Decision

An authenticated session is checked before reading or verifying a submitted
OTP. An already authenticated request does not consume the submitted code,
create a user, or switch accounts. Anonymous activation identity emails must
fit the canonical WordPress User storage contract before an OTP is requested
or verified.

For an anonymous verification POST, Application composes:

```text
verify and consume OTP
-> derive keyed email lookup in memory
-> provision or reuse one WordPress user
-> append or repair the account-created audit when applicable
-> issue a fresh WordPress session
-> redirect with 303 to the canonical Tag URL
```

The browser receives no email, OTP, challenge ID, lookup digest, handoff token,
or new custom session token.

All ReturnTag passwordless account creation uses one Infrastructure provisioner.
It acquires a short, network-scoped MySQL advisory lock derived only from the
keyed email lookup, repeats an exact WordPress email lookup inside the lock,
and fails closed if more than one exact account exists. Future WooCommerce
account provisioning must reuse this boundary.

Existing users are reused without changing passwords, roles, display names, or
profile data. New users receive the fixed `subscriber` role, an opaque random
login, a high-entropy unknown password, and a generic display name. Multisite
users are added to the current site only when they are not already members;
existing site roles are preserved.

New ReturnTag-created accounts carry a versioned source marker. Before session
issuance, a metadata-free `account_passwordless_created` Event with a system
actor and numeric User target is appended. A retry repairs a missing audit
Event for a marked account. Event append is at-least-once across a process
crash; a duplicate audit Event is preferable to an unaudited account or an
unsafe user rollback. No Event contains an email, Tag ID, IP, digest, OTP,
cookie, or session identifier.

RT-306 uses WordPress-generated authentication values and session tokens.
Core cookie emission is suppressed for this one issuance so the same native
cookie values can be sent with explicit `HttpOnly` and `SameSite=Lax`
attributes, plus WordPress-derived paths and HTTPS security decisions.
The session is non-persistent (`remember=false`). The canonical post-login
redirect is constructed server-side and uses `303`.

## Failure behavior

| Completed state | Later failure | Behavior |
|---|---|---|
| OTP consumed, no user | Provisioning or lock failure | No session; request a new OTP |
| User created, audit missing | Event failure or crash | No session; later verified retry repairs audit |
| User and audit present | Cookie failure | No session; later verified retry reuses the user |
| Session cookies issued | Redirect interrupted | Repeated request reuses the session and cannot recreate the user |

No failure path resurrects a consumed OTP or deletes a created WordPress user.

## Consequences

- Schema remains version 8 and no core WordPress index is altered.
- The public activation email limit is 100 ASCII bytes for WordPress identity
  compatibility, even though generic email values may be longer elsewhere.
- ReturnTag concurrency is serialized across sites in the same WordPress
  network. An unrelated plugin that creates users without this boundary
  remains a documented residual race; the postcondition check fails closed if
  ambiguity becomes visible.
- WordPress registration hooks can still run for a new account. Supported
  WordPress and WooCommerce configurations require integration acceptance to
  confirm that no password or marketing email is emitted.
- RT-306 performs no Tag ownership assignment, Tag status transition,
  activation Event, Finder action, WooCommerce order action, or Migration.

## Rejected alternatives

- **Expose a post-verification handoff token:** adds a reusable browser
  credential and contradicts the RT-305 no-handoff decision.
- **Rely only on `email_exists()` before insert:** permits a ReturnTag
  read-then-write race.
- **Add a unique index to `wp_users.user_email`:** modifies a WordPress core
  table and can be incompatible with existing sites and plugins.
- **Wrap `wp_insert_user()` in the challenge transaction:** WordPress hooks,
  object caches, and third-party side effects do not have reliable rollback
  semantics.
- **Delete a user after partial failure:** destructive compensation can remove
  a legitimate identity created or adopted by another request.

## Rollback

Disable `returntag_global_activation_enabled` first to stop new OTP
verification and passwordless session provisioning. Code rollback removes the
RT-306 composition and signed-in activation state while preserving WordPress
users, account source markers, audit Events, consumed challenges, and existing
WordPress sessions. Rollback must not delete users or reset consumed OTP state.
