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
