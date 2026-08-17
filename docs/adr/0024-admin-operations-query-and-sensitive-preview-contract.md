# ADR 0024: Admin operations query and sensitive preview contract

- Status: Accepted
- Date: 2026-08-13
- Ticket: RT-326

## Context

TagCore support staff need bounded operational visibility into Tags, Finder Reports, and WordPress users. Broad database browsing, private-message exposure, and original evidence access would expand privacy and abuse risk beyond the phase-one support requirement.

## Decision

TagCore provides three capability-separated WordPress Admin surfaces and Cookie-authenticated internal REST routes. Every collection query requires one exact anchor. Email anchors are accepted only in POST bodies and are resolved to one WordPress User ID before Tag queries or cursor creation. An email matching more than one WordPress user fails closed and requires a User ID.

The capability contract advances from version 2 to 3. Administrators receive `manage_returntag_disputes`, `view_returntag_users`, and `view_returntag_audit_logs` in addition to the existing capabilities. Sites may later compose narrower roles without changing the route contract.

Query projections are allowlists. Tag results exclude private item names, Lost messages, location and scan history. Finder Report results exclude message ciphertext, Finder email, object references, source filenames, source evidence, Email derivatives and tokens. User results exclude password, session, OTP, order and address data.

Sensitive preview is controlled independently by the default-off `returntag_admin_sensitive_preview_enabled` option. A permitted dispute operator may explicitly POST to reveal an optional Finder message or the retained `ready` Review derivative only while the report is non-terminal and non-expired. Blocked and expired reports fail closed. The original and Email derivative are never available. Each successful reveal appends exactly one metadata-free Event identifying the operator, Finder Report, event type and time. Failures never append a successful-view Event.

All RT-326 routes require a current Schema, a valid WordPress REST nonce, and their route capability. Responses use `Cache-Control: no-store, private`, `Pragma: no-cache`, `Referrer-Policy: no-referrer`, and `X-Content-Type-Options: nosniff`. Evidence bytes are served without a filename or public URL; the browser creates a temporary Object URL and revokes it on navigation or replacement.

Pagination is keyset-based, defaults to 50, and is capped at 100. Criteria-bound HMAC cursors contain no email address. RT-326 is read-only except for metadata-free preview audit Events.

## Consequences

- Schema remains 13 and TagCore remains 0.5.0.
- No dependency, public API, Theme, business-state, user-role mutation, or evidence-retention change is introduced.
- Suspending or retiring Tags, ownership transfers, report adjudication, user mutation, and operational role configuration remain follow-up work.
- Disabling the sensitive-preview option immediately removes message and image access while metadata queries continue.
