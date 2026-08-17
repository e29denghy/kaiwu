import assert from "node:assert/strict";
import { access, readFile } from "node:fs/promises";
import test from "node:test";

async function render() {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}`);
  const { default: worker } = await import(workerUrl.href);

  return worker.fetch(
    new Request("https://kaiwu.example/", {
      headers: {
        accept: "text/html",
        host: "kaiwu.example",
        "x-forwarded-host": "kaiwu.example",
        "x-forwarded-proto": "https",
      },
    }),
    {
      ASSETS: {
        fetch: async () => new Response("Not found", { status: 404 }),
      },
    },
    {
      waitUntil() {},
      passThroughOnException() {},
    },
  );
}

test("server-renders the KAIWU bilingual landing page", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);

  const html = await response.text();
  assert.match(
    html,
    /<title>开物 KAIWU — Human-approved Agent Harness Workbench<\/title>/i,
  );
  assert.match(html, /让\s*<span lang="en">Agent<\/span>\s*执行/);
  assert.match(html, /把\s*<strong>决定权<\/strong>\s*留给人/);
  assert.match(html, /体验审批流程/);
  assert.match(html, /kaiwu\.event\/v1/);
  assert.match(html, /SDK PREVIEW ADAPTER/);
  assert.match(html, /0\.1\.0-rc\.6/);
  assert.match(html, /https:\/\/kaiwu\.example\/og\.png/);
  assert.doesNotMatch(html, /codex-preview|react-loading-skeleton/i);
});

test("ships project metadata and removes starter preview artifacts", async () => {
  const [page, layout, packageJson] = await Promise.all([
    readFile(new URL("../app/page.tsx", import.meta.url), "utf8"),
    readFile(new URL("../app/layout.tsx", import.meta.url), "utf8"),
    readFile(new URL("../package.json", import.meta.url), "utf8"),
  ]);

  assert.match(page, /开物 KAIWU/);
  assert.match(page, /useState<DemoStep>/);
  assert.match(layout, /generateMetadata/);
  assert.match(packageJson, /"name": "kaiwu-site"/);
  assert.doesNotMatch(packageJson, /react-loading-skeleton/);

  await Promise.all([
    assert.rejects(access(new URL("../app/_sites-preview", import.meta.url))),
    access(new URL("../public/og.png", import.meta.url)),
    access(new URL("../.openai/hosting.json", import.meta.url)),
  ]);
});
