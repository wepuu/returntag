# ReturnTag Performance Baseline

**Status:** Milestone 2 capacity acceptance baseline

**Ticket:** RT-210

**Measured:** 2026-07-29

## 1. Approved operating envelope

Milestone 2 supports:

- at most `100,000` requested Tag IDs in one Batch;
- at least `1,000,000` retained Tag rows in the tested site dataset;
- resumable generation in chunks of `100`;
- deterministic CSV export of a complete `100,000`-Tag Batch.

The Batch creation boundary rejects a requested quantity greater than
`100,000` before writing a Batch or Event. This is an application capacity
contract, not a database limit.

## 2. Default acceptance environment

The recorded measurements use the default isolated wp-env test site:

```text
WordPress: 7.0.2
PHP: 8.4
WooCommerce: 10.9.4
Database image: mariadb:lts
Schema version: 8
```

Fixtures are synthetic and contain no real personal data. The million-row
fixture creates ten Batches with `100,000` Tags each. Performance tests drop
only their trusted, dynamically prefixed test tables during cleanup; they do
not reset the development site or destroy Docker volumes.

## 3. Acceptance budgets

| Operation | Dataset or sample | Budget |
|---|---:|---:|
| Generation chunk | 10,000 generated Tags, chunk size 100 | p95 <= 2.0 s |
| Batch inventory first page | 100,000 Tags, page size 100 | p95 <= 0.3 s |
| Batch inventory continuation | 100,000 Tags, page size 100 | p95 <= 0.3 s |
| Exact Tag search | 1,000,000 Tags | p95 <= 0.2 s |
| Batch search first page | 100,000 Tags, page size 100 | p95 <= 0.3 s |
| Batch search continuation | 100,000 Tags, page size 100 | p95 <= 0.3 s |
| Batch progress projection | 1,000,000 Tags retained | p95 <= 0.2 s |
| Lifecycle Tag-status count | 100,000 Tags | p95 <= 2.0 s |
| Deterministic CSV build | 100,000 Tags | <= 90 s and <= 128 MiB peak delta |

The lifecycle count budget is deliberately separate from bounded page reads:
it validates all `100,000` rows through the existing
`(batch_id, tag_status)` index before a release decision.

## 4. Recorded result

The 2026-07-29 default-environment run passed:

| Operation | Result |
|---|---:|
| Generation chunk | p95 `0.638803 s` |
| Batch inventory first page | p95 `0.000596 s` |
| Batch inventory continuation | p95 `0.000499 s` |
| Exact Tag search | p95 `0.000610 s` |
| Batch search first page | p95 `0.000934 s` |
| Batch search continuation | p95 `0.000876 s` |
| Batch progress projection | p95 `0.000715 s` |
| Lifecycle Tag-status count | p95 `1.698596 s` |
| Deterministic CSV build | `0.557556 s`, no measurable peak-memory increase |

PHPUnit reported `2 tests, 42 assertions` in `93.944 s`. These figures are a
repeatable engineering baseline, not a production service-level agreement.
Container host load, database statistics, storage, and production concurrency
may produce different absolute timings.

## 5. Query-plan decision

The performance suite verifies that representative inventory and Batch-search
statements expose an indexed candidate through `EXPLAIN`. It does not freeze
the optimizer-selected key, row estimate, cost, or access type.

The Schema version 8 primary key and `batch_id_status` index met the approved
million-row budgets. RT-210 therefore adds no `(batch_id, tag_id)` index and no
Migration. A future capacity change must provide new measurements and use a
numbered Migration if an index is then justified.

## 6. Running the suite

From `plugin/tagcore` in the isolated WordPress integration environment:

```text
composer test:performance
```

This suite is intentionally separate from `composer check` and ordinary pull
request checks because it creates one million rows and takes materially longer
than unit or integration tests. Run it for capacity-affecting query, index,
generation, export, database-engine, or infrastructure changes and before a
Milestone 2 release candidate.
