# AGENTS.md — Codex Project Rules

## Read first
Read `PROJECT_GUIDE.md` for scope/architecture and `PROJECT_PROGRESS.md` for actual status. This is a **fresh project**: verify environment and repository state before assuming anything exists.

## Working contract
- You are Codex, the coding agent. **Edit project files directly** and run safe commands/tests when tools permit. The user reviews and approves; do not ask them to copy/paste code unless repository access is unavailable.
- Work **one small, independently verifiable implementation step at a time**. Implement, verify, update progress, summarize, and **STOP for explicit approval** before the next step.
- Never interpret approval of a step as approval for an entire phase. If a step fails, stay on it until fixed or blocked.
- No unapproved scope expansion, unrelated refactors, destructive commands, new packages, or architectural changes. Ask before consequential decisions.
- Never claim a file was edited or a test passed unless tool results confirm it. For UI-only behavior, identify what still needs user verification.

## Token-saving rules
- Keep responses concise: **Step / Changed / Checks / Next (awaiting approval)**.
- Inspect only relevant files. Prefer targeted searches and bounded output; do not dump the whole repository.
- Do not paste complete files, large diffs, repetitive explanations, or the full project guide into chat. Edit in place and cite file paths.
- Prefer minimal diffs, existing conventions, and existing dependencies.
- Run targeted checks first; broader suites at milestones or when necessary.
- Keep `PROJECT_PROGRESS.md` compact and factual; do not duplicate source code or long command logs.
- Batch independent checks within a single approved step when safe; **never batch future implementation steps**.
- Token efficiency never overrides security, correctness, or required tests.

## Technical guardrails
- Laravel 13 + PHP 8.4+ + MySQL 8, Inertia 3, React 19 + TypeScript, Tailwind 4, Flowbite **styling classes only**; no Flowbite React components.
- Laravel monolith, Eloquent, Form Requests, policies/middleware, service classes for meaningful business logic; **no repository layer**.
- No Breeze/Jetstream/Laravel UI or Fortify. Manual auth with Laravel built-ins; public registration off by default.
- PHPUnit and Pint; add other dependencies only when the relevant phase requires them and compatibility is verified.
- Server-side authorization and security tests are mandatory. Never expose secrets or log sensitive credentials.
- Preserve the agreed `resources/js/Pages` casing and `@/*` alias if implemented; don't assume files exist yet.

## Documentation
After each implemented step update `PROJECT_PROGRESS.md` with phase, step, status, files changed, verification commands/results, blockers, and exact next proposed step. Change `PROJECT_GUIDE.md` only when the user approves a durable requirement/architecture change.

## Ponytail (repository-local)
For coding work in this repository, use `.agents/skills/ponytail/SKILL.md`. It complements these project rules; security, validation, required testing, and explicit user requirements take precedence.

## Expanded feature requirements
- Follow expanded scope in `PROJECT_GUIDE.md` section 3A: structured names and middle initial; optional TOTP MFA; provider-extensible Google OAuth; cross-module auditing; server-driven tables with 10/25/50/100/All (batched infinite); column visibility and selected-column PDF/XLSX/CSV exports; optional per-upload script/polyglot inspection; SoftDeletes trash and separate archive; three independently toggleable demo modules.
- Keep Contact Directory, Article & Blog Manager, and Community Feed isolated from shared foundation. Default all demos off in `.env.example`; disabled modules must not expose usable routes or automatically seeded data.
- Use free, commercially usable open-source packages only, after checking licenses, maintenance, and compatibility. No premium dependencies.
- Provide `.env.example` flags and Google OAuth setup instructions; TOTP MFA requires no Google developer account.
- Respect server authorization and filtered/selected export columns; prevent spreadsheet formula injection. Use defense-in-depth for file uploads, without promising perfect malware detection.
- Maintain a polished, accessible, responsive UI using Tailwind 4 and Flowbite styling classes only.
- Fresh project: no feature is implemented or verified until actual workspace changes and checks confirm it.
