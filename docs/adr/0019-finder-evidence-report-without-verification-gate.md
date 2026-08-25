# ADR 0019: Finder evidence report without an email-verification gate

**Status:** Accepted

> **Partial supersession:** ADR 0028 supersedes this ADR's content-moderation
> and reviewer-availability gates. Its private-media, technical-processing,
> one-way notification, privacy, retention, and feature-control requirements
> remain accepted.

**Date:** 2026-08-04

**Scope:** Phase-one Finder Report intake, evidence processing, Owner alert,
and optional promotion to a verified private conversation

**Schema before/after:** `8 -> 8` (documentation stage only)

**Plugin before/after:** `0.4.0 -> 0.4.0`

## Context

The existing phase-one contract models every Finder contact as a Conversation:
the Finder supplies an email and message, verifies the email, and only then is
the Owner notified. It also rejects every Finder image or attachment. The
approved product flow now prioritizes a fast, credible first report. The Finder
must provide visual evidence that the item is in their possession, but should
not have to verify an email address merely to alert the Owner.

Forcing an anonymous or email-optional report into
`returntag_conversations` would weaken the meaning of its required encrypted
Finder email fields and canonical `pending_verification` state. Sending an
unprocessed upload to the Owner would also expose metadata, unsafe content,
and uncontrolled files through transactional email.

## Decision

### Separate report and conversation models

ReturnTag will introduce a one-way Finder Report model separate from the
canonical Conversation model. The initial Finder Report accepts:

- `Message for the owner`: optional; when present, 10 to 500 characters;
- `Item photo`: required; exactly one evidence image;
- Finder email: optional and not an initial-notification gate.

Submitting a report does not create a Conversation and does not give the Owner
a reply path. If the Finder later supplies and verifies an email address, an
Application service may create or link a canonical Conversation. The existing
Conversation states remain exactly `pending_verification`, `open`, `closed`,
`blocked`, and `expired`; Finder Report or media states must not be added to
that enum.

### Evidence boundary

The Item photo is evidence supplied by the Finder, not an Owner profile image
and not a Conversation attachment. The public boundary accepts one JPEG, PNG,
or WebP image no larger than 8 MiB and 20 megapixels. It rejects SVG, GIF,
HEIC, PDF, video, audio, additional images, and files whose signature, MIME,
or decoded content disagree.

Accepted bytes enter encrypted, non-public quarantine outside the WordPress
Media Library and public uploads tree. Infrastructure must decode and re-encode
the image, remove EXIF, GPS, capture time, device data, original filename, and
other metadata, and produce controlled derivatives. The review derivative has
a maximum 1600-pixel edge. The email derivative has a maximum 800-pixel edge
and target size of 200 KiB. Neither derivative may use a public media URL.

Content-safety review is fail-closed. A report is eligible for notification
only after the image is `ready`. Decode failure, unsupported content, safety
rejection, processing timeout, unavailable safety controls, or storage failure
must prevent Owner notification. The system must never replace a rejected
required image with an empty or text-only alert.

### Owner notification

After durable report and evidence state are committed, an idempotent Worker
resolves the current Owner at send time and rechecks the relevant feature
controls. The Owner notification embeds only the processed email derivative as
a local MIME CID part. It must not contain the original image, a remote image,
a public URL, an access Token, a private item name, Tag ID, original filename,
Finder email, or Owner email in user-visible content or cross-party headers.

If the optional message is absent, the entire message section is omitted. A
Secure Reply action is shown only after Finder email verification has opened a
linked Conversation. Report ID and derivative version form the notification
idempotency key; retries must not create duplicate Owner alerts, and terminal
delivery failures are not retried indefinitely.

The Finder consent text must explain that the processed evidence thumbnail is
sent into the Owner's mailbox. ReturnTag can delete its stored copies under the
retention policy, but cannot recall copies already received, cached, exported,
or forwarded by an email client.

### Persistence, retention, and controls

Future expand Migrations will add separate Finder Report and private-media
storage contracts after Schema 8. The implementation ticket assigns the next
available contiguous versions; this ADR does not reserve Migration numbers.
Report records contain encrypted optional message content and state. Media
records contain only private object references, processing/integrity metadata,
and retention times; they contain no public URL or original filename.

Unnotified quarantine, rejected evidence, and terminal processing artifacts
expire within 24 hours. Notified report evidence expires 30 days after Owner
notification unless an approved abuse or dispute hold applies. Cleanup is
bounded, observable, retry-safe, and deletes both object bytes and their usable
references without deleting audit events. An email copy already delivered is
outside that cleanup boundary.

The dedicated `returntag_finder_evidence_enabled` control defaults disabled and
fails closed. Submission and notification also recheck
`returntag_finder_contact_enabled`; email dispatch rechecks
`returntag_email_dispatch_enabled`. Public submission requires same-site/CSRF
reasoning, atomic per-Tag, direct-peer IP, device/risk, and global limits, plus
an approved risk-CAPTCHA adapter when needed. CAPTCHA is supplemental rather
than the sole control.

## Consequences

- An Owner can receive useful evidence without making the Finder verify email.
- The initial flow is deliberately one-way; two-way privacy relay retains a
  verified destination and the existing Conversation contract.
- Image processing, private storage, content safety, queue idempotency,
  retention, and a kill switch become release blockers for runtime work.
- Mailbox copies cannot be recalled, so consent and minimum processed content
  are part of the product contract.
- RT-315 changes documentation only. TagCore remains `0.4.0`, Schema remains
  `8`, and no report table, media object, queue job, route, or email exists yet.

### Stage 1 implementation note

RT-315 Stage 1 subsequently assigns contiguous expand Migrations `0009` and
`0010`, advancing the implemented Schema from `8` to `10`. It adds only the two
tables, typed persistence contracts, and uncomposed `$wpdb` adapters. The ADR's
runtime, privacy, media-safety, retention, and default-off release conditions
remain unchanged; no public route, object storage, queue, or email is composed.

### Stage 2 implementation note

RT-315 Stage 2 adds the uncomposed media-safety kernel approved by this ADR.
Server-side Fileinfo and GD validate and decode one still JPEG, PNG, or WebP;
re-encoding strips metadata and produces the bounded review and email JPEGs.
Purpose-bound XChaCha20-Poly1305 storage uses separate external object and
reference keys and returns no path or URL. Content safety remains default-deny:
the shipped reviewer is unavailable, and only an explicit provider approval
can create an approved-evidence marker. Schema remains `10`; public intake,
queues, notification, retention orchestration, and provider composition remain
future stages.

## Rejected alternatives

- **Require Finder email verification before every alert:** adds friction to a
  one-way safety report and is superseded by this approved contract.
- **Store the report as `pending_verification` Conversation:** misrepresents an
  email-optional report and conflicts with required Conversation email fields.
- **Allow Owner replies before verification:** has no verified delivery target
  and would create an unsafe orphan-message channel.
- **Attach or remotely link the original image:** leaks uncontrolled bytes or
  metadata and creates tracking, Token-forwarding, and lifetime risks.
- **Notify with text when required evidence fails:** defeats the evidence gate
  and makes a rejected upload indistinguishable from an approved report.
- **Place evidence in WordPress Media Library:** creates a public-URL and
  editorial-asset boundary inappropriate for private Finder evidence.

## Rollback

This stage is documentation-only and creates no runtime or data to roll back.
A future implementation must first disable
`returntag_finder_evidence_enabled`, stop new intake, allow in-flight Workers to
converge safely, and retain or clean private evidence according to the approved
policy. Code rollback must remain compatible with prior Conversation data and
must not delete accepted messages, ownership, audit events, or Tag IDs.
