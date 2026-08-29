import { access, cp, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const projectRoot = fileURLToPath(new URL("../", import.meta.url));
const clientRoot = path.join(projectRoot, "dist/client");
const outputRoot = path.join(projectRoot, "static-dist");
const workerEntry = new URL("../dist/server/index.js", import.meta.url);
const siteUrl = new URL(process.env.SITE_URL ?? "https://kaiwu.denghy.cn");

if (siteUrl.protocol !== "https:") {
  throw new Error("SITE_URL must use HTTPS");
}

await access(path.join(clientRoot, "og.png"));
await rm(outputRoot, { recursive: true, force: true });
await mkdir(outputRoot, { recursive: true });
await cp(clientRoot, outputRoot, { recursive: true });

workerEntry.searchParams.set("static-export", `${process.pid}-${Date.now()}`);
const { default: worker } = await import(workerEntry.href);
const response = await worker.fetch(
  new Request(siteUrl, {
    headers: {
      accept: "text/html",
      host: siteUrl.host,
      "x-forwarded-host": siteUrl.host,
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

if (!response.ok) {
  throw new Error(`Static render failed with HTTP ${response.status}`);
}

const html = await response.text();
if (!html.includes("开物 KAIWU") || !html.includes("SDK PREVIEW ADAPTER")) {
  throw new Error("Static render is missing required KAIWU content");
}

await writeFile(path.join(outputRoot, "index.html"), html, "utf8");
await writeFile(
  path.join(outputRoot, "release.json"),
  `${JSON.stringify(
    {
      commit_sha: process.env.KAIWU_RELEASE_SHA?.trim() || null,
      canonical_url: siteUrl.origin,
      generated_at: new Date().toISOString(),
    },
    null,
    2,
  )}\n`,
  "utf8",
);

const localReferences = [
  ...html.matchAll(/(?:src|href)="(\/[^"]+)"/g),
].map((match) => new URL(match[1], siteUrl).pathname);

await Promise.all(
  [...new Set(localReferences)].map(async (pathname) => {
    const relativePath = pathname.replace(/^\/+/, "");
    if (relativePath === "") {
      return;
    }

    await access(path.join(outputRoot, relativePath));
  }),
);

const release = JSON.parse(
  await readFile(path.join(outputRoot, "release.json"), "utf8"),
);
console.log(
  `Exported ${html.length} HTML characters and ${localReferences.length} local asset references to ${outputRoot} for ${release.canonical_url}`,
);
