from __future__ import annotations

import json
import sys
import tempfile
import unittest
import uuid
from dataclasses import replace
from pathlib import Path


BRIDGE_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BRIDGE_ROOT))

from kaiwu_dsh_bridge import (  # noqa: E402
    BridgeConfig,
    BridgeError,
    DSH_RELEASE,
    HarnessRunResult,
    SDK_VERSION,
    build_prompt,
    dispatch_directories,
    event_payload,
    process_dispatch,
    select_runner,
)


class FakeRunner:
    transport = "test-runner"

    def __init__(self, result: HarnessRunResult | None = None) -> None:
        self.result = result
        self.calls: list[tuple[str, str, str]] = []

    def run(self, prompt: str, session_id: str, permission_mode: str) -> HarnessRunResult:
        self.calls.append((prompt, session_id, permission_mode))
        if self.result is None:
            raise AssertionError("runner must not be called")
        return self.result


class DeepSeekHarnessBridgeTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.outbox = self.root / "outbox"
        self.inbox = self.root / "events.jsonl"
        self.workspace = self.root / "workspace"
        self.sessions = self.root / "sessions"
        self.workspace.mkdir()
        self.outbox.mkdir()
        self.cordis = self.root / "cordis.yml"
        self.cordis.write_text("[]\n", encoding="utf-8")
        self.config = BridgeConfig(
            outbox=self.outbox,
            inbox=self.inbox,
            workspace=self.workspace,
            session_root=self.sessions,
            cordis=self.cordis,
        )

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def quest(self, dispatch_id: str, requires_write: bool = True) -> dict[str, object]:
        return {
            "schema": "kaiwu.quest/v1",
            "id": dispatch_id,
            "quest_id": 7,
            "attempt": 1,
            "title": "Add a health endpoint",
            "goal": "Expose a read-only health endpoint.",
            "project": {"id": 3, "name": "Alpha", "slug": "alpha"},
            "acceptance_criteria": ["GET /health returns 200"],
            "constraints": ["Do not deploy"],
            "verification": ["Run the feature test"],
            "risk_level": "low",
            "requires_write": requires_write,
            "approval": {"status": "approved", "approved_at": "2026-08-17T09:00:00Z"},
            "created_at": "2026-08-17T09:00:00Z",
        }

    def dispatch(self, quest: dict[str, object]) -> Path:
        directory = self.outbox / str(quest["id"])
        directory.mkdir()
        (directory / "quest.json").write_text(json.dumps(quest), encoding="utf-8")
        (directory / "status.json").write_text(
            json.dumps(
                {
                    "schema": "kaiwu.execution-status/v1",
                    "dispatch_id": quest["id"],
                    "status": "queued",
                    "updated_at": "2026-08-17T09:00:00Z",
                }
            ),
            encoding="utf-8",
        )
        return directory

    def events(self) -> list[dict[str, object]]:
        return [json.loads(line) for line in self.inbox.read_text(encoding="utf-8").splitlines()]

    def test_completed_dispatch_writes_started_and_completed_events(self) -> None:
        dispatch_id = str(uuid.uuid4())
        directory = self.dispatch(self.quest(dispatch_id))
        runner = FakeRunner(
            HarnessRunResult(
                session_id=f"kaiwu-{dispatch_id}",
                final_response="Implemented and verified.",
                finish_reason="completed",
            )
        )

        result = process_dispatch(directory, self.config, runner)

        self.assertEqual("completed", result)
        status = json.loads((directory / "status.json").read_text(encoding="utf-8"))
        self.assertEqual("completed", status["status"])
        self.assertEqual("workspace-write", runner.calls[0][2])
        self.assertIn("GET /health returns 200", runner.calls[0][0])
        events = self.events()
        self.assertEqual(["execution.started", "execution.completed"], [item["type"] for item in events])
        self.assertEqual(dispatch_id, events[1]["payload"]["dispatch_id"])
        self.assertEqual("Implemented and verified.", events[1]["payload"]["result"])
        self.assertEqual("test-runner", events[1]["adapter_extension"]["transport"])

    def test_read_only_quest_selects_read_only_sandbox_mode(self) -> None:
        dispatch_id = str(uuid.uuid4())
        quest = self.quest(dispatch_id, requires_write=False)
        directory = self.dispatch(quest)
        runner = FakeRunner(
            HarnessRunResult(f"kaiwu-{dispatch_id}", "Audit complete.", "max-tokens")
        )

        self.assertEqual("completed", process_dispatch(directory, self.config, runner))
        self.assertEqual("read-only", runner.calls[0][2])
        self.assertIn("Permission mode: read-only", build_prompt(quest, self.workspace))

    def test_unapproved_quest_is_rejected_without_starting_sdk(self) -> None:
        dispatch_id = str(uuid.uuid4())
        quest = self.quest(dispatch_id)
        quest["approval"] = {"status": "pending", "approved_at": None}
        directory = self.dispatch(quest)
        runner = FakeRunner()

        self.assertEqual("failed", process_dispatch(directory, self.config, runner))
        self.assertEqual([], runner.calls)
        status = json.loads((directory / "status.json").read_text(encoding="utf-8"))
        self.assertEqual("failed", status["status"])
        self.assertIn("not human-approved", status["error"])
        self.assertEqual(["execution.failed"], [item["type"] for item in self.events()])

    def test_terminal_dispatch_is_idempotently_skipped(self) -> None:
        dispatch_id = str(uuid.uuid4())
        directory = self.dispatch(self.quest(dispatch_id))
        status_path = directory / "status.json"
        status = json.loads(status_path.read_text(encoding="utf-8"))
        status["status"] = "completed"
        status_path.write_text(json.dumps(status), encoding="utf-8")

        self.assertEqual("skipped", process_dispatch(directory, self.config, FakeRunner()))
        self.assertFalse(self.inbox.exists())

    def test_cli_transport_uses_pinned_headless_command_and_permission_environment(self) -> None:
        executable = self.root / "fake-dsh"
        executable.write_text(
            "#!/bin/sh\n"
            'printf "%s|%s|%s" "$DSH_PERMISSION_MODE" "$DSH_HOME" "$3"\n',
            encoding="utf-8",
        )
        executable.chmod(0o755)
        runner = select_runner(replace(self.config, transport="cli", dsh_bin=executable))

        result = runner.run("Inspect only", "kaiwu-test", "read-only")

        self.assertEqual("completed", result.finish_reason)
        self.assertEqual("kaiwu-test", result.session_id)
        self.assertEqual(
            f"read-only|{self.sessions}|Inspect only",
            result.final_response,
        )

    def test_missing_dispatch_id_is_rejected_without_creating_a_directory(self) -> None:
        dispatch_id = str(uuid.uuid4())
        missing = self.outbox / dispatch_id

        with self.assertRaisesRegex(BridgeError, "dispatch directory does not exist"):
            dispatch_directories(self.outbox, dispatch_id)

        self.assertFalse(missing.exists())

    def test_event_versions_follow_the_selected_transport(self) -> None:
        dispatch_id = str(uuid.uuid4())
        quest = self.quest(dispatch_id)

        cli_event = event_payload(
            dispatch_id,
            "execution.started",
            "running",
            "Started",
            "Started through the npm CLI.",
            quest,
            {"transport": "npm-headless-cli"},
        )
        sdk_event = event_payload(
            dispatch_id,
            "execution.started",
            "running",
            "Started",
            "Started through the Python SDK.",
            quest,
            {"transport": "python-sdk-jsonrpc"},
        )

        self.assertEqual(DSH_RELEASE, cli_event["payload"]["dsh_release"])
        self.assertNotIn("sdk_version", cli_event["payload"])
        self.assertEqual(SDK_VERSION, sdk_event["payload"]["sdk_version"])


if __name__ == "__main__":
    unittest.main()
