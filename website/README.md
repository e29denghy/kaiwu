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
