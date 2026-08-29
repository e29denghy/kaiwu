#!/usr/bin/env python3
"""Run approved KAIWU Quest envelopes through DeepSeek Harness transports."""

from __future__ import annotations

import argparse
import fcntl
import json
import os
import shutil
import subprocess
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Protocol


ADAPTER_VERSION = "0.2.0"
DSH_RELEASE = "0.1.1-rc.2"
SDK_VERSION = "0.1.1rc1"
SUCCESS_FINISH_REASONS = {"completed", "max-tokens"}


class BridgeError(RuntimeError):
    """Raised when a dispatch cannot safely enter DeepSeek Harness."""


@dataclass(frozen=True)
class HarnessRunResult:
    """Normalized fields returned by a DeepSeek Harness transport."""

    session_id: str
    final_response: str
    finish_reason: str | None


class HarnessRunner(Protocol):
    """Testable runner boundary around an official automation transport."""

    transport: str

    def run(self, prompt: str, session_id: str, permission_mode: str) -> HarnessRunResult:
        """Run one approved Quest and return the normalized transport result."""


@dataclass(frozen=True)
class BridgeConfig:
    """Operator-owned bridge paths and model selection."""

    outbox: Path
    inbox: Path
    workspace: Path
    session_root: Path
    cordis: Path
    provider: str = "deepseek-official"
    model: str = "deepseek-v4-flash"
    max_tokens: int | None = None
    request_timeout_seconds: float | None = None
    transport: str = "auto"
    dsh_bin: Path | None = None


class DeepSeekSdkRunner:
    """Execute each dispatch in an isolated official SDK runtime."""

    transport = "python-sdk-jsonrpc"

    def __init__(self, config: BridgeConfig) -> None:
        self.config = config

    def run(self, prompt: str, session_id: str, permission_mode: str) -> HarnessRunResult:
        try:
            from deepseek_harness import DeepSeekHarness
        except ImportError as exception:
            raise BridgeError(
                f"deepseek-harness-sdk=={SDK_VERSION} is required; install requirements.txt"
            ) from exception

        with DeepSeekHarness(
            provider=self.config.provider,
            model=self.config.model,
            max_tokens=self.config.max_tokens,
            cwd=str(self.config.workspace),
            runtime_cwd=str(self.config.workspace),
            session_root=str(self.config.session_root),
            cordis=str(self.config.cordis),
            env={"DSH_PERMISSION_MODE": permission_mode},
            request_timeout_seconds=self.config.request_timeout_seconds,
        ) as harness:
            result = harness.run(prompt, session_id=session_id)

        return HarnessRunResult(
            session_id=result.session_id,
            final_response=result.final_response,
            finish_reason=result.finish_reason,
        )


class DeepSeekCliRunner:
    """Run the pinned official npm headless CLI and capture its final text."""

    transport = "npm-headless-cli"

    def __init__(self, config: BridgeConfig, executable: Path) -> None:
        self.config = config
        self.executable = executable

    def run(self, prompt: str, session_id: str, permission_mode: str) -> HarnessRunResult:
        environment = os.environ.copy()
        environment.update(
            {
                "DSH_HOME": str(self.config.session_root),
                "DSH_PERMISSION_MODE": permission_mode,
            }
        )
        timeout = self.config.request_timeout_seconds
        try:
            completed = subprocess.run(
                [str(self.executable), "--profile", "headless", prompt],
                cwd=self.config.workspace,
                env=environment,
                text=True,
                capture_output=True,
                timeout=timeout,
                check=False,
            )
        except (OSError, subprocess.TimeoutExpired) as exception:
            raise BridgeError(f"DeepSeek Harness npm CLI failed to run: {exception}") from exception

        if completed.returncode != 0:
            diagnostics = completed.stderr.strip() or completed.stdout.strip() or "no diagnostics"
            raise BridgeError(
                f"DeepSeek Harness npm CLI exited {completed.returncode}: {diagnostics[-4000:]}"
            )

        final_response = completed.stdout.strip()
        if final_response == "":
            raise BridgeError("DeepSeek Harness npm CLI returned no final assistant response")

        return HarnessRunResult(
            session_id=session_id,
            final_response=final_response,
            finish_reason="completed",
        )


def select_runner(config: BridgeConfig) -> HarnessRunner:
    """Choose a transport; auto uses the latest sandbox-capable npm release."""

    transport = config.transport
    if transport == "auto":
        transport = "cli"
    if transport == "sdk":
        return DeepSeekSdkRunner(config)
    if transport != "cli":
        raise BridgeError("transport must be one of auto, sdk, or cli")

    bundled = Path(__file__).with_name("node_modules") / ".bin" / "dsh"
    discovered = str(config.dsh_bin) if config.dsh_bin is not None else shutil.which("dsh")
    executable = Path(discovered) if discovered else bundled
    if not executable.is_file():
        raise BridgeError(
            "DeepSeek Harness npm CLI was not found; run npm install in the bridge directory "
            "or pass --dsh-bin"
        )
    return DeepSeekCliRunner(config, executable.resolve())


def utc_now() -> str:
    """Return an RFC 3339 UTC timestamp."""

    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def read_json(path: Path) -> dict[str, Any]:
    """Read one JSON object with an actionable error."""

    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exception:
        raise BridgeError(f"cannot read JSON {path}: {exception}") from exception

    if not isinstance(payload, dict):
        raise BridgeError(f"JSON root must be an object: {path}")

    return payload


def write_json_atomic(path: Path, payload: dict[str, Any]) -> None:
    """Replace a status document without exposing a partial write."""

    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(f".{path.name}.{os.getpid()}.tmp")
    data = json.dumps(payload, ensure_ascii=False, indent=2) + "\n"
    with temporary.open("w", encoding="utf-8") as handle:
        handle.write(data)
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(temporary, path)


def append_event(inbox: Path, payload: dict[str, Any]) -> None:
    """Append one complete JSONL event under an inter-process lock."""

    inbox.parent.mkdir(parents=True, exist_ok=True)
    line = json.dumps(payload, ensure_ascii=False, separators=(",", ":")) + "\n"
    with inbox.open("a", encoding="utf-8") as handle:
        fcntl.flock(handle.fileno(), fcntl.LOCK_EX)
        handle.write(line)
        handle.flush()
        os.fsync(handle.fileno())
        fcntl.flock(handle.fileno(), fcntl.LOCK_UN)


def validate_quest(quest: dict[str, Any], dispatch_id: str) -> None:
    """Enforce the immutable approval fields before starting the transport."""

    if quest.get("schema") != "kaiwu.quest/v1":
        raise BridgeError("unsupported or missing Quest schema")
    if quest.get("id") != dispatch_id:
        raise BridgeError("Quest id must match its dispatch directory")
    if not isinstance(quest.get("title"), str) or not quest["title"].strip():
        raise BridgeError("Quest title is required")
    if not isinstance(quest.get("goal"), str) or not quest["goal"].strip():
        raise BridgeError("Quest goal is required")
    approval = quest.get("approval")
    if not isinstance(approval, dict):
        raise BridgeError("Quest approval record is required")
    if approval.get("status") != "approved" or not approval.get("approved_at"):
        raise BridgeError("Quest is not human-approved; dispatch rejected")
    if not isinstance(quest.get("requires_write"), bool):
        raise BridgeError("Quest requires_write must be boolean")


def list_items(value: Any) -> list[str]:
    """Normalize a Quest list while rejecting non-string entries."""

    if value is None:
        return []
    if not isinstance(value, list) or any(not isinstance(item, str) for item in value):
        raise BridgeError("Quest criteria, constraints, and verification must be string arrays")
    return [item.strip() for item in value if item.strip()]


def format_list(items: list[str]) -> str:
    """Render a stable numbered list for the DSH prompt."""

    return "\n".join(f"{index}. {item}" for index, item in enumerate(items, 1)) or "None specified."


def build_prompt(quest: dict[str, Any], workspace: Path) -> str:
    """Translate a KAIWU Quest envelope into one DSH user message."""

    acceptance = list_items(quest.get("acceptance_criteria"))
    constraints = list_items(quest.get("constraints"))
    verification = list_items(quest.get("verification"))
    permission = "workspace-write" if quest["requires_write"] else "read-only"

    return f"""You are DeepSeek Harness executing a human-approved KAIWU Quest.

Dispatch ID: {quest['id']}
Quest title: {quest['title']}
Workspace: {workspace}
Permission mode: {permission}

Goal:
{quest['goal']}

Acceptance criteria:
{format_list(acceptance)}

Constraints:
{format_list(constraints)}

Required verification:
{format_list(verification)}

Execution rules:
- Read and follow AGENTS.md and repository-local instructions before editing.
- Stay inside the configured workspace and do not request sandbox escalation.
- Preserve unrelated worktree changes and never deploy unless the Quest explicitly authorizes deployment.
- If permission mode is read-only, do not modify files even if a tool appears capable of doing so.
- Verify the result against every listed criterion and report changed files, checks, and unresolved risks.
""".strip()


def event_payload(
    dispatch_id: str,
    event_type: str,
    status: str,
    title: str,
    summary: str,
    quest: dict[str, Any] | None,
    details: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Create a normalized KAIWU event for one dispatch phase."""

    phase = event_type.removeprefix("execution.")
    project = quest.get("project") if isinstance(quest, dict) else None
    project_slug = project.get("slug") if isinstance(project, dict) else None
    payload: dict[str, Any] = {
        "dispatch_id": dispatch_id,
        "harness": "deepseek-harness",
        "adapter_version": ADAPTER_VERSION,
        "dsh_release": DSH_RELEASE,
    }
    if details:
        payload.update(details)
    transport = str(payload.get("transport", "python-sdk-jsonrpc"))
    if transport == "python-sdk-jsonrpc":
        payload["sdk_version"] = SDK_VERSION

    return {
        "schema": "kaiwu.event/v1",
        "id": f"dsh-{dispatch_id}-{phase}",
        "type": event_type,
        "status": status,
        "title": title,
        "summary": summary,
        "project_slug": project_slug,
        "occurred_at": utc_now(),
        "payload": payload,
        "adapter_extension": {
            "vendor": "deepseek-ai",
            "transport": transport,
        },
    }


def process_dispatch(
    dispatch_directory: Path,
    config: BridgeConfig,
    runner: HarnessRunner,
    retry_running: bool = False,
) -> str:
    """Claim and process one dispatch, returning completed, failed, or skipped."""

    dispatch_id = dispatch_directory.name
    lock_path = dispatch_directory / ".deepseek-harness-bridge.lock"
    lock_path.parent.mkdir(parents=True, exist_ok=True)

    with lock_path.open("a+", encoding="utf-8") as lock:
        try:
            fcntl.flock(lock.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return "skipped"

        quest: dict[str, Any] | None = None
        status_path = dispatch_directory / "status.json"

        try:
            status = read_json(status_path)
            current_status = status.get("status")
            eligible = current_status == "queued" or (retry_running and current_status == "running")
            if not eligible:
                return "skipped"

            quest = read_json(dispatch_directory / "quest.json")
            validate_quest(quest, dispatch_id)
            prompt = build_prompt(quest, config.workspace)
            permission_mode = "workspace-write" if quest["requires_write"] else "read-only"
            session_id = f"kaiwu-{dispatch_id}"
            transport = runner.transport
            started_at = utc_now()

            write_json_atomic(
                status_path,
                {
                    "schema": "kaiwu.execution-status/v1",
                    "dispatch_id": dispatch_id,
                    "status": "running",
                    "harness": "deepseek-harness",
                    "transport": transport,
                    "session_id": session_id,
                    "updated_at": started_at,
                },
            )
            append_event(
                config.inbox,
                event_payload(
                    dispatch_id,
                    "execution.started",
                    "running",
                    f"DeepSeek Harness started: {quest['title']}",
                    "The approved Quest entered the pinned DeepSeek Harness automation runtime.",
                    quest,
                    {
                        "session_id": session_id,
                        "permission_mode": permission_mode,
                        "transport": transport,
                    },
                ),
            )

            result = runner.run(prompt, session_id, permission_mode)
            if result.finish_reason not in SUCCESS_FINISH_REASONS:
                raise BridgeError(
                    f"DeepSeek Harness ended with finish_reason={result.finish_reason or 'none'}"
                )

            finished_at = utc_now()
            write_json_atomic(
                status_path,
                {
                    "schema": "kaiwu.execution-status/v1",
                    "dispatch_id": dispatch_id,
                    "status": "completed",
                    "harness": "deepseek-harness",
                    "transport": transport,
                    "session_id": result.session_id,
                    "finish_reason": result.finish_reason,
                    "updated_at": finished_at,
                },
            )
            append_event(
                config.inbox,
                event_payload(
                    dispatch_id,
                    "execution.completed",
                    "completed",
                    f"DeepSeek Harness completed: {quest['title']}",
                    result.final_response[:4000],
                    quest,
                    {
                        "session_id": result.session_id,
                        "finish_reason": result.finish_reason,
                        "result": result.final_response,
                        "transport": transport,
                    },
                ),
            )
            return "completed"
        except Exception as exception:
            error = str(exception) or exception.__class__.__name__
            failed_at = utc_now()
            write_json_atomic(
                status_path,
                {
                    "schema": "kaiwu.execution-status/v1",
                    "dispatch_id": dispatch_id,
                    "status": "failed",
                    "harness": "deepseek-harness",
                    "transport": runner.transport,
                    "error": error,
                    "updated_at": failed_at,
                },
            )
            append_event(
                config.inbox,
                event_payload(
                    dispatch_id,
                    "execution.failed",
                    "failed",
                    "DeepSeek Harness dispatch failed",
                    error,
                    quest,
                    {"error": error, "transport": runner.transport},
                ),
            )
            return "failed"
        finally:
            fcntl.flock(lock.fileno(), fcntl.LOCK_UN)


def dispatch_directories(outbox: Path, dispatch_id: str | None = None) -> list[Path]:
    """Return candidate dispatch directories in deterministic order."""

    if dispatch_id is not None:
        if Path(dispatch_id).name != dispatch_id:
            raise BridgeError("dispatch id must not contain path separators")
        directory = outbox / dispatch_id
        if not directory.is_dir():
            raise BridgeError(f"dispatch directory does not exist: {directory}")
        return [directory]
    if not outbox.is_dir():
        raise BridgeError(f"outbox directory does not exist: {outbox}")
    return sorted(path for path in outbox.iterdir() if path.is_dir())


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    """Parse the bridge command line."""

    default_cordis = Path(__file__).with_name("cordis.yml")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--outbox", required=True, type=Path)
    parser.add_argument("--inbox", required=True, type=Path)
    parser.add_argument("--workspace", required=True, type=Path)
    parser.add_argument("--session-root", required=True, type=Path)
    parser.add_argument("--cordis", type=Path, default=default_cordis)
    parser.add_argument("--provider", default="deepseek-official")
    parser.add_argument("--model", default="deepseek-v4-flash")
    parser.add_argument("--max-tokens", type=int)
    parser.add_argument("--request-timeout-seconds", type=float)
    parser.add_argument("--transport", choices=["auto", "sdk", "cli"], default="auto")
    parser.add_argument("--dsh-bin", type=Path)
    parser.add_argument("--dispatch-id")
    parser.add_argument("--retry-running", action="store_true")
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    """Process eligible dispatches once for cron/launchd supervision."""

    args = parse_args(argv)
    config = BridgeConfig(
        outbox=args.outbox.resolve(),
        inbox=args.inbox.resolve(),
        workspace=args.workspace.resolve(),
        session_root=args.session_root.resolve(),
        cordis=args.cordis.resolve(),
        provider=args.provider,
        model=args.model,
        max_tokens=args.max_tokens,
        request_timeout_seconds=args.request_timeout_seconds,
        transport=args.transport,
        dsh_bin=args.dsh_bin.resolve() if args.dsh_bin is not None else None,
    )

    if not config.workspace.is_dir():
        print(f"error: workspace directory does not exist: {config.workspace}", file=sys.stderr)
        return 2
    if not config.cordis.is_file():
        print(f"error: Cordis config does not exist: {config.cordis}", file=sys.stderr)
        return 2

    try:
        directories = dispatch_directories(config.outbox, args.dispatch_id)
    except BridgeError as exception:
        print(f"error: {exception}", file=sys.stderr)
        return 2

    try:
        runner = select_runner(config)
    except BridgeError as exception:
        print(f"error: {exception}", file=sys.stderr)
        return 2
    counts = {"completed": 0, "failed": 0, "skipped": 0}
    for directory in directories:
        result = process_dispatch(directory, config, runner, args.retry_running)
        counts[result] += 1

    print(
        "DeepSeek Harness bridge: "
        f"completed={counts['completed']} failed={counts['failed']} skipped={counts['skipped']}"
    )
    return 1 if counts["failed"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
