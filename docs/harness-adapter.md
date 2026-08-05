# Harness adapter protocol

KAIWU isolates vendor-specific behavior at the filesystem boundary. A bridge can be a shell script, daemon, MCP server, plugin, or native Harness integration.

## Inbox

The Inbox is append-only JSONL. Each non-empty line is one event:

```json
{
  "schema": "kaiwu.event/v1",
  "id": "evt-20260805-001",
  "type": "execution.completed",
  "status": "completed",
  "title": "Feature tests passed",
  "summary": "The approved Quest completed without deployment.",
  "project_slug": "kaiwu-demo",
  "occurred_at": "2026-08-05T09:00:00+08:00",
  "payload": {
    "dispatch_id": "7fb9c8df-1d08-4eb0-8685-5738c5fb6894"
  }
}
```

Required fields are `id`, `type`, `title`, and `occurred_at`. KAIWU uses `(connection, id)` as the idempotency key. Unknown fields remain available in the stored payload.

Suggested event types:

- `quest.accepted`
- `execution.started`
- `execution.progress`
- `execution.waiting`
- `execution.completed`
- `execution.failed`
- `execution.cancelled`

## Outbox

Each dispatch receives an immutable directory:

```text
outbox/
└── <dispatch-id>/
    ├── quest.json
    └── status.json
```

KAIWU writes both files atomically. A bridge should claim a queued dispatch without modifying `quest.json`, perform the work within its own permission model, then append events to the Inbox.

## Adapter rule

An adapter translates; it does not weaken approval. A vendor-specific adapter must not dispatch a Quest unless the envelope contains `approval.status = approved` and a non-null `approved_at`.
