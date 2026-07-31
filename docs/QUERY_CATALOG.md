# ReturnTag Repository Query Catalog

**Status:** Milestone 1 Schema version 8 acceptance baseline

## 1. Purpose

This catalog maps every RT-109 Repository read shape to its stable cursor and
candidate database index. It is an operational review aid, not permission to
expose these queries directly through a public route or generic CRUD API.

Repository lists are bounded to at most 100 records. Query-plan tests verify
that MariaDB and MySQL report the catalogued candidate indexes without fixing
optimizer-specific costs, row estimates, access types, or complete plans.

## 2. Point lookups

| Repository method | Predicate | Candidate index |
|---|---|---|
| Batch by ID | `batch_id = ?` | `PRIMARY` |
| Batch by code | `batch_code = ?` | `batch_code_unique` |
| Tag by Tag ID | `tag_id = ?` | `PRIMARY` |
| Batch Export by Batch/version | `batch_id = ? AND export_version = ?` | `batch_export_version_unique` |
| Auth Challenge by ID | `challenge_id = ?` | `PRIMARY` |
| Conversation by ID | `conversation_id = ?` | `PRIMARY` |
| Access Token by digest | `token_hash = ?` | `token_hash_unique` |

These methods return at most one typed record. Token-digest lookup performs no
authentication, exchange, revocation, or session creation.

## 3. Bounded lists

| Repository method | Ordering and cursor | Candidate index |
|---|---|---|
| Tags by Batch | `tag_status ASC, tag_id ASC` | `batch_id_status` plus the InnoDB primary-key suffix |
| Tags by Owner | `tag_status ASC, tag_id ASC` | `owner_id_status` plus the InnoDB primary-key suffix |
| Batch Exports by Batch | `export_version DESC` | `batch_export_version_unique` |
| Latest Auth Challenge | `created_at DESC, challenge_id DESC` | `purpose_email_created_at` plus the InnoDB primary-key suffix |
| Messages by Conversation | `message_id ASC` | `conversation_message` |
| Events by Target | `created_at DESC, event_id DESC` | `target_type_target_id_created_at` plus the InnoDB primary-key suffix |
| Events by Correlation | `event_id DESC` | `correlation_id` plus the InnoDB primary-key suffix |

All cursors use strict comparisons and request one extra record to determine
whether another bounded page exists. No list uses offset pagination or an
unbounded result.

## 4. Projection policy

Current Repository methods hydrate complete persistence records. Tag, Message,
Auth Challenge, Conversation, and Event records may include TEXT or BLOB data.
Public scan pages and future administrative tables must introduce explicit,
privacy-reviewed summary projections instead of reusing complete record reads
for large lists.

Summary projections must:

- name every selected column;
- exclude ciphertext, message bodies, hashes, private item names, and metadata
  unless the use case explicitly requires them;
- retain bounded cursor pagination;
- document the matching index here;
- receive EXPLAIN and privacy regression coverage.

## 5. EXPLAIN policy

`RepositoryQueryPlanTest` executes representative bounded query shapes through
`EXPLAIN`. It asserts only that the statement is accepted and that the expected
index is present in `possible_keys`.

The test deliberately does not assert:

- the optimizer's selected `key`;
- `rows`, `filtered`, or cost estimates;
- exact access type;
- complete text or JSON plans.

Those values vary with engine version, statistics, and fixture size. Capacity
benchmarks and production-scale latency budgets remain future operational work.

## 6. RT-205 administrative progress

| Read shape | Predicate and bound | Candidate index |
|---|---|---|
| Batch progress | `batch_id = ?` with named counter/state columns | `PRIMARY` |
| Generation lifecycle times | `target_type = 'batch' AND target_id = ? AND event_type IN (...)`, ordered by `created_at, event_id`, limit `3` | `target_type_target_id_created_at` plus the InnoDB primary-key suffix |

The projection returns no Tag row, Event metadata, Action Scheduler argument,
private manufacturing notes, or personal data. Queue status is inspected
separately through the provider adapter.

## 7. RT-206 Batch Tag inventory

| Read shape | Predicate, ordering, and bound | Candidate index |
|---|---|---|
| First inventory page | `batch_id = ?`, `ORDER BY tag_id ASC`, limit `page_size + 1` | `PRIMARY` or `batch_id_status`, optimizer-selected |
| Continued inventory page | `batch_id = ? AND tag_id > ?`, `ORDER BY tag_id ASC`, limit `page_size + 1` | `PRIMARY` or `batch_id_status`, optimizer-selected |

The projection names only `tag_id`, `tag_status`, and `created_at`. It does not
reuse `TagRepository::list_by_batch()` or hydrate TEXT/private columns. The
cursor is the last Tag ID internally and a versioned opaque Base64URL value at
the REST boundary.

Schema 8 has no `(batch_id, tag_id)` compound index. RT-206 therefore records
EXPLAIN output and bounded 2,500-row behavior without asserting an optimizer
choice or silently changing Schema. RT-210 must evaluate real capacity data
before proposing a new numbered Migration.

The RT-206 integration fixture inserts 2,500 synthetic non-PII rows, executes
both prepared EXPLAIN statements, verifies that each reports indexed
candidates, and reads two bounded 50-row pages without offset pagination. The
selected key, access type, estimates, and cost remain deliberately unfrozen.

## 8. RT-207 audited Batch CSV export

| Read or write shape | Predicate, ordering, and bound | Candidate index |
|---|---|---|
| Export source first chunk | `batch_id = ?`, `ORDER BY tag_id ASC`, limit `500` | `PRIMARY` or `batch_id_status`, optimizer-selected |
| Export source continuation | `batch_id = ? AND tag_id > ?`, `ORDER BY tag_id ASC`, limit `500` | `PRIMARY` or `batch_id_status`, optimizer-selected |
| Locked Batch state | `batch_id = ? FOR UPDATE` | `PRIMARY` |
| Batch Tag count | `batch_id = ?` | `batch_id_status` |
| Latest export audit | `batch_id = ?`, `ORDER BY export_version DESC`, limit `1` | `batch_export_version_unique` |
| Export history | `batch_id = ? AND export_version < ?`, `ORDER BY export_version DESC`, bounded limit | `batch_export_version_unique` |
| First-export transition | `batch_id = ? AND batch_status = 'generated' AND generated_quantity = requested_quantity` | `PRIMARY` |

The export source names only `tag_id`, `tag_type`, and `model_code`; it does not
hydrate complete Tag records. Version allocation is serialized by the parent
Batch row rather than `MAX(export_version)` without a lock. The existing unique
Batch/version index remains the final concurrency constraint.

RT-207 intentionally does not add a `(batch_id, tag_id)` index. Its chunked
query extends the RT-206 plan already assigned to RT-210 capacity validation.
No test should freeze an optimizer-specific key, cost, or row estimate.

## 9. RT-208 Batch lifecycle controls

| Read or write shape | Predicate, ordering, and bound | Candidate index |
|---|---|---|
| Lifecycle state | `batch_id = ?`, optionally `FOR UPDATE` | `PRIMARY` |
| Tag status counts | `batch_id = ? GROUP BY tag_status` | `batch_id_status` |
| Latest audited export | `batch_id = ? ORDER BY export_version DESC LIMIT 1` | `batch_export_version_unique` |
| Conditional transition | `batch_id = ? AND batch_status = ?` | `PRIMARY` |

The aggregate query returns only canonical counts. It does not select Tag IDs,
owner IDs, private item fields, Lost Mode content, scan times, or message data.
The Batch lock serializes the status write and Event append; the conditional
predicate remains the final stale-state guard. Schema version `8` requires no
new index for these bounded queries.

## 10. RT-209 read-only Tag search

| Read shape | Predicate, ordering, and bound | Candidate index |
|---|---|---|
| Exact Tag ID | `tag_id = ?`, limit `1` | `PRIMARY` |
| First exact Batch page | unique `batch_code = ?`, optional `tag_status = ?`, `ORDER BY tag_id ASC`, limit `page_size + 1` | `batch_code_unique`, then `PRIMARY` or `batch_id_status`, optimizer-selected |
| Continued exact Batch page | same filters plus `tag_id > ?`, `ORDER BY tag_id ASC`, limit `page_size + 1` | `batch_code_unique`, then `PRIMARY` or `batch_id_status`, optimizer-selected |

The projection names twelve approved operational columns across Tags and
Batches and never selects owner, item, label, Lost Mode message, or scan
fields. The application derives activation availability without another query.
The Base64URL cursor is versioned and bound to a stable hash of the exact
normalized filters. Schema 8 has no `(batch_id, tag_id)` compound index;
RT-210 owns capacity validation and any numbered Migration proposal.

## 11. RT-210 measured capacity decision

The RT-210 profile creates ten Batches with `100,000` synthetic Tags each and
measures the existing RT-205 through RT-209 query shapes against one million
retained rows. It also validates a 10,000-Tag Action Scheduler generation smoke
run and deterministic export of one complete 100,000-Tag Batch.

All approved budgets in `docs/PERFORMANCE.md` passed. Representative inventory
and Batch-search queries exposed indexed candidates under `EXPLAIN`; the test
continues to avoid assertions about optimizer-selected keys, cost estimates,
or exact row counts.

No `(batch_id, tag_id)` Migration is added. The existing Schema 8 primary key
and `batch_id_status` index remain the documented candidates. A higher Batch
limit, materially different data distribution, or observed production
regression requires new measurements before an index proposal.

## 12. RT-302 public input boundary

RT-302 adds no query shape. The public route normalizes and validates one
bounded path segment entirely before the Repository boundary. It neither uses
the exact Tag primary-key lookup documented for RT-209 nor tests whether a Tag
or Batch exists. RT-303 owns the first approved public state-resolution query.

## 13. RT-303 public state resolution

| Read shape | Predicate, join, and bound | Candidate indexes |
|---|---|---|
| Public Tag state | `t.tag_id = ?`, left join `b.batch_id = t.batch_id`, limit `1` | Tags `PRIMARY`, Batches `PRIMARY` |

The projection names only:

```text
t.owner_id
t.tag_type
t.public_label
t.tag_status
t.lost_mode
t.lost_message
t.activated_at
b.batch_status
b.activation_enabled
```

The left join preserves a present Tag whose Batch row is missing so
Application can fail closed as a data-integrity error. `owner_id` is used only
for a server-side equality decision and is not part of the rendered page
model. The query never selects private item names, Batch codes, emails,
orders, messages, tokens, scan history, devices, pairing state, or locations.
It performs no write and requires no new Schema version 8 index.

## 14. RT-304 activation OTP request

| Read or write shape | Predicate and bound | Candidate index |
|---|---|---|
| Recent email count | `purpose = 'activation_otp' AND email_lookup = ? AND created_at >= ?` | `purpose_email_created_at` |
| Recent Tag count | `subject_type = 'tag' AND subject_id = ? AND created_at >= ?` | `subject_created_at` |
| Worker challenge lock | `challenge_id = ? FOR UPDATE` | `PRIMARY` |
| Latest open replacement check | purpose, subject, email lookup, `consumed_at IS NULL`, newest one | `purpose_email_created_at` |
| Atomic issue | primary key plus `send_count=0`, open, unexpired | `PRIMARY` |
| Retention cleanup | `expires_at < ?`, ordered and limited to `500` | `expires_consumed_at` |

Email and Tag count queries remain bounded by fixed recent windows. Direct IP
counts do not scan `ip_hash`; atomic IP and global budgets use plugin-owned,
non-autoloaded WordPress Option buckets under a site-scoped advisory lock.
Schema remains version `8`.

## 15. RT-305 activation OTP verification

| Read or write shape | Predicate and bound | Candidate index |
|---|---|---|
| Latest verification eligibility | purpose, subject type/ID, keyed email, newest one, `LIMIT 1` | `purpose_email_created_at` |
| Latest verification lock | purpose, subject type/ID, keyed email, newest one, `LIMIT 1 FOR UPDATE` | `purpose_email_created_at` |
| Wrong-code attempt | primary key, exact prior attempt, `< 5`, issued, open, unexpired | `PRIMARY` |
| One-time success | primary key, `< 5`, issued, unverified, unconsumed, unexpired | `PRIMARY` |

The first query is bounded to one row. Its purpose and keyed-email prefix uses
`purpose_email_created_at`; subject predicates are applied to the small
matching challenge set. Conditional primary-key writes execute within the same
transaction and row lock.

Direct-peer IP, Tag, and global verification budgets are reserved before the
eligibility read. The keyed-email budget is reserved only after an eligible
latest challenge is found, preventing unknown identities from allocating
durable email buckets. All budgets use hashed, non-autoloaded
`returntag_otp_verify_rate_*` Options under a site-scoped advisory lock. No
unindexed challenge-table IP scan or Schema change is added.

## 16. RT-306 passwordless identity provisioning

| Read or write shape | Predicate and bound | Authority |
|---|---|---|
| Account advisory lock | network ID plus truncated keyed email lookup, two-second wait | MySQL named lock |
| Exact WordPress user lookup | canonical email, at most three candidates, exact in-process comparison | WordPress User API and `user_email` index |
| New WordPress user | fixed Subscriber role, opaque random login, canonical email | WordPress User API |
| Account audit append | `account_passwordless_created`, numeric User target, no metadata | `returntag_events` append |

The advisory lock contains no raw email and coordinates every ReturnTag
passwordless account creation path. The WordPress `user_email` and
`user_login` indexes are not treated as unique database constraints. More than
one exact email match fails closed. No Tag, Batch, order, shipment, ownership,
or activation query is added.

## 17. RT-307 atomic activation

| Read or write shape | Predicate and bound | Candidate indexes |
|---|---|---|
| First-owner conditional update | exact `t.tag_id`, null Owner, `unregistered`, null activation time, joined released and activation-enabled Batch | Tags `PRIMARY`, Batches `PRIMARY` |
| Zero-row committed-state lock | exact `t.tag_id`, left-joined Batch, `LIMIT 1 FOR UPDATE` | Tags `PRIMARY`, Batches `PRIMARY` |
| Activation audit append | fixed `tag_activated`, numeric User actor, canonical Tag target, no metadata | Events insert |

The update is bounded to one Tag because `tag_id` is unique. The follow-up
lock reads only Owner ID, Tag status, activation timestamp, Batch status, and
Batch activation control. It runs only after a zero-row update and does not
select email, item, label, Lost Mode content, order, shipment, message, token,
device, or location data. Schema 8 requires no new index.

## 18. RT-308 committed-state convergence

RT-308 adds no query shape. It composes the RT-307 conditional activation and
zero-row lock with the existing RT-303 exact public Tag/Batch state read. The
second read is deliberate: it maps the committed database state rather than
the write outcome to an existing Owner, Finder, invalid, or explanation page.

No conflict row or Event is written, and no Owner identifier crosses the
Application-to-renderer boundary. Candidate indexes and Schema version remain
unchanged.

## 19. RT-309 authenticated activation budgets

| Read or write shape | Predicate and bound | Authority |
|---|---|---|
| Nine fixed-window budget reads/writes | exact hashed Option name | WordPress Options primary key |
| Limiter lock | site-derived fixed lock name, two-second wait | MySQL named lock |
| Expired bucket cleanup | activation Option prefix, ordered, limit `500` | WordPress Options name index |
| Activation and convergence | existing RT-307 write/lock plus RT-303 committed-state read | Existing documented indexes |

Budget Option names contain expiry plus SHA-256 scope hashes. Values contain
only count and expiry. Raw email, IP, Tag ID, User profile, cookie, Session,
OTP, item, order, message, device, and location data are not selected or
stored. Schema version 8 requires no new index.

## 20. RT-310 Smart Tag static guide

RT-310 adds no read or write shape. Guide visibility uses the canonical
`tag_type` and public page state already present in the RT-303 privacy-minimized
projection and Application view model. It does not read or write
`owner_pairing_ack_at`, account, device, pairing, battery, location, or
smart-network data. Schema version 8 and all existing indexes remain
unchanged.
