# Harness adapter protocol

KAIWU isolates vendor-specific behavior at the filesystem boundary. A bridge can be a shell script, daemon, MCP server, plugin, or native Harness integration.

The machine-readable contract is [schemas/kaiwu-event-v1.schema.json](../schemas/kaiwu-event-v1.schema.json). Public valid and invalid examples live in [examples/conformance](../examples/conformance) so adapter authors can run the same checks as KAIWU.

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

Required fields are `schema`, `id`, `type`, `title`, and `occurred_at`. KAIWU uses `(connection, id)` as the idempotency key. Unknown fields remain available in the stored payload.

Validate an event Inbox without importing it:

```bash
php artisan harness:validate examples/conformance/valid/events.jsonl
php artisan harness:validate /absolute/path/to/events.jsonl --schema=event
```

The command is read-only and returns a non-zero exit code when a line is malformed or violates the selected schema.

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

## DeepSeek Harness Preview bridge

KAIWU ships a process-separated bridge under [`bridges/deepseek-harness`](../bridges/deepseek-harness). It pins the official Python SDK `0.1.0rc6` and npm headless CLI `0.1.0-rc.6`, translates each approved Quest into one DSH task, and translates the selected automation transport's lifecycle/results back into the event contract above. Auto mode uses SDK JSON-RPC on Linux and the npm CLI on macOS because the published rc6 macOS arm64 SDK wheel omits the `node-pty` native file required by its default runtime.

The bridge maps `requires_write=false` to DSH `read-only` and write-capable Quests to `workspace-write`. SDK mode's Cordis composition uses sandboxed Bash and filesystem providers with approval policy `never`; CLI mode's shipped headless profile has no interactive approval answerer. Both fail closed on unattended permission escalation. KAIWU imports the resulting events separately and projects recognized `execution.started`, `execution.completed`, `execution.failed`, and `execution.cancelled` events onto the matching `QuestExecution` by `payload.dispatch_id`.

DeepSeek labels DSH Developer Preview and warns of breaking changes. The KAIWU Event/Quest contracts remain the stable boundary; the SDK pin is an explicitly versioned preview transport.
