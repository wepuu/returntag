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

Future passwordless email OTP flows must use a dedicated challenge record, not
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

Finder email must be verified before the owner is notified. Owners never see a
finder email address, and finders never see an owner email address.

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
- Reject unsafe HTML and scripts; phase one does not support attachments.
- Apply CSRF protection to browser mutations and explicit capabilities to
  administrative routes.
- Use privacy-safe, non-enumerating error messages.
- Do not expose private item names on finder pages.
- Keep user-facing copy translatable with the `tagcore` text domain.

Sensitive pages use no-store, no-referrer, and no-index controls and do not load
advertising pixels, session replay, or unnecessary third-party tracking.

## 8. Abuse prevention

Rate limits apply to activation, OTP requests and verification, finder message
submission, secure-token exchange, and dispute endpoints. Limits should combine
privacy-safe email, IP, device or risk signals without treating one signal as
identity proof.

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

## 12. Review requirements

Security-sensitive changes must include automated negative tests, a review of
authorization and replay/concurrency behavior, safe fixtures containing no real
personal data, a logging review, retention impact, and a clear kill switch or
rollback plan.
