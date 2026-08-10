# Domain

Framework-independent entities, value objects, policies, validation, state
rules, and frozen business invariants belong here. Domain PHP must not call
WordPress, WooCommerce, database, email, HTTP, or queue APIs directly.

RT-109 adds backed enums for the canonical Batch, Tag, smart-network,
conversation, message-sender, and delivery values. The enums freeze persisted
vocabulary only; they do not implement state transitions or workflows.

RT-315 Stage 1 adds separate Finder Report status, evidence status, and allowed
source-MIME enums. They do not alter the canonical Conversation states or imply
that an image has been decoded, scanned, stored, or approved.

RT-315 Stage 2 adds the closed `approved`/`rejected` safety-decision vocabulary
and the `source`/`review`/`email` private-object purposes. The safety enum has no
unknown or unavailable approval value; provider failure is an exception and
cannot be persisted or interpreted as approval.

RT-202 adds the strict `TagId` value object. It owns the exact six-character
length and canonical unambiguous alphabet, but it does not normalize public
input, generate randomness, reserve an ID, or handle collisions.
