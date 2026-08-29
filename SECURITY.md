# Security policy

## Supported version

Security fixes currently target the latest `main` branch.

## Report a vulnerability

Please use GitHub private vulnerability reporting when enabled. Do not open a public issue containing credentials, local paths, private repository content, or an exploit that can execute unapproved commands.

## Deployment warning

KAIWU v0.1 is local-first and does not include authentication. Bind the development server to localhost only. Add authentication, authorization, CSRF review, encrypted secret storage, and an explicit network threat model before any shared or internet-facing deployment.

Harness bridges should run with the least filesystem and network permission possible. KAIWU's approval record does not replace the receiving Harness's sandbox or external-write confirmation.

The DeepSeek Harness Preview bridge must remain a separately supervised process. It maps read-only Quests to `read-only` and write-capable Quests to `workspace-write`. Auto mode uses the pinned npm CLI and DSH's headless profile without an interactive answerer. Explicit SDK compatibility mode requests KAIWU's sandbox plugins with approval policy `never` and must abort if the published runtime cannot load them. Both fail closed on unattended escalation. Do not replace either path with `danger-full-access`, expose the bridge through an HTTP controller, or store `DEEPSEEK_API_KEY` in KAIWU connection JSON or Quest text.
