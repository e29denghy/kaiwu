# KAIWU website

Official bilingual website for [开物 KAIWU](https://github.com/e29denghy/kaiwu), a local-first, human-approved workbench for coordinating Agent Harnesses.

The site is deliberately read-only. The interactive approval demo changes browser presentation state only; it does not connect to a Harness, modify a repository, or execute commands.

## Local development

Requires Node.js 22.13 or newer.

```bash
npm install
npm run dev
```

## Validation

```bash
npm test
```

This builds the Cloudflare Worker-compatible output and verifies the rendered bilingual page, protocol links, social metadata, and removal of starter preview artifacts.

The Sites project identifier is stored in `.openai/hosting.json`. No deployment credential is stored in the repository.

## Static production export

The one-page site can also be exported for an Nginx-only production host. The export renders the Worker entry for the configured canonical URL, copies its hashed browser assets, verifies every local HTML asset reference, and writes release provenance to `static-dist/release.json`.

```bash
SITE_URL=https://kaiwu.denghy.cn \
KAIWU_RELEASE_SHA="$(git rev-parse HEAD)" \
npm run export:static
```

Use `deploy/nginx/kaiwu.denghy.cn.bootstrap.conf` while obtaining the first ACME certificate, then replace it with `deploy/nginx/kaiwu.denghy.cn.conf`. The final document root is the atomic symlink `/var/www/kaiwu.denghy.cn/current`.
