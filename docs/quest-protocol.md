# Quest protocol

`kaiwu.quest/v1` is the stable handoff between KAIWU and an execution Harness.

The machine-readable contract is [schemas/kaiwu-quest-v1.schema.json](../schemas/kaiwu-quest-v1.schema.json). A valid public example is available at [examples/conformance/valid/quest.json](../examples/conformance/valid/quest.json).

Validate a Quest envelope without dispatching it:

```bash
php artisan harness:validate examples/conformance/valid/quest.json
php artisan harness:validate /absolute/path/to/quest.json --schema=quest
```

## Lifecycle

```text
draft
→ awaiting_approval
→ approved
→ queued
→ running
→ awaiting_result_confirmation
→ completed
```

Failure and cancellation branches are append-only:

```text
queued|running → failed → queued (new attempt)
approved|queued|running → cancelled
```

Every retry creates a new `quest_execution`, attempt number, dispatch ID, and Outbox directory. Previous errors and results are never overwritten.

## Envelope fields

- `id`: unique dispatch ID.
- `quest_id`: KAIWU database identity.
- `attempt`: monotonic execution attempt.
- `goal`: desired outcome, not an unrestricted instruction.
- `acceptance_criteria`: observable completion conditions.
- `constraints`: boundaries the Harness must preserve.
- `verification`: checks required before reporting success.
- `risk_level`: `low`, `medium`, or `high`.
- `requires_write`: whether repository or workspace changes are expected.
- `approval`: persisted human approval evidence.

The receiving Harness remains responsible for its own sandbox, repository checks, secrets, and external side effects.
