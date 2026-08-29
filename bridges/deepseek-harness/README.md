# DeepSeek Harness bridge

This external bridge consumes human-approved `kaiwu.quest/v1` Outbox directories, runs them through a pinned official DeepSeek Harness automation transport, and appends normalized `kaiwu.event/v1` events to KAIWU's Inbox.

The bridge pins the latest installable npm CLI, `@deepseek-ai/dsh@0.1.1-rc.2`, and records the latest Python SDK compatibility target, `deepseek-harness-sdk==0.1.1rc1`. DeepSeek currently labels DSH as Developer Preview and warns that compatibility-breaking changes will occur. The newer GitHub tag `0.1.2-alpha.1` has no matching npm CLI or Python SDK package, so it is not a reproducible deployment target.

## Install

Auto mode uses the npm headless CLI on every platform (Node.js 22.19+ or 24+). The published Python SDK `0.1.1rc1` macOS arm64 wheel now includes its native runtime, but that runtime does not expose `@deepseek-ai/dsh-bash-sandbox` to external Cordis configurations. KAIWU's safety composition therefore fails during initialization instead of falling back to an unconfined executor. `--transport sdk` remains an explicit compatibility diagnostic, not the supported automatic path.

Install the auto-mode dependency. npm 10 may need legacy peer resolution for this Developer Preview release; KAIWU explicitly pins the required `@deepseek-ai/cordis-plugin-group` peer:

```bash
cd /absolute/path/to/Kaiwu/bridges/deepseek-harness
npm ci --legacy-peer-deps

# Optional: reproduce the explicit SDK compatibility check.
python3 -m venv .venv
. .venv/bin/activate
python -m pip install -r requirements.txt
```

Keep `DEEPSEEK_API_KEY` outside Git. `DEEPSEEK_BASE_URL` is optional for a compatible proxy.

## Run one scan

Create a KAIWU Harness connection with driver `DeepSeek Harness Preview`, then use the exact Inbox and Outbox paths from that connection:

```bash
export DEEPSEEK_API_KEY=sk-...
python bridges/deepseek-harness/kaiwu_dsh_bridge.py \
  --outbox /absolute/path/to/kaiwu-outbox \
  --inbox /absolute/path/to/deepseek-events.jsonl \
  --workspace /absolute/path/to/approved-workspace \
  --session-root /absolute/path/to/dsh-sessions
php artisan harness:sync deepseek-local
```

The command scans once so an operator can supervise it with cron, launchd, systemd, or another process manager. `--dispatch-id <uuid>` narrows a run. A dispatch already marked `completed`, `failed`, or `cancelled` is skipped. `--retry-running` is an explicit crash-recovery switch and can repeat model work; inspect the workspace and status file before using it.

`--transport auto` is the default and selects the pinned npm CLI. Use `--transport sdk` only for an explicit compatibility test. CLI mode finds `dsh` on `PATH` or the bridge-local `node_modules/.bin/dsh`; `--dsh-bin` can point to another pinned executable. `--request-timeout-seconds` applies to the npm process and SDK JSON-RPC requests.

## Safety

- The bridge rejects any envelope without `approval.status = approved` and `approved_at`.
- `requires_write=false` selects DSH `read-only`; otherwise it selects `workspace-write`.
- Auto/CLI mode uses DSH's shipped headless profile with `DSH_PERMISSION_MODE`; headless has no interactive answerer, so wider permission requests fail closed. The explicit SDK composition also requests sandboxed Bash/filesystem providers and approval policy `never`; an SDK runtime that cannot load those plugins fails closed at startup.
- The bridge holds a per-dispatch process lock, writes status atomically, and appends JSONL under a file lock.
- KAIWU imports events separately; the bridge never connects to the application database or runs inside an HTTP request.

Run keyless tests with:

```bash
python -m unittest discover -s bridges/deepseek-harness/tests -v
```
