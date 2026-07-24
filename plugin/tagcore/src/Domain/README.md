# Domain

Framework-independent entities, value objects, policies, validation, state
rules, and frozen business invariants belong here. Domain PHP must not call
WordPress, WooCommerce, database, email, HTTP, or queue APIs directly.

RT-109 adds backed enums for the canonical Batch, Tag, smart-network,
conversation, message-sender, and delivery values. The enums freeze persisted
vocabulary only; they do not implement state transitions or workflows.
