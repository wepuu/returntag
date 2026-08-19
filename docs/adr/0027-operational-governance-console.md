# ADR 0027: Operational roles, global audit search, and retention controls

## Status

Accepted for RT-329.

## Context

RT-326 through RT-328 introduced capability-separated operations queries and controlled mutations, but administrators still had to assemble roles manually, inspect audit history only through object details, and infer cleanup health from Action Scheduler. Arbitrary capability editors, audit exports, custom retention periods, and unbounded manual cleanup would expand privilege and privacy risk.

## Decision

TagCore installs eight fixed, least-privilege WordPress roles: Batch Operator, Tag Operator, Tag Lifecycle Operator, Dispute Operator, User Support, Audit Viewer, Retention Operator, and Operations Manager. Capability contract version 6 adds `manage_returntag_role_profiles` and `manage_returntag_retention`. Only Administrators receive role-profile configuration by default. Operations Manager receives operational capabilities but no role configuration, `edit_users`, or site administration. Reconciliation changes only TagCore-owned capabilities on fixed roles; it preserves users and unrelated WordPress capabilities.

The internal `POST /tagcore/v1/admin/audit-events/search` route requires a REST Nonce, current Schema, and `view_returntag_audit_logs`. It defaults to 24 hours, caps the window at 31 days, accepts only exact allowlisted filters, and uses criteria-bound `(created_at, event_id)` keyset cursors. The response omits Event metadata and correlation identifiers and projects only identity classifications, internal identifiers, result, and UTC time. User links require `view_returntag_users` independently.

The Retention view exposes four fixed policies backed by existing cleanup hooks. `GET /tagcore/v1/admin/retention/tasks` returns policy copy, schedule health, bounded pending counts, and last/next run state without Action arguments, storage references, or raw errors. `POST /tagcore/v1/admin/retention/tasks/{task}/run` additionally requires an exact Task ID confirmation and the default-off `returntag_admin_retention_run_enabled` control. It queues one wrapper action that invokes one existing bounded cleanup batch. Duplicate pending work fails closed. Automatic schedules do not consult the manual-run flag.

Manual request and completion/failure append metadata-free retention-task Events. Finder evidence cleanup remains Hold-aware. No governance route changes frozen security windows or deletes business Events, accepted Messages, Tag IDs, Owner Claims, or manufacturing exports. Schema remains 14, TagCore remains 0.5.0, and no dependency changes are introduced.

## Consequences

Operators gain repeatable least-privilege profiles, a global privacy-minimized Audit Log, and visible cleanup health. The Operations console remains a WordPress Admin adapter; business cleanup logic remains in existing Application and Infrastructure services. Rollback first disables manual runs, then restores code. Fixed roles and capabilities may remain safely installed, and all audit history is preserved.
