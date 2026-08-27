# RT-339 Privacy Data Map

**Status:** Accepted contract map

**Evidence baseline:** canonical `main@164480c`, TagCore `0.5.0`, Schema `15`,
capability contract `6`

**External policy version:** `FORGETAG-PRIVACY-RETENTION-v1.0-20260827`

**Accountable privacy owner:** Forge Life LLC, acting as ForgeTag Product Owner
and Privacy Owner

**Effective and approval date:** 2026-08-27

This map is implementation evidence, not a substitute for the product or
external privacy policy. Forge Life LLC approved the Owner, Finder, and
previous-Owner projections and constrained-anonymization boundaries recorded
here. The governing behavior and approved schedule are fixed in
[ADR 0030](../adr/0030-privacy-export-and-constrained-erasure-contract.md).

## Approved retention and SLA mapping

| Approved rule | Engineering projection |
|---|---|
| Privacy export archive: 7 days | Reuse the protected WordPress export lifecycle; TagCore must not create a second permanent archive. |
| Privacy request audit: 3 years | Future Schema 16 request rows retain only fixed state, policy version, checkpoint/result codes, and timestamps; no email, body, evidence, IP, or provider payload. |
| OTP challenges: 24 hours after expiry/consumption | All TagCore OTP purposes require bounded deletion within 24 hours. The existing seven-day post-expiry cleanup is non-compliant and must be corrected before RT-340 acceptance. |
| Access-token hashes: 30 days after expiry/revocation | Revoke first, then delete hash-only token rows within 30 days; raw tokens are never stored or exported. |
| Temporary rate-limit/submission state: 24 hours after expiry | Bounded Option/ledger cleanup must remove expired state within 24 hours. |
| Operational/security logs: 90 days | Provider log retention must be configured to no more than 90 days; logs are never an export source. |
| Notified Finder Evidence: 30 days | Existing report/media `retention_until` remains the ordinary cleanup boundary; Active Hold overrides affected deletion. |
| Rejected/incomplete Finder Evidence: 7 days | Seven days is the maximum. Existing 24-hour cleanup remains compliant and may stay shorter. |
| Closed/expired Conversation message content: 12 months | Encrypted bodies and Finder identity ciphertext/HMAC are removed or irreversibly disconnected no later than 12 months after terminal closure/expiry, unless an Active Hold applies; accepted-message audit facts remain separately. |
| Private Tag fields after ownership ends: 30 days | `item_name`, `public_label`, and `lost_message` are cleared within 30 days after ownership ends, subject to Hold and integrity checks; active ownership first causes `action_required`. |
| Email delivery/webhook metadata: 180 days | Schema 15 delivery and allowlisted webhook-event metadata are cleaned within 180 days; addresses, content, and raw payloads remain absent. |
| Ownership/transfer/dispute/security audit facts: 7 years | Preserve fixed facts for seven years while replacing eligible direct identity links with a non-reversible anonymous subject reference. Active Hold overrides affected cleanup. |
| Tag IDs/Batch/manufacturing export integrity: permanent | Preserve public Tag IDs, non-reuse state, Batch facts, export versions, row counts, checksums, and timestamps permanently. |
| Backup natural expiry: 35 days | Do not rewrite historical backups for one request. Data removed from live systems expires through protected backup rotation within 35 days. |
| Request acknowledgement | Immediate, no later than 24 hours. |
| Normal export/erasure completion | Target seven calendar days; internal maximum 30 calendar days. `action_required` and valid Hold pause only affected completion. |
| Retryable failure/completion notification | Create an operational alert within 24 hours of a retryable failure and notify completion within 24 hours. |

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
| `returntag_batches` | `created_by`; free-text `notes` may contain accidental personal data | Manufacturing audit; administrator/operator | Exclude from Owner/Finder export; operator receives no raw Batch notes through privacy export | Preserve Batch and immutable manufacturing facts permanently; anonymize eligible operator identity after the seven-year audit boundary; free-text notes require separate minimization review | Permanent Batch/export integrity; seven-year attributable audit; Active Hold overrides affected anonymization | `CreateBatchesTableMigration.php`, Batch repositories |
| `returntag_tags` | `owner_id`, `item_name`, `public_label`, `lost_message`, ownership and scan times | Current Owner and physical Tag | Current Owner: Tag ID/type/status, their private item fields, Lost Mode state and relevant times; previous Owner: historical participation only, never current private fields | Active owned Tag causes `action_required`; no automatic unassign/retire. After approved lifecycle resolution, clear private text within 30 days and anonymize eligible direct Owner links while preserving Tag ID, non-reuse and lifecycle facts | Tag ID and lifecycle facts are permanent; private fields have a 30-day post-ownership maximum; Active Hold overrides affected cleanup | `CreateTagsTableMigration.php`, Account and ownership repositories |
| `returntag_batch_exports` | `created_by` operator ID | Immutable manufacturing export audit | Exclude from Owner/Finder export | Preserve version, checksum, count and time permanently; anonymize eligible operator identity after seven years | Permanent export integrity; seven-year attributable audit; no privacy deletion of export facts | `CreateBatchExportsTableMigration.php`, `WpdbBatchExportRepository.php` |
| `returntag_auth_challenges` | encrypted email, email lookup HMAC, OTP hash, IP hash, attempt/send counts | Owner/Finder authentication and abuse prevention | Exclude all fields; an export may state only that authentication occurred when an approved Event already records it | Cleanup through bounded challenge expiry; never decrypt for erasure matching when keyed lookup suffices | Delete within 24 hours after expiry/consumption. Existing seven-day cleanup must be corrected by RT-340 acceptance; Holds do not make OTPs exportable | `CreateAuthChallengesTableMigration.php`, auth challenge stores |
| `returntag_conversations` | Owner snapshot ID, Finder email ciphertext/HMAC, verification and activity times | Private two-party relay | Verified participant receives safe Conversation status/times and entitled message content; never the other address or internal user ID | Close/revoke before anonymization. Remove or irreversibly disconnect Finder address ciphertext/HMAC no later than the 12-month terminal-content boundary; Owner snapshot may become an anonymous subject reference | Twelve months after close/expiry for identity-bearing content; dispute/abuse Hold takes precedence | `CreateConversationsTableMigration.php`, Conversation repositories |
| `returntag_messages` | encrypted body, sender role; provider and dispatch fields | Accepted relay content and delivery | Include only content the verified requester sent or was entitled to receive, labelled by role without identity; exclude provider/dispatch internals | Preserve accepted-message audit fact. Remove or irreversibly disconnect the body within 12 months after Conversation close/expiry unless a Hold applies | Twelve months after close/expiry for content; seven years for approved audit facts; Active Hold overrides affected cleanup | `CreateMessagesTableMigration.php`, relay repositories |
| `returntag_access_tokens` | token hash, role, purpose, exchange/revocation times | Secure-link access | Exclude | Revoke first; expired/revoked hash rows are deleted within 30 days and are never exported | Maximum 30 days after expiry/revocation; no Hold exposes token data | `CreateAccessTokensTableMigration.php`, access-token stores |
| `returntag_events` | actor ID, target ID, bounded metadata may correlate to a person | Security, ownership and business audit | Export only an allowlisted plain-language summary of the requester's own actions; exclude raw `metadata_json`, correlation ID and other actors | Preserve Event fact for seven years. Replace eligible direct actor references with an approved non-reversible anonymous subject reference; target business IDs remain where integrity requires | Seven-year ownership/transfer/dispute/security audit; Active Hold overrides affected anonymization | `CreateEventsTableMigration.php`, `WpdbEventStore.php` |
| `returntag_finder_reports` | Owner snapshot ID, encrypted optional Finder message, Tag/conversation link | One-way recovery report and conversation bootstrap | Owner may receive safe report status and the message already disclosed to them; Finder may receive their submitted text/status after verified identity matching; exclude other-party IDs | Remove or irreversibly disconnect one-way report content within 30 days after Owner notification. If the report remains linked to a Conversation, participant message content follows the 12-month terminal Conversation boundary. Preserve report outcome, Tag link and audit facts without direct identity | Thirty days after notification for one-way content; 12 months after Conversation close/expiry for participant content; Active Hold overrides affected cleanup; evidence itself remains excluded | `CreateFinderReportsTableMigration.php`, Finder Report repositories |
| `returntag_finder_report_media` | encrypted object references, image derivatives, hashes, dimensions, Hold placer ID/times | Private evidence and technical processing | Exclude every image, derivative, object reference, hash, dimension, key ID and Hold field | Cleanup only through private-media retention worker after no active Hold; privacy eraser cannot bypass or directly expose object storage | Maximum 30 days after Owner notification; rejected/incomplete evidence maximum seven days, with the existing shorter 24-hour cleanup permitted; Active Hold overrides affected deletion | `CreateFinderReportMediaTableMigration.php`, `AddFinderEvidenceHoldMigration.php`, private-media stores |
| `returntag_tag_transfers` | source Owner ID, encrypted target email/HMAC, token hash, status/times | Ownership transfer | Participant receives only their role, Tag, status and safe times; never the other party's email or internal ID | Cancel/revoke open token paths before identity anonymization. Preserve transfer outcome and immediate previous-Owner revocation evidence; anonymize eligible direct identity after seven years | Seven-year transfer/security audit; dispute Hold overrides affected anonymization | `CreateTagTransfersTableMigration.php`, transfer repositories |
| `returntag_email_deliveries` | no address/content; opaque idempotency key and provider message ID | Provider-neutral delivery projection | Exclude provider, IDs, idempotency key and attempts; user-facing export may use only a safe delivery-status summary when tied through an authorized business record | Delete minimal delivery metadata within 180 days; do not attempt email-based deletion because no address is stored | Maximum 180 days | `CreateEmailDeliveryTablesMigration.php`, `WpdbEmailDeliveryRepository.php` |
| `returntag_email_webhook_events` | no address/content; provider event/message IDs and times | Signature-verified delivery convergence | Exclude | Delete allowlisted metadata within 180 days after convergence; never store or reconstruct raw payload | Maximum 180 days | `CreateEmailDeliveryTablesMigration.php`, webhook repository/controller |

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
| OTP, activation, manual-entry, Finder and Account rate buckets | Count, expiry and keyed/HMAC-shaped peer/email/Tag buckets | Exclude | Remove within 24 hours after expiry; never reverse or export a lookup key | `Infrastructure/RateLimit/*` |
| Owner test-email dispatch claims | Opaque event/owner-derived claim and expiry | Exclude | Remove within 24 hours after expiry | `WordPressOptionOwnerTestEmailDispatchClaimStore` |
| Finder Report submission ledger | Opaque form/token-derived state and expiry | Exclude | Remove within 24 hours after expiry; never expose submission tokens | `WordPressFinderReportSubmissionLedger` |
| Retention task status/claims | Fixed task ID, state, counts and times; operator ID may appear in the Action Scheduler argument/Event rather than the public result | Exclude from ordinary export; approved operator history comes from privacy-safe Events | Remove temporary claims within 24 hours after expiry; preserve approved audit Events under the seven-year rule | `RetentionTaskManager` |
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
operational metadata and is excluded from personal export. Finished temporary
state expires within 24 hours where it is part of a TagCore rate-limit or
submission ledger; provider-owned scheduler history must not exceed the
approved 90-day operational-log boundary. Cleanup never deletes unfinished
actions.

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
- Operational and security log providers must enforce a maximum 90-day
  retention. Approved ownership, transfer, dispute, and security Event facts
  remain for seven years with constrained anonymization.

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

RT-340 engineering implementation is authorized. Runtime acceptance still
requires:

1. Schema 16 defines the fixed request states without email, free text or
   private payload storage;
2. every rule above is implemented or backed by documented platform
   configuration and operational evidence;
3. the existing seven-day OTP post-expiry cleanup is reduced to the approved
   24-hour maximum;
4. tests cover Owner, Finder and previous-Owner boundaries, active Tag
   `action_required`, Hold precedence, idempotent retry, partial failure, and
   an export privacy-leak scan; and
5. production enablement receives a separate approval.
