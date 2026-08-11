# ADR 0022: Owner Dashboard and tag-management contract

**Status:** Accepted

**Date:** 2026-08-10

**Scope:** RT-317 Stage 0 authenticated Owner Dashboard, safe Tag metadata
management, and Account-to-Secure-Reply entry

**Schema before/after:** `12 -> 12`

**Plugin before/after:** `0.4.0 -> 0.4.0`

## Context

Milestone 3 establishes passwordless activation and a WordPress-authenticated
Owner session. RT-315 and RT-316 establish Finder evidence, private
Conversation, Secure Reply, and participant terminal actions. The phase-one
Owner still needs one coherent Account surface for owned Tags and
Conversations without moving ownership decisions into a Theme, exposing
private item data, or treating a browser-supplied Owner identifier as
authority.

The Tags table already contains `item_name`, `public_label`, `lost_mode`,
`lost_message`, and `owner_pairing_ack_at`. Schema 12 already contains the
Conversation and Access Token contracts needed by Secure Reply. Stage 0 must
therefore freeze presentation, authorization, mutation, incident-control, and
staging boundaries before any Account route or write path is registered.

## Decision

### Information architecture and routes

TagCore owns these canonical Account surfaces:

- `/account/sign-in/` for passwordless Owner sign-in when no authenticated
  WordPress session exists;
- `/account/` for My Tags and the Account overview;
- `/account/tags/{tag_id}/` for one owned Tag detail;
- `/account/conversations/` for the current Owner's Conversation summary and
  secure continuation action.

The public Tag ID in a detail URL selects a candidate record but is never
authorization evidence. Every read and mutation derives the current WordPress
user server-side and rechecks `owner_id`. An unauthenticated, transferred,
unknown, or otherwise inaccessible candidate receives a generic response that
does not disclose Tag or Owner existence.

Account sign-in reuses the approved passwordless identity architecture but
uses an Account-specific OTP purpose and rate-limit domain. It never creates a
Tag owner, overwrites a password, or treats an email address as ownership
evidence. Request and verification responses remain non-enumerating.

### Owner-only projections

My Tags includes only Tags currently owned by the authenticated user. It may
show the Tag ID, product type, lifecycle status, private item name, public
label, Lost Mode state, and bounded presentation timestamps. Active,
suspended, and retired Tags remain distinguishable. A transferred Tag
disappears from the prior Owner's list and its previous detail URL becomes
generic unavailable.

Tag Detail may show `item_name` only to the current Owner. Finder-facing
surfaces remain limited to `public_label`, product type, and approved Lost Mode
content. Account Conversation summaries may show status and bounded activity
metadata, but not either email address, message bodies, Access Tokens, media
references, evidence filenames, or private evidence.

Suspended and retired Tags are read-only in Account. An empty Account is a
valid state and does not create or infer ownership.

### Bounded Owner mutations

Each mutation is a same-site, nonce-protected explicit POST with one closed
action. The browser never supplies `owner_id`, lifecycle status, actor role,
Event type, or authorization result. Application rechecks current ownership,
requires an `active` Tag, validates the complete value, performs one atomic
write, and requests one metadata-minimal Event.

- `item_name` is optional Owner-only plain text with a maximum of 191 Unicode
  characters.
- `public_label` is optional Finder-visible plain text with a maximum of 191
  Unicode characters.
- `lost_mode` is a canonical boolean independent of Tag status.
- `lost_message` is optional Finder-visible plain text with a maximum of 500
  Unicode characters. It rejects HTML and approved high-risk classes,
  including passwords, verification codes, financial-account identifiers,
  identity-document numbers, and complete home addresses.
- Smart Setup acknowledgement sets `owner_pairing_ack_at` once, idempotently.
  It cannot be cleared through Stage 2 and is never evidence of Apple or
  Google pairing, device state, location, battery, or account identity.

Audit Events identify only the authenticated actor, Tag target, fixed action,
fixed result, and UTC time. They do not contain item names, public labels, Lost
Messages, emails, location, device data, or submitted text.

### Account-to-Secure-Reply entry

The Conversation list is not a second message renderer. `Continue securely`
is an explicit Account POST. The browser may select a Conversation, but the
server re-resolves current active ownership and the complete Stage 6/7A
eligibility graph. Success revokes prior Owner sessions for that Conversation
and issues the existing role-bound, HttpOnly, SameSite=Strict 30-minute
`conversation_session`; it then redirects to the canonical Secure Reply page.

The WordPress Account session alone cannot read or submit Conversation
messages. GET cannot mint or exchange Secure Reply access. The 24-hour email
link contract, Finder authorization, message limits, encryption, terminal
states, and Token revocation behavior remain unchanged.

### Incident control and staged delivery

Account runtime is governed by a new site-scoped, non-autoloaded,
default-disabled option:

```text
returntag_owner_account_enabled
```

Missing, malformed, or disabled state makes Account sign-in, reads, and
mutations generically unavailable. This control is operational containment,
not authentication or authorization. It does not disable public scan,
activation, Finder reporting, emailed Secure Reply links, ownership records,
or existing Conversation sessions. The activation flag must not be reused as
an Account-management switch.

Implementation remains split into:

1. Stage 1: Account passwordless entry and read-only My Tags/Tag Detail;
2. Stage 2: private/public item metadata, Lost Mode, Lost Message, and Smart
   Setup acknowledgement mutations;
3. Stage 3: read-only Conversation summaries and the bounded Owner Secure
   Reply continuation POST.

Transfer, Retire, Test Email, privacy export/deletion, and administrative
moderation require separate contracts and runtime tickets. Transfer must
revoke previous-Owner access immediately; Retire is irreversible; neither may
reuse a Tag ID.

## Presentation and accessibility

Account is mobile-first PHP server-rendered TagCore UI using semantic HTML,
labelled controls, visible focus, translatable US English, plugin-scoped CSS,
and optional progressive enhancement. The ForgeTag Theme may provide the brand
shell and approved tokens but cannot query ownership or implement forms.

All Account surfaces send `Cache-Control: no-store`,
`Referrer-Policy: no-referrer`, and
`X-Robots-Tag: noindex, nofollow, noarchive`. They load no advertising pixel,
session replay, remote customer asset, or unnecessary third-party tracker.

## Consequences

- The Owner gains one future Account model without weakening Finder privacy or
  Secure Reply authorization.
- Existing Schema 12 columns are sufficient; Stage 0 adds no Migration,
  table, index, or persisted option value.
- A dedicated default-off control allows Account containment without disabling
  activation or Finder recovery.
- Transfer and Retire stay isolated from ordinary profile edits so their
  concurrency, revocation, replay, and irreversibility rules can be reviewed
  independently.

## Rejected alternatives

- **Theme-owned Account pages:** violates the replaceable Theme boundary and
  duplicates ownership policy.
- **Submitted Owner ID as authorization:** permits horizontal privilege
  escalation.
- **WordPress login as direct message authorization:** bypasses the existing
  role-bound Secure Reply session and terminal-state checks.
- **Activation flag reused for Account containment:** couples independent
  incident domains and could disrupt activation recovery unexpectedly.
- **Transfer and Retire mixed into metadata editing:** combines reversible
  presentation updates with high-risk ownership and lifecycle actions.

## Rollback

Stage 0 is documentation-only. It creates no route, Option value, user,
session, Token, Event, email, queue job, database row, asset, release, or
deployment. Before runtime exists, rollback is a documentation revert. Future
runtime rollback begins by disabling `returntag_owner_account_enabled` and
removing Account adapters while preserving ownership, Tags, Lost Mode data,
pairing acknowledgements, Conversations, Tokens, Messages, and audit Events.
