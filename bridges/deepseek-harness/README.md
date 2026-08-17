# DeepSeek Harness bridge

This external bridge consumes human-approved `kaiwu.quest/v1` Outbox directories, runs them through a pinned official DeepSeek Harness automation transport, and appends normalized `kaiwu.event/v1` events to KAIWU's Inbox.

The bridge pins both published DeepSeek transports to rc6: `deepseek-harness-sdk==0.1.0rc6` and `@deepseek-ai/dsh@0.1.0-rc.6`. DeepSeek currently labels DSH as Developer Preview and warns that compatibility-breaking changes will occur. Upgrade either pin only after reviewing the official release and rerunning the bridge and KAIWU conformance tests.

## Install

On Linux, auto mode uses the Python SDK (Python 3.10+). On macOS, auto mode uses the npm headless CLI (Node.js 22.19+ or 24+) because the published rc6 macOS arm64 SDK wheel is missing the `node-pty` native file required by its bundled default runtime. This was verified against the official wheel itself; forcing `--transport sdk` on that wheel fails during initialization.

Install the platform's auto-mode dependency:

```bash
cd /absolute/path/to/Kaiwu/bridges/deepseek-harness
python3 -m venv .venv
. .venv/bin/activate
python -m pip install -r requirements.txt

# macOS rc6 fallback
npm install
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

`--transport auto` is the default. Use `--transport sdk` or `--transport cli` only for an explicit compatibility test. CLI mode finds `dsh` on `PATH` or the bridge-local `node_modules/.bin/dsh`; `--dsh-bin` can point to another pinned executable. `--request-timeout-seconds` applies to the npm process and SDK JSON-RPC requests.

## Safety

- The bridge rejects any envelope without `approval.status = approved` and `approved_at`.
- `requires_write=false` selects DSH `read-only`; otherwise it selects `workspace-write`.
- SDK mode uses the included Cordis composition with sandboxed Bash/filesystem providers and approval policy `never`. CLI mode uses DSH's shipped headless profile with `DSH_PERMISSION_MODE`; headless has no interactive answerer, so wider permission requests fail closed.
- The bridge holds a per-dispatch process lock, writes status atomically, and appends JSONL under a file lock.
- KAIWU imports events separately; the bridge never connects to the application database or runs inside an HTTP request.

Run keyless tests with:

```bash
python -m unittest discover -s bridges/deepseek-harness/tests -v
```
