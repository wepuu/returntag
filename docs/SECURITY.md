# ReturnTag Security and Privacy Baseline

**Status:** Security baseline plus RT-007 flags, RT-008 logging, RT-108 Schema controls, and RT-109 persistence boundaries

## 1. Purpose

This document defines mandatory security and privacy controls for ReturnTag.
RT-007 implements the fail-closed read boundary for global incident controls,
and RT-008 implements a default-disabled sanitized operational logging
boundary. Authentication, encryption, rate limiting, email, audited flag
mutation, and complete incident tooling remain future work.

## 2. Security objectives

ReturnTag must protect tag ownership, private contact information, secure-link
access, manufacturing exports, and administrative actions while allowing an
anonymous finder to begin a privacy-preserving recovery flow.

The design assumes that the public six-character Tag ID is visible on the
physical product and is also used for first activation. It is not a secret.
Compensating controls therefore include disabled-by-default batch activation,
email verification, rate limiting, atomic activation, export auditing, dispute
handling, and operational kill switches.

## 3. Sensitive data

Never commit, print, export, or write to ordinary logs:

- production credentials or encryption keys;
- plaintext OTPs or access tokens;
- unnecessary full owner or finder email addresses;
- complete private message bodies;
- manufacturer production exports;
- Apple or Google account, device, pairing, battery, or location data.

Secrets belong in approved environment or secret-management facilities.
Encryption keys must not be stored in the same database as encrypted content.

## 4. Authentication and challenges

Passwordless email OTP flows must use a dedicated challenge record, not
only a WordPress Transient. Store a secure code hash, bounded expiry, attempt
count, send count, consumption state, purpose, and privacy-safe rate-limit keys.

Expired, consumed, revoked, or attempt-exhausted challenges fail safely.
Existing users are reused rather than duplicated, and their passwords are never
overwritten by OTP or WooCommerce account provisioning.

Access tokens and secure-link tokens require high entropy, purpose and actor
binding, expiration, revocation, and hash-only storage. A GET request must not
consume or execute a one-time action because email security scanners may
prefetch links. Token exchange requires an explicit confirmation such as POST.

## 5. Authorization and ownership

- Enforce ownership server-side for every owner action.
- Never trust a submitted owner ID as proof of authorization.
- Administrative actions require explicit `returntag_` capabilities, nonces
  where applicable, confirmation for sensitive operations, and audit events.
- Ownership transfer revokes the previous owner's access and obsolete tokens.
- Controllers and templates do not implement authorization policy themselves;
  they invoke application services and map safe results.

RT-201 installs the site-scoped, non-autoloaded capability contract version
`1`. Only users with `manage_returntag_batches` can see the Batch admin page or
use the `tagcore/v1/batches` routes. The browser uses WordPress REST cookie
authentication and the `wp_rest` nonce; route permission callbacks enforce the
capability server-side. Responses are marked `no-store, private`, and failures
do not expose SQL or raw exception text.

## 6. Private finder relay

An initial one-way Finder Report may notify the current Owner without Finder
email verification only after its required evidence image passes the approved
private processing and content-safety controls. Finder email is optional for
that report. It must be verified before a canonical Conversation opens, the
Owner receives a reply action, or any Owner reply is delivered to the Finder.
Owners never see a finder email address, and finders never see an owner email
address.

The other party's address must not appear in:

```text
HTML or text responses
email From, Reply-To, CC, or BCC headers
email subject or body
URLs
logs or traces
exports or analytics
administrative screens without an approved privileged purpose
```

Finder email is encrypted at rest. A keyed lookup value may support equality
checks, throttling, and deduplication. ReturnTag may process both addresses only
to operate the relay and related safety controls. Do not claim end-to-end
encryption.

## 7. Public input and output

- Normalize, validate, and length-limit every public input.
- Escape output at render time for its specific HTML, attribute, URL, JSON, or
  email context.
- Reject unsafe HTML and scripts. General attachments remain unsupported; the
  only phase-one exception is one required Finder Report evidence image under
  the RT-315 private-media contract.
- Apply CSRF protection to browser mutations and explicit capabilities to
  administrative routes.
- Use privacy-safe, non-enumerating error messages.
- Do not expose private item names on finder pages.
- Keep user-facing copy translatable with the `tagcore` text domain.

Sensitive pages use no-store, no-referrer, and no-index controls and do not load
advertising pixels, session replay, or unnecessary third-party tracking.

## 8. Abuse prevention

Rate limits apply to activation, OTP requests and verification, Finder Report
submission, evidence processing, finder message submission, secure-token
exchange, and dispute endpoints. Limits should combine per-Tag, privacy-safe
email when present, direct-peer IP, device or risk, and global signals without
treating one signal as identity proof.

Risk-based CAPTCHA may supplement but not replace server-side validation,
authorization, throttling, and atomic writes. Owners and finders must be able to
close or report conversations. Suspended and retired tags cannot create new
conversations.

## 9. Manufacturing and activation controls

- New production batches default to activation disabled.
- RT-201 accepts only bounded manufacturing metadata. It ignores submitted
  status, generated quantity, activation, actor, and timestamps; these values
  are fixed by the Application service.
- Each successful RT-201 create appends a metadata-free `batch.created` Event
  whose actor is the authenticated internal User ID and whose target is the
  numeric Batch ID.
- RT-202 generates candidates from exactly six independent `random_int()`
  selections over the canonical 32-character alphabet. It excludes `0`, `1`,
  `I`, and `O`, and rejects any random adapter output outside the requested
  bounds.
- RT-202 does not log, export, persist, reserve, retry, or expose generated
  candidates.
- RT-203 attempts insertion without a uniqueness pre-query and retries only
  numeric database error `1062`, for at most ten total candidates. Database
  messages are discarded at the wpdb boundary because they can contain SQL or
  Tag IDs. Failed candidate history is neither returned nor logged.
- Non-duplicate persistence and Batch-snapshot failures fail immediately.
  Exhaustion cannot delete, overwrite, or return an existing Tag ID.
- RT-204 starts generation only through an authenticated POST request guarded
  by `manage_returntag_batches`; WordPress cookie requests require the REST
  nonce. The endpoint accepts no requested status, checkpoint, quantity, retry,
  or Tag ID input and returns only aggregate Batch and queue status.
- Queue payloads contain only the internal Batch ID and integer progress
  counters. Generated Tag IDs, candidate history, SQL, database errors, and
  manufacturing notes are not placed in Action Scheduler arguments or Events.
- Every Tag insert and counter advance share a short transaction and locked
  Batch row. Counter drift, future checkpoints, invalid states, or failed
  conditional writes stop work without deleting or exposing committed IDs.
- RT-204 emits one metadata-free start Event with the authenticated internal
  User ID and one metadata-free completion Event with a system actor. It emits
  no per-Tag or per-chunk Event.
- RT-205 requires the same dedicated capability for its no-store progress GET
  endpoint. It exposes only committed counters, audited lifecycle times,
  normalized queue health, and action availability; it never returns Tag IDs,
  queue arguments, SQL, provider errors, or Event metadata.
- The browser polls only a visible active generation view, never faster than
  the server's three-second minimum. Queue inspection failure disables recovery
  actions until state can be verified. A missing worker exposes an idempotent
  retry without deleting or regenerating committed IDs.
- Export access is restricted and every export is audited by version, row
  count, operator, timestamp, and SHA-256 checksum.
- A leaked batch can have new activation suspended without silently disabling
  already active owners.
- Activation is an atomic conditional mutation and records conflicts without
  exposing owner information.
- Generated or exported Tag IDs are never reused after an incident or rollback.

## 10. Smart-network boundary

Phase one does not request Apple or Google login and does not read or store
account identifiers, device identifiers, pairing state, battery state, current
location, or location history. An owner setup acknowledgement is only evidence
that static guidance was acknowledged, not that ReturnTag verified pairing.

Smart finding networks and ReturnTag QR recovery remain independently usable.

## 11. Logging, monitoring, and incidents

Logs and metrics must be useful without exposing secrets or private content.
Use opaque internal identifiers, bounded metadata, and masked addresses where a
legitimate operational need exists. Audit events record actor, action, target,
result, and UTC time without copying sensitive payloads.

RT-008 recursively redacts values under sensitive keys and common email,
Bearer, and secret-assignment patterns. It bounds string length, collection
size, and nesting depth; exceptions retain only class and numeric code. The
structured adapter is disabled by default and is not registered by the plugin
bootstrap. A future operations decision must define its sink, access,
retention, monitoring, and explicit enablement before production use.

This sanitizer is a final defensive boundary, not permission to submit
plaintext secrets or private content. Ordinary operational logs do not replace
the durable, privacy-safe `returntag_events` audit records planned for later
tickets, and RT-008 emits no business event.

Preserve global incident controls for activation, finder contact, email
dispatch, and WooCommerce account provisioning, plus batch-level activation
control. Feature flags reduce impact but do not replace a code fix, security
review, or formal release rollback.

RT-007 reads the global controls as site-scoped WordPress options. Missing
options and values other than boolean `true`, integer `1`, or string `"1"` are
disabled. The adapter uses WordPress option-cache behavior, adds no static
cache, and has no environment override. It exposes no write API; until an
audited administrative control is implemented, an approved operator may use
WP-CLI to set a flag to `0` for containment and must record the operational
change outside TagCore. No current product workflow consumes the flags.

Suspected credential exposure, address disclosure, mass activation, export
leakage, or abuse requires immediate containment, evidence preservation,
privacy-safe investigation, key or token revocation where relevant, and a
reviewed remediation release.

RT-101 permits schema execution only during single-site activation, a
completed TagCore upgrade, or an admin compensation request authorized by
`activate_plugins`. A database advisory lock prevents concurrent runners, and
the version advances only after postcondition verification. Public requests do
not execute DDL. Network-wide activation is rejected until a resumable
multisite rollout is designed.

Migration reports contain version numbers only. Activation errors and admin
notices do not expose SQL, table names, database credentials, or raw exception
messages. The Migration runtime does not enable operational logging and does
not process PII, OTPs, tokens, message content, Apple/Google data, or location
data.

RT-102 stores manufacturing metadata only. Canonical codes and states use
case-sensitive ASCII columns, new batches default to activation disabled, and
the Schema verifier checks the engine, collation, columns, primary key, unique
constraint, and compound indexes before version `1` is recorded. The table has
no Claim, order, shipment, tracking, account, device, pairing, battery, or
location field. RT-102 does not accept public input or expose a read/write API.

RT-103 stores the public Tag ID, batch membership, optional owner reference,
lifecycle state, and bounded item display fields. A Tag ID is public and is not
treated as an authentication secret. `item_name` is owner-private;
`public_label` and `lost_message` are public-target fields but RT-103 exposes
no route or rendering behavior. `owner_pairing_ack_at` records only an owner's
static-guide acknowledgement, never verified pairing. The table contains no
Claim, order, shipment, tracking, Apple/Google account, device, pairing state,
battery, or location field. Migration `0002` verifies the complete predecessor
batches contract before it can advance Schema version `1` to `2`.
Schema preflight blocks incompatible existing definitions before `dbDelta()`
can rewrite them; only absent tables and missing expected indexes are eligible
for automatic creation or repair.

RT-104 stores only Batch-scoped export audit metadata: version, row count,
format, SHA-256 checksum, operator ID, and UTC creation time. It stores no CSV
body, path, Tag ID list, email, order, shipment, Claim ID, credential, or
manufacturer export. The checksum index is deliberately non-unique so a
repeated immutable export can retain a new audit record; the Batch/version pair
is unique. RT-104 exposes no file, route, download, or write API.

RT-105 defines storage for passwordless OTP, Finder email verification, and
other one-time challenges without implementing any authentication behavior.
The contract forbids plaintext email, OTP, and IP storage: email ciphertext is
an opaque binary envelope, equality/rate-limit lookups are keyed HMAC-shaped
values, and the code is represented only by a secure hash. Encryption and HMAC
keys remain outside WordPress and its database. The schema also records bounded
counters, expiry, verification, consumption, and creation times, but RT-105
does not enforce the PRD's ten-minute expiry, five-attempt limit, resend delay,
rate limits, or terminal-state decisions. No challenge value is logged or
exposed through a route, Repository, admin screen, email, or public response.

RT-106 adds only the storage boundary for privacy-preserving conversations and
messages. Finder email and message content use opaque `longblob` envelopes;
finder equality lookup uses a keyed-HMAC-shaped value. Owner email is not
stored in either table. The schema contains no plaintext address, message body,
Reply-To value, attachment, order, Claim ID, smart-network device, pairing, or
location field. Encryption and lookup keys remain outside WordPress and its
database.

Conversation status must be written explicitly by future application code;
the database does not silently promote a record to `pending_verification` or
`open`. Message delivery begins as `queued`, and provider acceptance must not be
treated as confirmed delivery. RT-106 does not notify an owner, send email,
verify finder identity, expose a secure page, process a token, log ciphertext,
or enforce retention. Those controls must be implemented and reviewed before a
production write path is enabled.

RT-107 adds only hash-based storage for future secure-link and conversation
access tokens. The table contains a unique 64-character digest, purpose, actor
role, Conversation reference, and explicit UTC expiry, exchange, revocation,
and creation times. It contains no plaintext Token, email address, IP address,
User-Agent, owner ID, order, Claim ID, device, pairing, or location field.

RT-107 does not choose a hashing implementation, generate or deliver a Token,
register a route, authenticate an actor, create a session, or consume a Token.
Future secure-link handling must keep raw Tokens out of logs and URLs as early
as practical, validate purpose and actor binding, reject expired/revoked/
exchanged Tokens, and require explicit POST or equivalent confirmation before
exchange. A GET request must remain non-consuming so email scanners cannot use
a one-time Token through prefetch.

RT-108 adds only the durable storage boundary for privacy-safe business audit
events. It records classification codes, optional numeric actor identity,
opaque target identity, result, correlation, optional metadata text, and UTC
creation time. It has no dedicated plaintext OTP, Token, email, message body,
order, Claim ID, device, pairing, or location field.

The schema cannot make arbitrary metadata safe. RT-109 rejects invalid JSON,
nested structures, unapproved or sensitive keys, full email-shaped values, and
encoded metadata larger than 4096 bytes. Values are limited to flat scalar
types, and the default policy denies all non-empty metadata until a future
ticket supplies an event-specific allowlist. Stored metadata is revalidated on
read. Correlation IDs are operational grouping values, not authentication
tokens.

Event identity is a separate default-deny boundary. Every Event write must be
approved by an event-specific actor/target/correlation policy, while a generic
guard rejects email, IP, digest/token-shaped, credential, device, session, and
location-like identifiers. Stored Event identities are checked again on read.
No production Event policy or writer is registered in RT-109.

RT-109 represents finder email and message ciphertext, keyed lookup digests,
OTP password hashes, and access-token digests with distinct value objects so
they cannot be accidentally interchanged in Repository calls. Hydration
revalidates their storage shape and rejects a plaintext six-character OTP in
the hash column. These value objects do not prove that encryption or keyed
hashing occurred; only a future approved cryptographic adapter may create them
from product input.

Repository adapters parameterize query values and use only trusted table
identifiers. Persistence and schema-inspection errors return fixed messages;
raw `$wpdb` error output is suppressed for the operation and its prior setting
is restored so SQL and bound data are not leaked. Failed or malformed
`information_schema` reads stop migration instead of being treated as a
missing table. Repository lookups do not authorize actors, verify OTPs,
exchange Tokens, or implement business state transitions. RT-109 registers no
logger bridge, route, admin screen, or production write path.

RT-110 adds security-oriented installation and upgrade acceptance. Tests prove
that a partial upgrade preserves predecessor records, a complete schema can
restore a missing version Option without DDL, ordinary public lifecycle hooks
cannot run migrations, and uninstall preserves tables, Schema state, and
records. Database compatibility jobs use synthetic values only and do not
persist CI databases or expose production credentials.

Query-plan tests execute EXPLAIN over prepared, dynamically prefixed statements.
They do not log bound ciphertext, hashes, OTPs, Tokens, email addresses, or
message content. The Query Catalog marks complete-record reads so future public
and administrative lists must use explicit privacy-reviewed projections.

RT-206 treats a complete Batch Tag ID list as sensitive manufacturing
operations data even though each six-character identifier is public when
attached to a physical Tag. Access requires `manage_returntag_batches`, current
Schema state, REST cookie authentication and nonce handling, and no-store
responses. The endpoint returns only `tag_id`, canonical `tag_status`, and UTC
`created_at`; it excludes owner IDs, private item names, public labels, Lost
Mode content, scan times, emails, orders, Claim IDs, tokens, device data, and
location data.

The opaque cursor is pagination state only. It grants no authority, contains no
PII or secret, and is strictly versioned and validated before reaching the
reader. Invalid cursors receive a fixed validation response, and persistence
failures retain the existing privacy-safe generic error without SQL or bound
values. RT-206 performs no logging and introduces no external side effect or
new kill switch; disabling TagCore removes the read surface.

RT-207 treats the complete CSV as the same sensitive manufacturing operations
data. Export creation and history require `manage_returntag_batches`, current
Schema state, WordPress REST cookie authentication and nonce handling, and
`no-store, private`. Download responses also set `nosniff`, `no-referrer`, and
`noindex` controls.

The export reader selects no owner, email, item, Lost Mode, scan, order,
shipment, Claim, credential, message, device, pairing, or location field. CSV
formula prefixes in operator-controlled cells are neutralized before encoding.
The filename is derived only from the already validated Batch Code and the
server-allocated positive version.

The temporary file is outside the public product data contract, is never
stored in the database, and is removed after streaming or any failure. REST
responses and ordinary logs expose neither the temporary path nor CSV content.
SHA-256 and export history are visible only to authorized Batch operators.

First export, audit append, `generated -> exported`, and the privacy-safe
`batch_exported` Event commit atomically after integrity validation. Re-export
must reproduce the previous exact digest and row count; a site URL, Batch
snapshot, Tag snapshot, count, or formatter drift fails closed. Suspended and
voided Batches cannot issue new manufacturing files.

RT-208 lifecycle routes require current Schema state, WordPress REST cookie
authentication and nonce handling, and `manage_returntag_batches`. Commands
use a client-observed status and a conditional database update so stale
requests cannot overwrite a newer incident decision. Void also requires exact,
case-sensitive Batch Code confirmation; GET requests never change state.

Release requires complete committed inventory and a matching audited export.
The site-scoped global activation flag remains authoritative even after a
successful Batch release. Suspend and Void disable only future Batch
activation; they do not expose or change owner identity or active Tag rows.
Responses contain aggregate canonical status counts only and use
`no-store, private`.

Events contain the numeric operator and Batch identifiers with no metadata,
correlation ID, email, Tag ID list, CSV content, token, device, or location.
Failures return fixed messages without SQL, database errors, or record values.

## 12. Review requirements

Security-sensitive changes must include automated negative tests, a review of
authorization and replay/concurrency behavior, safe fixtures containing no real
personal data, a logging review, retention impact, and a clear kill switch or
rollback plan.

## 13. RT-209 Tag search controls

Tag search requires current Schema state, WordPress REST cookie authentication
and nonce handling, and `manage_returntag_tags`. It rejects unfiltered,
partial, wildcard, owner, and free-text searches. Tag ID input is normalized
before canonical validation; Batch Code matching remains exact and
case-sensitive.

Responses are `no-store, private` and exclude owner identifiers, private names,
labels, Lost Mode content, scan history, emails, tokens, messages, order or
Claim data, devices, and locations. Opaque pagination cursors are filter-bound
but are neither credentials nor authorization evidence. Failures expose no SQL
or stored private value.

Search visibility is not authorization to activate or mutate a Tag. The server
derives activation availability from trusted stored facts and the global
activation flag; the browser does not infer it. Suspended and voided IDs remain
visible to authorized operators for audit and non-reuse enforcement, while the
response exposes no owner identity. A new search clears the previous result
before waiting for the next no-store response.

## 14. RT-210 capacity controls

The `100,000`-Tag Batch limit is validated by the Application contract and
again mapped at the administrative boundary before any Batch or Event write.
The browser maximum communicates the contract but is not trusted as
enforcement. Existing capability, REST nonce, Schema-current, queue,
transaction, and incident-control checks remain unchanged.

Capacity fixtures are synthetic and contain no personal data. Performance
output is limited to aggregate counts, durations, and memory deltas; it must
not contain Tag IDs, CSV rows, SQL values, credentials, emails, messages,
tokens, device data, or location data.

The capacity limit reduces accidental resource exhaustion but does not replace
authorization, rate controls, queue monitoring, or operator review. Generation
continues asynchronously in resumable 100-Tag chunks, and export continues to
use a private temporary artifact with bounded reads.

## 15. RT-301 public route controls

The public `GET /t/{tag_id}` route is anonymous by design and performs no
authorization-sensitive action. RT-301 treats the captured path segment as
untrusted opaque input: it is not normalized, logged, queried, reflected into
HTML, placed in headers, or passed to a state transition. RT-302 must validate
the canonical six-character value before any Tag lookup.

Until state resolution exists, `GET` and `HEAD` fail closed with a generic
`503`; mutation methods receive `405` and an explicit `Allow: GET, HEAD`.
Responses send `Cache-Control: no-store, private`, `Pragma: no-cache`,
`Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, and
`X-Robots-Tag: noindex, nofollow, noarchive`. The standalone template repeats
the referrer and robots controls in HTML and loads only the local TagCore
stylesheet, with no analytics, advertising, session replay, remote font, or
third-party asset.

RT-301 exposes no existence oracle because every non-empty one-segment input
receives the same status and content. It does not notify owners, activate Tags,
create finder conversations, process email, read private fields, or append
Events. Disabling TagCore removes the route; removing its rewrite rule and
flushing once is the operational rollback.

## 16. RT-302 public Tag ID input controls

RT-302 accepts at most 64 bytes at the shared Tag ID input boundary. It removes
whitespace and hyphens, uppercases ASCII letters, and then requires exactly six
characters from `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`. Malformed UTF-8, excluded
characters, unsupported punctuation, and over-limit input fail closed.

The public adapter URL-decodes the single route segment once. Normalizable
`GET` and `HEAD` inputs redirect only to a same-site URL built from the
validated canonical value. Mutation methods do not redirect. Invalid input
does not disclose which validation rule failed and receives the same generic
`503` body and privacy headers as canonical input until RT-303 implements
state resolution.

Neither raw nor normalized Tag IDs are rendered, logged, added to headers
other than the canonical same-site `Location`, queried, persisted, or passed
to a mutation. RT-302 adds no existence oracle, authentication decision,
owner or finder notification, rate-limit storage, Event, personal data,
secret, external request, or new kill switch. Disabling TagCore remains the
operational containment and rollback control.

## 17. RT-303 public state-page controls

RT-303 performs one exact lookup only after canonical validation and a
Schema-current check. The query is parameterized and names a minimal
projection. It does not hydrate the complete Tag record. Invalid and unknown
IDs share one generic `404` page with no validation detail, raw input, or
canonical ID reflection. Persistence, mapping, missing-Batch, and stale-Schema
conditions share the generic `503` service page and expose no SQL or stored
value.

The public route necessarily distinguishes a known Tag from an unknown ID in
order to render the PRD state pages. The six-character ID is public and not an
authentication secret. Residual enumeration risk is limited by the
cryptographically random alphabet, exact one-ID route, absence of bulk or
partial search, no-index/no-store controls, and the lack of any mutation,
Owner identity, email, or private item response. RT-309 remains responsible
for stateful activation-attempt rate limiting; RT-303 does not add passive-GET
rate-limit storage.

Current WordPress identity is obtained server-side. `owner_id` is used only
for an equality decision inside Application and is not present in the public
page model, HTML, headers, URLs, or ordinary logs. Finder output is limited to
the public product type and, only when Finder contact is enabled, the approved
`public_label` and Lost Mode content. `item_name`, owner email, finder email,
Batch code, messages, tokens, orders, devices, pairing state, and locations
are neither selected nor rendered. All stored public strings are escaped at
render time.

Every response retains `Cache-Control: no-store, private`, `Pragma: no-cache`,
`Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, and
`X-Robots-Tag: noindex, nofollow, noarchive`. The standalone page loads only
the local TagCore stylesheet. `GET` and `HEAD` perform no write, notification,
Event, queue, email, or token action; mutation methods remain `405`.

## 18. RT-304 activation OTP request controls

The public request never creates OTP plaintext. It validates one bounded ASCII
email, a direct-peer IP from `REMOTE_ADDR`, same-site browser signals, and an
anonymous WordPress nonce. Forwarding headers are ignored unless a future
trusted-proxy policy is approved. Generic accepted feedback is identical for
queued and throttled requests.

Persistent challenge counts and atomic durable buckets enforce minute, hour,
and daily email limits; minute and hour IP limits; Tag budgets; and global
queue budgets. Bucket keys contain only hashes and expiry, never email or raw
IP. Lock or storage failure fails closed.

The queue stores only `challenge_id`. The Worker rechecks global activation,
email dispatch, Tag status, Batch release, and Batch activation before
generating a six-digit OTP in memory. It HMACs the code with an external
dedicated pepper and issued-domain prefix before adaptive password hashing.
The challenge is claimed before email submission, and repeats are no-ops.

Email ciphertext uses XChaCha20-Poly1305 with purpose, Tag, and version as
associated data. Email encryption, lookup HMAC, and OTP pepper use independent
versioned keys outside WordPress and its database. Missing keys fail closed.
No OTP, email, ciphertext, digest, or full Tag ID is sent to logs or Events.

The public page adds a restrictive local-only Content Security Policy and
retains no-store, no-referrer, and no-index controls. `wp_mail()` acceptance is
not treated as delivery. RT-304 performs no code verification, authentication,
account creation, ownership assignment, or activation.

## 19. RT-305 activation OTP verification controls

The verification boundary accepts only a canonical email, exactly six ASCII
digits, and the direct peer IP after anonymous nonce and same-site validation.
It re-derives keyed lookup values; the browser receives no internal challenge
ID and retains no email or code in a URL, cookie, hidden field, rendered value,
log, or Event.

Before any password-hash comparison, the locked latest challenge must be
issued, unexpired, unverified, unconsumed, and below five attempts. Wrong codes
increment the attempt count under the same lock. A match atomically consumes
the challenge while marking it verified, so concurrent requests and replays
fail.

Separate atomic minute/hour verification budgets apply to keyed email, keyed
direct-peer IP, Tag, and global scopes. IP, Tag, and global capacity is reserved
before challenge lookup; keyed-email capacity is allocated only after an
eligible latest challenge is found. The locked verification transition repeats
every eligibility predicate, so the preliminary read grants no authority and
unknown identities cannot create durable email buckets. Public responses
intentionally do not distinguish malformed input, unknown email, missing
challenge, mismatch, expiry, replay, attempt exhaustion, throttling, lock
failure, or key failure.

Successful verification is not authentication and grants no authorization.
RT-305 creates no account, session, owner assignment, Tag activation, access
token, Event, queue work, or email. Disabling global activation is the kill
switch for both OTP request and verification.

## 20. RT-306 passwordless authentication controls

The current authenticated WordPress identity is checked before OTP form
handling. An already authenticated POST cannot consume a submitted OTP or
switch the browser to another account. Anonymous email identities must be
canonical ASCII values of at most 100 bytes so a code is not issued for a
value that the supported WordPress User table cannot store.

After RT-305 verifies and consumes the code, one provisioner derives only a
keyed lock scope, acquires a short network-scoped advisory lock, repeats exact
WordPress email lookup, and fails closed for ambiguous identity data. Existing
passwords, roles, display names, and profiles are never overwritten. New users
receive only `subscriber`, an opaque random login, a high-entropy unknown
password, and no password notification.

New ReturnTag-created accounts must append or repair a metadata-free
`account_passwordless_created` Event before session issuance. The Event has a
system actor and numeric User target and contains no metadata, email, Tag ID,
IP, lookup digest, OTP, cookie, or Session identifier. Account and Event
recovery is at-least-once because WordPress user hooks cannot be rolled back
safely with the challenge transaction.

WordPress creates a fresh non-persistent session token. TagCore sends the
native cookie values with `HttpOnly` and `SameSite=Lax`; HTTPS determines
`Secure` through the WordPress security policy. No custom authentication
cookie is introduced. Success redirects with a server-constructed same-site
`303`; all activation pages remain no-store, no-referrer, no-index, and under
the restrictive local-only Content Security Policy.

Provisioning, Event, cookie, or redirect failure never resurrects an OTP,
deletes a user, assigns ownership, or changes a Tag. Disable
`returntag_global_activation_enabled` to stop new passwordless activation
authentication.

## 21. RT-307 atomic activation controls

The activation use case accepts no browser-supplied Owner identifier. The
adapter must provide the current authenticated WordPress User ID, and
Application rejects non-positive identifiers. The canonical Tag ID is the only
public business input.

The database conditional write is the ownership authority. It repeats Tag
eligibility and Batch release/activation controls in the mutation predicate;
the global activation feature flag is checked before and inside the
transaction. A zero-row write never discloses an Owner or permits replacement.
RT-308 re-resolves the committed public state using the existing privacy-safe
Owner/Finder/invalid routing.

Only a successful first write appends `tag_activated`. The Event contains the
numeric User actor, canonical Tag target, success result, and no metadata,
email, IP, OTP, lookup digest, cookie, or Session identifier. Event failure
rolls back the Tag mutation. Disable `returntag_global_activation_enabled` to
contain new activation; committed ownership and audit evidence remain intact.

## 22. RT-308 convergence privacy controls

Activation outcomes are internal control flow, not public facts. After any
successful, idempotent, unavailable, or changed-state outcome, Application
re-reads the committed privacy-minimized public projection and returns only an
existing approved page state.

The resolver compares the committed Owner ID with the server-derived current
User ID in process. It never sends an Owner ID, email, activation-race reason,
or conflict marker to the renderer. A different actor receives the normal
Finder state, while missing and blocked Tags retain generic existing
responses. No support or dispute action is inferred from a failed write.

Persistence exceptions remain fail-closed for the later PublicSite adapter.
RT-308 adds no public endpoint, token, nonce decision, rate-limit bypass,
Event, log field, email, or personal-data storage.

## 23. RT-309 activation-attempt controls

The authenticated activation POST contains only a closed action and WordPress
nonce. Same-site Fetch Metadata and Origin evidence are validated before any
business work. User ID and email are loaded server-side from the authenticated
WordPress identity; user-supplied identity fields are not accepted.

The email and direct-peer IP are transformed by the existing independent keyed
lookup protection before rate limiting. Proxy forwarding headers are ignored.
The limiter atomically checks the approved User 5/hour and 10/day, email
5/hour and 10/day, IP 30/hour and 100/day, Tag 10/hour, and global 100/minute
and 2,000/hour budgets under one site lock.

Ineligible state consumes no activation budget. Throttling, invalid nonce,
cross-site evidence, missing User email, missing keys, limiter lock/storage
failure, and unexpected Application failure use the same retryable feedback
when the activation entry remains visible. No response, URL, header, template,
Option, log, or Event exposes the limiting scope, email, IP, Owner, cookie, or
Session identifier.

Only a reserved attempt reaches the RT-307 atomic ownership transaction.
Success and committed state changes use `303` to a canonical GET. The page
retains no-store, no-referrer, no-index, CSP, output escaping, and local-only
asset controls. Disable `returntag_global_activation_enabled` to stop new OTP
and ownership work.

## 24. RT-310 Smart Tag static-guide controls

The Smart Tag guide is presentation-only and appears only after the existing
server-side resolver selects an eligible Smart Tag activation entry. Browser
input cannot select the stored product type or force the guide onto another
state.

The guide requests no Apple or Google login, loads no remote smart-network
asset or SDK, follows no device or account link, and reads or stores no account
identifier, access token, device identifier, pairing state, battery state,
location, or location history. It does not claim that ReturnTag verified
pairing or network availability.

RT-310 does not write `owner_pairing_ack_at`; that field remains reserved for
a later authenticated Owner acknowledgement use case. The guide reuses the
existing no-store, no-referrer, no-index, local-only CSP, output-escaping, and
theme-independent page controls. There is no new endpoint, nonce decision,
rate-limit scope, Event, log field, external request, or personal data.

## 25. RT-311 future theme-entry security contract

RT-311 is documentation-only and registers no block, rewrite, route, Script
Module, request handler, or runtime asset. The future implementation must keep
manual entry inside TagCore and expose it to the ForgeTag theme through the
server-rendered `tagcore/tag-entry-link` block. The block accepts only a closed
`activate` or `report` presentation intent and must not accept a Tag ID, User
ID, email, Owner identity, access token, permission, state, or arbitrary
redirect target.

TagCore must generate same-site entry URLs through WordPress APIs rather than
trusting a Theme-supplied origin or path. `GET /tag/activate/` and
`GET /tag/report/` are non-mutating display locations. The implementation
ticket must explicitly define the Tag ID submission method, normalization,
length bound, rate limits, CSRF decision, canonical `303` behavior, generic
error responses, and audit decision before either location is registered.

The selected intent is untrusted display context only. It cannot select an
Owner, authorize activation, bypass a feature flag, or override the existing
server-derived Tag state. The standalone surface must remain usable without
JavaScript; modal enhancement must fail back to the original same-site link
and must not iframe `/t/{tag_id}`.

Entry and sensitive responses must retain the approved no-store, no-referrer,
no-index, framing, local-only asset, output-escaping, and unnecessary-tracking
controls. No Tag ID, intent, identity, email, token, or state result may be
sent to advertising pixels, session replay, or unnecessary analytics. The
future implementation requires public-input, enumeration-resistance,
keyboard, focus-restoration, mobile-full-screen, and no-JavaScript tests.

## 26. RT-312 manual-entry controls

The manual-entry routes accept only `GET`, `HEAD`, and `POST`; unsupported
methods receive `405` with the approved method list. POST requires a WordPress
nonce plus same-site Fetch Metadata and Origin evidence before application
work. Only one bounded Tag ID field is accepted. Input longer than 64 bytes,
malformed input, or a value outside the canonical six-character alphabet is
rejected without querying whether a Tag, Batch, email, User, or Owner exists.

Before normalization, every POST reserves atomic direct-peer IP budgets of
30/minute and 300/hour plus global budgets of 300/minute and 5,000/hour.
Proxy-forwarding headers are ignored. The direct peer is packed and converted
to a domain-separated HMAC-SHA-256 digest with WordPress authentication salt
before it enters a non-autoloaded Option name. Values contain only count and
expiry. A site-scoped advisory lock serializes budget checks and increments;
lock or storage failure fails closed. The existing bounded daily maintenance
job inspects and removes at most 500 expired entry buckets.

Valid input receives only a `303` redirect to the canonical `/t/{tag_id}`
route. Invalid, forbidden, throttled, and unavailable outcomes use generic,
translatable form feedback and expose no Tag-existence or state distinction;
429 responses use a fixed retry interval rather than a scope-specific value.
Entry responses are no-store, no-referrer, no-index, framing-denied, and
restricted to local scripts and styles. The block accepts no identity, Tag ID,
state, token, permission, arbitrary copy, or redirect target. Desktop dialog
enhancement uses the native dialog focus model, supports Escape, restores the
exact trigger, and falls back to the same-site link. Mobile uses the standalone
full-screen route and never loads the surrounding Theme page.

## 27. RT-315 Finder evidence-report security contract

RT-315 Stage 1 adds Schema 9/10 and typed repositories. Stage 2 adds an
uncomposed media-safety kernel: bounded source bytes, strict JPEG/PNG/WebP
container checks, server MIME/decode agreement, 20-megapixel enforcement,
orientation-aware GD decode, metadata-removing JPEG re-encoding, controlled
1600-pixel and 800-pixel/200-KiB derivatives, and explicit safety approval.
There is still no upload endpoint, queue, email, or composed runtime flag.
Runtime must remain disabled until every control in this section is present and
`returntag_finder_evidence_enabled` is explicitly enabled.

The public form accepts one optional plain-text Owner message and exactly one
required JPEG, PNG, or WebP image. The boundary enforces an 8 MiB source limit,
a 20-megapixel decoded limit, actual file-signature/MIME agreement, successful
bounded decode, and a single-file cardinality before acceptance. SVG, GIF,
HEIC, PDF, audio, video, archives, extra files, HTML, scripts, and malformed or
polyglot-like input are rejected with generic responses. Client filenames and
client MIME declarations are never trusted or retained.

Accepted bytes enter encrypted non-public quarantine outside WordPress uploads
and the Media Library. Processing re-encodes decoded pixels, removes EXIF, GPS,
capture time, device data, embedded profiles not required for safe rendering,
and original filenames, then creates controlled 1600-pixel review and
800-pixel/200-KiB email derivatives. Content-safety review is mandatory and
fails closed. No Owner notification occurs after a decode, storage, scanning,
processing, timeout, or safety failure. Provider requests use only the minimum
approved derivative and must not include Tag ID, item name, email, source
filename, or unnecessary metadata.

Object storage uses authenticated encryption with keys outside WordPress and
its database. Object references are non-public and never appear in HTML, URLs,
logs, analytics, or email. Ordinary logs and Events contain only approved
classification, result, opaque internal identifiers, timings, sizes, and error
codes; they contain no image bytes, thumbnails, private messages, email,
location, Tag ID, or object credentials.

The Stage 2 filesystem adapter encrypts every source and derivative object with
XChaCha20-Poly1305 and encrypts its random opaque reference with a distinct key.
Associated data binds key version, object purpose, and random identifier.
`RETURNTAG_TAGCORE_PRIVATE_MEDIA_OBJECT_KEY_V1` and
`RETURNTAG_TAGCORE_PRIVATE_MEDIA_REFERENCE_KEY_V1` are independent external
32-byte Base64 keys; they must never be stored in an Option or the database.
The configured absolute root must resolve outside `ABSPATH`, `WP_CONTENT_DIR`,
public uploads, and every configured public root. Symlink roots, traversal,
overwrites, key reuse, purpose confusion, ciphertext modification, digest
mismatch, and public-root placement fail closed. The adapter returns no path or
URL and is not yet registered by the production bootstrap.

Content safety remains an external approval boundary. Stage 2 passes only the
metadata-free review derivative to `FinderEvidenceSafetyReviewer`. The shipped
default adapter always reports safety unavailable. Only an explicit
`approved` result creates `ApprovedFinderEvidence`; rejection, provider error,
timeout, or missing configuration produces no approval marker.

Owner notification runs asynchronously after durable state. Its queue argument
is only an internal report ID. The Worker rechecks the evidence, Finder-contact,
and email-dispatch flags; resolves the current Owner at send time; and embeds
only the processed email derivative as a local MIME CID part. It never attaches
the original or uses a remote image, public URL, access Token, original
filename, private item name, Tag ID, or cross-party address. Report ID plus
derivative version provides idempotency. Terminal failures are bounded and do
not retry forever.

The Stage 4 WordPress mail adapter explicitly clears Reply-To, CC, and BCC,
provides text and escaped HTML alternatives, and marks `sent` only when the
configured mailer accepts the message. It never equates that acceptance with
provider delivery. A 15-minute stale claim is converged to terminal `failed`
to prevent an automatic duplicate after an ambiguous Worker crash window.

The initial report is one-way. Without verified Finder email, the Owner sees no
Secure Reply control and cannot create an orphan reply. Optional Finder email
uses the existing encryption/HMAC and one-time verification requirements before
the report may be linked to an open Conversation. Email privacy remains in
force in every header, body, URL, secure page, log, Event, and administration
view.

Submission requires a documented same-site/CSRF decision, atomic per-Tag,
direct-peer IP, device/risk, and global budgets, plus risk-based CAPTCHA when
appropriate. Suspended or retired Tags, disabled Finder contact, disabled
evidence processing, unavailable safety controls, storage failure, and rate-
limit failure all stop acceptance or notification safely. Owners can report or
block a Finder Report through a future authenticated, nonce-protected action
with an audit Event.

Quarantine, rejected, and terminal unnotified artifacts expire within 24
hours. Notified evidence expires 30 days after notification unless an approved
abuse/dispute hold applies. Cleanup is bounded, retry-safe, and auditable. The
Finder consent must state that ReturnTag cannot recall a derivative already
received, cached, exported, or forwarded from the Owner's mailbox.

## 28. RT-315 Stage 5 Finder email verification

The optional continuation is available only from a completed, unexpired,
Tag-bound Finder Report submission claim. The browser receives no internal
report ID, Conversation ID, email digest, ciphertext, or queue identifier.
Same-site and nonce checks protect both request and verification POSTs, and all
failure states remain generic.

Finder email encryption, lookup HMAC, peer-IP HMAC, and OTP hashing use a
dedicated external three-key set, report-bound authenticated data, and separate
domains from activation OTP. Plaintext OTP exists only in Worker memory; the
queue contains only the challenge ID. Request budgets cover keyed email and
direct-peer IP minute/hour windows, with persistent hourly challenge counts per
email and report. Codes expire after ten minutes, allow at most five attempts,
and are consumed exactly once.

Successful verification atomically creates or reuses one `open` Conversation
and links it to the report. The current active Owner is resolved at verification
time; suspended or retired Tags cannot create a Conversation. Neither address
is rendered, logged, placed in URLs, or exposed in mail headers. This stage
does not add Secure Reply, message delivery, access Tokens, or attachments.

## 29. RT-315 Stage 6 Secure Reply and bounded relay

Owner and Finder email links carry independent 32-byte random Tokens whose
keyed digests alone are stored. A GET cannot exchange a Token. The public route
moves a structurally valid Token to an HttpOnly, SameSite=Strict transient
cookie, redirects to a clean URL, and requires an explicit nonce-protected POST
before issuing a 30-minute role-bound session. Link lifetime is 24 hours.

Owner requests re-resolve the current active Owner server-side; transferred,
suspended, retired, closed, blocked, and expired access fails closed. Finder
authorization comes only from the role-bound link delivered to the verified
Finder destination. Neither role can select an Owner, actor role, Conversation,
recipient, or email through browser input.

Human messages are plain text, 10–500 Unicode characters, limited to 10 per
role and 20 per Conversation, encrypted at rest with an independent external
key, escaped at render time, and excluded from logs and Events. Attachments and
precise-location fields are rejected. Request budgets cover session, direct
peer and Conversation scopes. Queue payloads contain Message IDs only.

The Worker creates the recipient's next role-bound link in memory, sends no
cross-party address in headers or content, and records provider acceptance as
`sent`, not delivered. A stale ambiguous claim is terminal `failed` and is not
resent. Finder Contact and Email Dispatch remain the incident controls.

## 30. RT-316 participant close and report-block controls

Finder termination and Owner report-block are explicit, same-site,
nonce-protected POST actions from an active role-bound session. Finder can only
request `closed`; the current active Owner can only request `blocked`. Neither
request accepts an identifier, role, status, reason, free text, attachment, or
recipient from the browser.

The terminal transaction rechecks current ownership and all Stage 6
eligibility, revokes every link and session, fails still-queued Messages, and
records a metadata-free Event. Responses, Events, logs, URLs, and queue data
contain no email, private item name, Tag ID, message body, Token, report reason,
or evidence filename. Both cookies are cleared after success. Closed or blocked
Conversations cannot be read, exchanged, messaged, linked, or newly claimed.

The Worker rechecks the exact claimed Message and continuation Token immediately
before delivery. An email provider call that already passed that final check
and is in progress cannot be recalled. Its result cannot restore access or the
Conversation. Stage 7A provides no moderation
outcome, evidence hold, unblock, reopen, appeal, or ownership-dispute path.

## 31. RT-317 Owner Dashboard security contract

All Account reads and writes derive the authenticated WordPress user on the
server and recheck current ownership. Browser-supplied Owner IDs are rejected;
Tag and Conversation identifiers are selectors only. Account sign-in uses an
independent passwordless OTP purpose and privacy-safe rate-limit domain,
returns generic outcomes, reuses existing users, and never creates ownership
or overwrites passwords.

Account mutations require an active Tag, same-site request, WordPress nonce,
closed action, complete value validation, atomic conditional write, and
metadata-minimal Event. `item_name` remains Owner-only. Finder-visible values
are plain text and bounded; Lost Message rejects HTML, passwords, verification
codes, financial-account identifiers, identity-document numbers, and complete
home addresses. Suspended and retired Tags remain read-only, and transfer
removes prior-Owner access immediately when a later ticket implements it.

Conversation summaries exclude both email addresses, message bodies, Tokens,
media references, evidence, and evidence filenames. A WordPress login cannot
read or submit relay messages. Only an explicit Account POST that revalidates
the current active Owner and complete Conversation state may issue the
existing role-bound 30-minute Owner session; GET cannot mint access.

`returntag_owner_account_enabled` is a default-disabled containment control,
not authorization. Missing or malformed values fail closed for Account routes
without changing public scan, activation, Finder reporting, emailed Secure
Reply links, ownership, or Conversation state. Account responses are
no-store, no-referrer, and no-index and load no advertising, session replay,
remote customer asset, or unnecessary third-party tracker.

## 32. Owner lifecycle and Test Email controls

Test Email derives its recipient from the authenticated WordPress user and
queues no email address. WP Mail SMTP may transport `wp_mail()` calls but its
detailed/content logging and tracking remain off. Mailer acceptance is not
represented as delivery.

Transfer and Retire require a fresh Account OTP, authenticated session,
same-site checks, nonces, rate limits, and active current ownership. Invitation
Tokens are 32 random bytes, hash-only at rest, moved off the URL by GET, and
consumed only by an explicit POST from the matching authenticated email.
Neither operation logs private fields, reuses a Tag ID, or exposes an email.
