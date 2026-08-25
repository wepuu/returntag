# ADR 0028: Defer Finder image content moderation

**Status:** Accepted

**Date:** 2026-08-25

## Context

ADR 0019 required every Finder Report image to pass an approved external
content-safety review before Owner notification. No approved provider account,
production configuration, or calibrated policy is available. Treating that
missing integration as a release gate would keep the otherwise complete Finder
Report journey unavailable.

The product owner has approved a narrower current contract: ForgeTag accepts
exactly one required Finder evidence image and applies the existing technical
and privacy controls, but does not currently inspect or classify the image's
content. A future moderation integration remains possible only through a
separately approved product and architecture change.

## Decision

The current Finder Report runtime:

- validates the actual JPEG, PNG, or WebP signature and MIME agreement;
- enforces source-byte, decoded-pixel, cardinality, and derivative bounds;
- decodes and re-encodes pixels to remove filenames, EXIF, GPS, device, capture
  time, and unnecessary embedded metadata;
- stores the source and controlled derivatives only in encrypted private
  storage outside WordPress uploads and the Media Library;
- preserves rate limits, retention, Hold, idempotent background work, current-
  Owner resolution, privacy-safe errors, and the dedicated default-disabled
  Finder evidence feature flag;
- marks evidence `ready` after those technical controls complete successfully;
- does not call `FinderEvidenceSafetyReviewer`, require reviewer availability,
  or persist a moderation decision; and
- tells users that ForgeTag removes metadata but does not currently review image
  content. Product and email copy must not describe an image as safe, approved,
  moderated, scanned, or reviewed.

Decode, validation, storage, re-encoding, queue, or privacy-control failure
still fails closed and prevents Owner notification. Content moderation is not a
phase-one release gate. AWS, Rekognition, provider credentials, moderation
schema fields, model versions, policy versions, thresholds, and provider calls
are outside the current implementation.

The dormant provider-neutral moderation interfaces may remain as uncomposed
extension points. Re-enabling content moderation requires a new approved ticket
and an ADR/PRD change that defines the provider, calibrated decision policy,
failure behavior, data minimization, retention, user disclosure, staging
evidence, and release controls.

## Consequences

- The Finder Report flow can operate without an external moderation account.
- Owners may receive technically processed images whose visual content has not
  been reviewed by ForgeTag. The UI and transactional email disclose this
  limitation.
- Private-media technical controls remain mandatory and are not weakened by
  this decision.
- `ready` means technically processed and eligible for notification; it is not
  a content-safety classification.
- `returntag_finder_evidence_enabled` remains an independent operational kill
  switch and defaults disabled until explicit environment acceptance.

## Supersession

This ADR supersedes only the content-moderation and reviewer-availability gates
in ADR 0019. All other ADR 0019 requirements remain in force.

## Rollback

Disable `returntag_finder_evidence_enabled` to stop new intake. Do not delete
accepted reports, evidence Holds, notification records, Events, Conversations,
ownership, Tags, or Batch history. A future approved moderation adapter must be
introduced behind a new default-disabled rollout control or equivalent staged
release decision; it must not be silently activated by reverting this ADR.
