# RT-339 Privacy Data Map

**Status:** Draft engineering map — BLOCKED for acceptance

**Evidence baseline:** canonical `main@164480c`, TagCore `0.5.0`, Schema `15`,
capability contract `6`

**External policy version:** `UNVERIFIED`

**Accountable privacy owner:** `UNVERIFIED`

This map is implementation evidence, not a substitute for the product or
external privacy policy. Exact retention periods and response-time SLA values
remain unapproved until the two fields above are replaced by stable,
accountable references. The governing behavior is proposed in
[ADR 0030](../adr/0030-privacy-export-and-constrained-erasure-contract.md).

## Classification vocabulary

| Class | Meaning |
|---|---|
| Direct identity | Email address or WordPress User ID that directly identifies a person |
| Private content | Item text, Lost Mode text, Finder message, relay message, or evidence |
| Security material | OTP/token hashes, encrypted address lookup, IP/risk key, session or idempotency data |
| Business integrity | Tag, ownership, Batch, export, transfer, dispute, or accepted-message history that cannot be broadly deleted |
| Operational metadata | Fixed state, provider ID, timestamps, attempts, queue IDs, and health counters |

`Include` below always means include only after requester authorization and
output-field review. `Exclude` means the value never enters a personal export,
even if it is internally related to the requester.

## TagCore tables

| Storage | Personal or sensitive fields | Purpose and subject | Export | Erasure/anonymization | Retention and Hold | Code evidence |
|---|---|---|---|---|---|---|
| `returntag_batches` | `created_by`; free-text `notes` may contain accidental personal data | Manufacturing audit; administrator/operator | Exclude from Owner/Finder export; operator receives no raw Batch notes through privacy export | Preserve Batch and immutable manufacturing facts; policy must define whether old operator ID becomes an anonymous subject reference; free-text notes require separate minimization review | Batch and export integrity retention; no privacy request deletes a Batch | `CreateBatchesTableMigration.php`, Batch repositories |
| `returntag_tags` | `owner_id`, `item_name`, `public_label`, `lost_message`, ownership and scan times | Current Owner and physical Tag | Current Owner: Tag ID/type/status, their private item fields, Lost Mode state and relevant times; previous Owner: historical participation only, never current private fields | Active owned Tag causes `action_required`; no automatic unassign/retire. After approved lifecycle resolution, clear private text and replace/remove direct Owner link only under policy while preserving Tag ID, non-reuse and lifecycle facts | Tag ID and lifecycle are permanent integrity records; exact private-text retention is policy-blocked | `CreateTagsTableMigration.php`, Account and ownership repositories |
| `returntag_batch_exports` | `created_by` operator ID | Immutable manufacturing export audit | Exclude from Owner/Finder export | Preserve version, checksum, count and time; operator reference treatment is policy-blocked | Immutable audit; no privacy deletion | `CreateBatchExportsTableMigration.php`, `WpdbBatchExportRepository.php` |
| `returntag_auth_challenges` | encrypted email, email lookup HMAC, OTP hash, IP hash, attempt/send counts | Owner/Finder authentication and abuse prevention | Exclude all fields; an export may state only that authentication occurred when an approved Event already records it | Cleanup through bounded challenge expiry; never decrypt for erasure matching when keyed lookup suffices | Existing expiry worker controls; Holds do not make OTPs exportable | `CreateAuthChallengesTableMigration.php`, auth challenge stores |
| `returntag_conversations` | Owner snapshot ID, Finder email ciphertext/HMAC, verification and activity times | Private two-party relay | Verified participant receives safe Conversation status/times and entitled message content; never the other address or internal user ID | Close/revoke before anonymization. Finder address ciphertext/HMAC may be cleared only after retention/Hold checks; Owner snapshot may become an anonymous subject reference where policy permits | Conversation retention and dispute/abuse Hold take precedence | `CreateConversationsTableMigration.php`, Conversation repositories |
| `returntag_messages` | encrypted body, sender role; provider and dispatch fields | Accepted relay content and delivery | Include only content the verified requester sent or was entitled to receive, labelled by role without identity; exclude provider/dispatch internals | Preserve accepted-message audit fact. Body anonymization waits for both-party retention rights and Holds; never delete merely because one participant requested erasure | Approved message retention and Holds; exact period policy-blocked | `CreateMessagesTableMigration.php`, relay repositories |
| `returntag_access_tokens` | token hash, role, purpose, exchange/revocation times | Secure-link access | Exclude | Revoke first; expired/revoked hash rows follow security cleanup and are never exported | Short-lived security retention; no Hold exposes token data | `CreateAccessTokensTableMigration.php`, access-token stores |
| `returntag_events` | actor ID, target ID, bounded metadata may correlate to a person | Security, ownership and business audit | Export only an allowlisted plain-language summary of the requester's own actions; exclude raw `metadata_json`, correlation ID and other actors | Preserve Event fact. Direct actor reference may become an approved anonymous subject reference; target business IDs remain where integrity requires | Security/audit retention has precedence; exact period policy-blocked | `CreateEventsTableMigration.php`, `WpdbEventStore.php` |
| `returntag_finder_reports` | Owner snapshot ID, encrypted optional Finder message, Tag/conversation link | One-way recovery report and conversation bootstrap | Owner may receive safe report status and the message already disclosed to them; Finder may receive their submitted text/status after verified identity matching; exclude other-party IDs | Message anonymization waits for retention/Hold; preserve report outcome, Tag link and audit facts without direct identity where policy permits | Existing report/media expiry plus Hold; evidence itself remains excluded | `CreateFinderReportsTableMigration.php`, Finder Report repositories |
| `returntag_finder_report_media` | encrypted object references, image derivatives, hashes, dimensions, Hold placer ID/times | Private evidence and technical processing | Exclude every image, derivative, object reference, hash, dimension, key ID and Hold field | Cleanup only through private-media retention worker after no active Hold; privacy eraser cannot bypass or directly expose object storage | `retention_until` and Schema 14 Hold tuple govern; exact policy mapping remains blocked | `CreateFinderReportMediaTableMigration.php`, `AddFinderEvidenceHoldMigration.php`, private-media stores |
| `returntag_tag_transfers` | source Owner ID, encrypted target email/HMAC, token hash, status/times | Ownership transfer | Participant receives only their role, Tag, status and safe times; never the other party's email or internal ID | Cancel/revoke open token paths before identity anonymization. Preserve transfer outcome and immediate previous-Owner revocation evidence | Transfer/security retention and dispute Hold; exact period policy-blocked | `CreateTagTransfersTableMigration.php`, transfer repositories |
| `returntag_email_deliveries` | no address/content; opaque idempotency key and provider message ID | Provider-neutral delivery projection | Exclude provider, IDs, idempotency key and attempts; user-facing export may use only a safe delivery-status summary when tied through an authorized business record | Preserve minimal delivery evidence for the approved operational period; do not attempt email-based deletion because no address is stored | Operational delivery retention; exact period policy-blocked | `CreateEmailDeliveryTablesMigration.php`, `WpdbEmailDeliveryRepository.php` |
| `returntag_email_webhook_events` | no address/content; provider event/message IDs and times | Signature-verified delivery convergence | Exclude | Bounded operational cleanup after terminal convergence and approved retention; never store or reconstruct raw payload | Operational retention; exact period policy-blocked | `CreateEmailDeliveryTablesMigration.php`, webhook repository/controller |

## WordPress-owned identity data

| Storage | Contract |
|---|---|
| `wp_users` through the active `$wpdb->users` table | WordPress owns account profile, password and core erasure semantics. TagCore must use public WordPress privacy APIs and must not query a hard-coded `wp_` table. TagCore never overwrites or exports password hashes. |
| User Meta | RT-339 found no approved TagCore personal-data User Meta contract. Any future TagCore key must be added to this map before writing. Core/WooCommerce keys remain owned by their providers. |
| Roles and capabilities | Role membership is authorization data, not ordinary Owner/Finder export content. Capability-version Options contain no person. Account deletion must not silently alter unrelated administrators. |
| WordPress privacy requests/exports | Core confirmation and protected export-file lifecycle are reused. TagCore callbacks remain bounded, paginated and participant-aware. TagCore does not create a second permanent export archive. |

Table prefixes above are conceptual. Runtime code must always use the active
WordPress prefix and never hard-code `wp_`.

## WordPress Options

| Option class | Data | Export | Cleanup/anonymization | Evidence |
|---|---|---|---|---|
| Feature flags and schema/capability versions | Site configuration, no person | Exclude | Preserve | `WordPressOptionFeatureFlagReader`, schema/capability stores |
| OTP, activation, manual-entry, Finder and Account rate buckets | Count, expiry and keyed/HMAC-shaped peer/email/Tag buckets | Exclude | Existing bounded expiry cleanup; never reverse or export a lookup key | `Infrastructure/RateLimit/*` |
| Owner test-email dispatch claims | Opaque event/owner-derived claim and expiry | Exclude | Existing claim cleanup | `WordPressOptionOwnerTestEmailDispatchClaimStore` |
| Finder Report submission ledger | Opaque form/token-derived state and expiry | Exclude | Existing bounded cleanup; never expose submission tokens | `WordPressFinderReportSubmissionLedger` |
| Retention task status/claims | Fixed task ID, state, counts and times; operator ID may appear in the Action Scheduler argument/Event rather than the public result | Exclude from ordinary export; approved operator history comes from privacy-safe Events | Preserve bounded operational status; remove expired claims | `RetentionTaskManager` |
| Rewrite rules and WordPress/WooCommerce Options | Provider-owned site configuration | Out of TagCore exporter scope | Provider-owned | WordPress/WooCommerce APIs |

No privacy request may enumerate all Options or use an unbounded SQL prefix
scan in a public request. Cleanup workers use trusted fixed prefixes and
bounded batches.

## Private files and object storage

Finder source images and controlled Review/Email derivatives are encrypted
private objects outside WordPress uploads and the Media Library. They have no
public URL and are never included in a privacy export. Object references,
encryption key IDs, content hashes, dimensions, original filenames and image
bytes remain excluded.

Erasure calls the existing private-media deletion abstraction only after the
database record is retention-eligible and has no active Hold. A partial object
deletion failure leaves the database checkpoint retryable; it must not mark the
privacy request completed or drop the only cleanup reference.

Evidence: `Infrastructure/Media`, `Infrastructure/Persistence`, ADR 0019, ADR
0026, and ADR 0028.

## Action Scheduler and queues

Approved TagCore actions enqueue internal numeric IDs or bounded fixed
arguments: Batch/checkpoint, challenge ID, Finder Report ID, message ID,
transfer ID, Event/Owner ID, retention task ID, and email webhook convergence.
New privacy workers must enqueue only the internal privacy request ID.

Queue payloads must not contain an email address, message body, evidence bytes,
token, IP address, provider payload, or export archive. The worker re-resolves
current authorized state at execution time. Action Scheduler history is
operational metadata and is excluded from personal export; cleanup follows the
versioned retention policy without deleting unfinished actions.

Evidence: `Infrastructure/Queue`, `Account/AccountBootstrap.php`,
`Admin/RetentionTaskManager.php`, and `Infrastructure/Email/EmailWebhookBootstrap.php`.

## Logs, metrics and Events

- Ordinary logs use the existing sensitive-context sanitizer and fixed error
  codes. They are not a personal-data export source.
- Logs must not contain full email addresses, OTPs, tokens, message bodies,
  private item text, evidence, object references, encryption material, Resend
  credentials, or complete webhook payloads.
- Metrics are aggregate operational counts. Small-cohort reporting must not
  make a person or single order/Tag relationship inferable.
- Business/security Events are durable records, but exports use a fixed safe
  projection rather than raw `metadata_json`.

Evidence: `SensitiveLogContextSanitizer`, `WordPressErrorLogLogger`, Event
repositories, and governance query projections.

## Actor-specific visibility matrix

| Data | Current Owner | Finder | Previous Owner | Administrator/support |
|---|---|---|---|---|
| Current private Tag fields | Include for owned Tag | Exclude | Exclude | Only through existing dedicated capability and audited view |
| Public Tag fields | Include | Include as already public | Historical safe summary only | Include through authorized Tag lookup |
| Finder address | Exclude | Include only as the requester's own confirmed identity, never as a stored raw export field | Exclude | No ordinary display/export; decryption only where an approved support flow explicitly requires it |
| Owner address | Include only as the Owner's own WordPress profile | Exclude | Include only as their own WordPress profile | WordPress user administration rules, not TagCore export |
| Relay content | Entitled messages only | Entitled messages only after verified identity | Exclude after access revocation | Existing capability, feature flag, explicit reveal and audit only |
| Finder evidence | Exclude | Exclude | Exclude | Existing evidence-preview capability/flag/Hold rules; never privacy export |
| Tokens, OTP, IP/risk keys | Exclude | Exclude | Exclude | Exclude from ordinary UI/export/logs |
| Provider and queue internals | Exclude | Exclude | Exclude | Bounded operational status only, no content/address |
| Ownership/transfer history | Own safe role/history | Exclude | Own safe historical role | Authorized privacy-safe audit projection |

## RT-340 implementation gates

RT-340 must not begin runtime acceptance until:

1. the stable external policy version and accountable owner replace both
   `UNVERIFIED` values;
2. every policy retention/SLA rule is mapped to a table, Option, file, queue,
   Event or log class above;
3. the privacy owner approves export and constrained-erasure projections;
4. Schema 16 defines the fixed request states without email, free text or
   private payload storage; and
5. tests cover Owner, Finder and previous-Owner boundaries, active Tag
   `action_required`, Hold precedence, idempotent retry, partial failure, and
   an export privacy-leak scan.
