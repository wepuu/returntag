# Domain

Framework-independent entities, value objects, policies, validation, state
rules, and frozen business invariants belong here. Domain PHP must not call
WordPress, WooCommerce, database, email, HTTP, or queue APIs directly.

RT-109 adds backed enums for the canonical Batch, Tag, smart-network,
conversation, message-sender, and delivery values. The enums freeze persisted
vocabulary only; they do not implement state transitions or workflows.

RT-202 adds the strict `TagId` value object. It owns the exact six-character
length and canonical unambiguous alphabet, but it does not normalize public
input, generate randomness, reserve an ID, or handle collisions.
