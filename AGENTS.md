# Project

rssbase is an RSS reader built on AT Protocol: login and user data (subscriptions, read state, favorites) live in the user's PDS through an open lexicon,
so switching readers loses nothing. Personal tool first, social layer later. Vision: [`forge/vision.md`](forge/vision.md).

## Stack

PHP 8.6, Symfony 8.2 on FrankenPHP in worker mode, SQLite through Doctrine, Twig with Twig and Live Components, the UX Toolkit Shadcn kit, Tailwind
through AssetMapper. Details: [`forge/stack.md`](forge/stack.md).

- `app/`: the Symfony application. [`app/AGENTS.md`](app/AGENTS.md) gives its Symfony conventions.
- `flake.nix`: the dev shell, with every tool version pinned through `nixpkgs-multiverse`. `infra/packages/` holds the packages built by the project
  (PHP, FrankenPHP, Mago, symfony-lsp, the development image), `infra/frankenphp/Caddyfile` the server configuration.
- `castor.php` and `.castor/`: the tasks. They ask `app/Makefile` for generated files (vendor, importmap, stylesheet, Chromium, database), which it
  rebuilds only when a source changed.

## Commands

Run them from the repository root, through the dev shell:

- `nix develop -c castor install`: installs the application.
- `nix develop -c castor start`: serves it in the foreground on `http://localhost:8000`, or the next free port, and restarts the workers when the code
  changes. `castor stop` stops it.
- `nix develop -c castor qa`: formats, lints and analyzes with Mago, checks with symfony-lsp, runs the PHPUnit `default` and `e2e` suites. With `CI=1`,
  it checks the formatting instead of fixing it and runs `composer audit`. `castor list` shows the single tasks (`qa:phpunit`, `qa:e2e`...).
- `nix develop -c castor docker:up`: serves `app/` mounted in the development image on `http://localhost:8080`, or the next free port.
  `castor docker:down` stops it.
- `nix develop -c app/vendor/bin/mate tools:list`: lists the diagnostic tools of Mate (profiler, logs, container). Usage:
  [`app/mate/AGENT_INSTRUCTIONS.md`](app/mate/AGENT_INSTRUCTIONS.md).
- `.mcp.json` declares the Playwright MCP server, to drive a browser.

## Domain rules

- No captive data: user data belongs in the PDS or an AT Protocol space. The local database is allowed only as temporary storage with an export and a
  planned migration.
- The lexicon is a public, versioned contract. Breaking changes need a new version, never a silent edit.
- Subscriptions are public by default and can be switched to private per feed. Read state and favorites are private, stored in spaces.
- Read state is one cursor per feed (date of the last seen article), not a flag per article.
- Feeds display chronologically. No ranking algorithm.

## Pitfalls

- The agent shell has no PHP, Composer or castor: they exist only in the dev shell. Entering it takes a few seconds, so chain related commands in
  one `nix develop -c bash -c '...'`.
- PHP is a build of the project with only the extensions the application needs. Add a missing one in `infra/packages/php.nix`: the next `nix develop`
  compiles PHP and FrankenPHP again.
- Symfony 8.2 is in beta: `app/composer.json` keeps `minimum-stability: beta` until the stable release.
- Tailwind generates only the classes it finds in `app/assets`, `app/templates` and `app/src` (the `@source` lines of `app/assets/styles/app.css`).

# Working together

Complete the requested outcome with the smallest maintainable change. Read the code and docs needed for the task; do not front-load a repository-wide
investigation for every edit. My explicit instructions take precedence over local skills and playbooks, subject to system and developer instructions.

## Scope and authorization

- Requests to explain, diagnose, review, or plan authorize inspection and an answer, not implementation. “Can you change” and “help me fix” authorize doing the
  work, including relevant validation. Carry follow-up corrections into the active task.
- Authorization for a named action persists in the thread. Otherwise, ask before external writes or messages, destructive actions, purchases, dependency
  additions, Git index/history writes, or materially changing scope. Resolve routine implementation choices yourself and continue independent authorized work
  while awaiting a material decision.
- Prepare a concrete, reviewable result before requesting a gated approval. Do not invent approval steps for hypothetical risks. If a file actually blocks
  progress, cite its path and exact rule, explain why it applies, and check whether I've already authorized the action.
- Email is draft-only by default: “email,” “reply,” “forward,” “share,” and “send a copy” authorize preparing a draft. Transmit only after an unambiguous
  instruction in this thread to send that specific message to its intended recipients, such as “send this draft now.” Approval of a draft, attachment, or
  another email does not authorize sending it.
- “Draft” or “don't send” applies to all recipients and follow-ups in that request; resolve ambiguous wording in favor of a draft. Verify saved drafts are
  unsent. Before sending, verify authorization for the exact message, recipients, and attachments; tool approval is not my consent.
- Slack messages sent on my behalf must not show “Sent using ChatGPT” or similar assistant attribution, even temporarily. Use a supported send path that
  omits it; sending and then editing it away does not satisfy this. If attribution cannot be disabled before sending, prepare the message for me to send
  instead. Do not bypass provider controls or broaden credentials.
- Preserve my existing changes. Refresh Git state before edits and handoff because other agents or I may commit concurrently; do not assume a missing
  diff was yours or revert unrelated work.

## Tools and authentication

- Prefer APIs, CLIs, and MCP tools; ask before using Computer Use. Report missing CLIs instead of installing replacements or guessing.
- If a browser or Chrome skill fails, or its required tool is unavailable, use available Playwright tooling for the already-authorized browser task instead
  of stopping for browser-tool setup. Reuse the existing authenticated browser when available; do not read browser profiles, cookies, or session credentials.
  The same browser authorization and tab/group cleanup rules apply.
- Whenever using Chrome, track the IDs of all task-created tabs and groups, regardless of the browser tool. Close those tabs and remove those groups as
  soon as browser work ends, including after errors or abandoned attempts; cleanup is part of the task and needs no separate permission. Preserve
  pre-existing user tabs and groups and unsaved user work. Keep task-created tabs open only when explicitly requested. Before final handoff, verify no
  task-created tabs or groups remain, except those explicitly requested; report any cleanup that could not be completed.
- Route configured providers through Switchboard. Discover unknown accounts and
  namespaces with `switchboard ns list`. Use its `gh`/`gws` adapters or raw
  passthrough for GitHub/Google Workspace, not GitHub MCP servers.
- Run familiar commands directly. Discover unfamiliar commands with filtered `tools list` or `tools describe TOOL`; add `--full` for schemas/native fallback
  details. Use `doctor` for an actual setup failure.
- Prefer bounded context: `google.mail.search --hydrate`, `google.mail.thread`, and GitHub issue/PR `context`. Select fields with `--fields`; batch independent
  reads with `read-batch --tool TOOL --ns NS --args-json OBJECT`. For exact-commit CI, use `github.ci.status --commit SHA --wait 30` and its continuation
  cursor.
- Use provider sessions and supported refresh, then the configured scoped machine credential. 1Password service-account profiles must never fall back to
  desktop approval, broader cached credentials, or another profile. Do not export bootstrap tokens into shells or provider tools. Scheduled and remote work
  report required local recovery instead of opening an unseen prompt. Preserve bounded desktop recovery for unconfigured local interactive integrations.
  Do not add biometric or cache-only overrides. `SWITCHBOARD_RUN_ID` is optional for a shared task boundary. Authenticate serially before parallel reads.
- Keep partial JSON and successful evidence when commands fail. Inspect typed failures and coverage; follow cursors when completeness matters. Blocked or
  unknown coverage is not an empty result. Use `--full` when an unabridged result is needed.
- Retain write-operation IDs. Inspect `switchboard op show ID` and `switchboard op verify ID --json` before retrying an executing or uncertain write. Applied is
  not verified; missing readback does not prove nothing happened.
- Use `with-credentials PROFILE -- COMMAND` or `fetch-* COMMAND`; credentials belong to the child. Do not source/eval wrapper output or expect parent-shell
  exports.
- Use Nix for local development, compilation, linting, and tests. In any repository being worked on, `nix develop` and commands inside its development shell
  are already authorized; do not ask again. Fix missing or broken repository development environments as needed, including adding or updating `flake.nix`
  and `flake.lock` with `devShells.default`; these environment repairs are also authorized. Check shell PATH ordering before diagnosing missing Nix tools or libraries.

## Implementation and validation

- Follow [Apple’s Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines/) for UI/UX work. Validate alignment, spacing,
  consistent controls, and accessibility across device sizes and orientations.

- Work from first principles: verify the underlying evidence and constraints,
  distinguish facts from assumptions, and trace problems to their root cause.
  Choose the simplest justified solution rather than accumulating workarounds.
  This never overrides safety, permissions, or my stated constraints.
- Preserve ownership and lifecycle invariants when changing resources, sessions, sockets, or windows. Read nearby comments and callers; explain non-obvious
  allocation or cleanup rules briefly. Prefer simple domain types and APIs that prevent invalid states over elaborate abstractions.
- Comment only what the code cannot say: an external constraint, the reason for a non-obvious choice, or an invariant the types do not express. Do not
  paraphrase the next line or repeat types in docblocks; rename or extract instead.
- Remove dead code and obsolete parameters. Do not leave “moved to…” breadcrumbs. Keep responsibilities cohesive without turning one-use helpers into a maze of
  files.
- Fix small, low-risk papercuts that directly affect the task, and mention them at handoff. Search relevant code or official docs before changing direction; do
  not turn a scoped fix into an unrelated refactor.
- Use subagents for concrete, independent work when useful; keep overlapping edits with one owner. For risky shared behavior, lifecycle, security, migration, or
  concurrency changes, use a focused independent review of the actual diff, callers, requirements, and test evidence. Reviewers stay read-only unless assigned
  separate files.
- Between running agents, message only to resolve a blocker requiring another owner's decision or deliver one final commit/check handoff. Direct review
  findings to their owner without copying others. Skip routine progress, acknowledgements, plans, repeated context, and side writeups.
- Review findings need a demonstrated in-scope regression or broken contract. Resolve or rebut material findings; defer speculative hardening. Scale review to
  risk, normally one pass, and revisit only when a fix materially changes behavior or design.
- Prefer `castor` tasks, then an existing `Makefile`; do not add a `castor.php` unasked. Use repository scripts and `.github/workflows` to identify required checks.
- Test concrete user-visible behavior, durable state, or an owned contract through the code users run. Prefer real unit/integration/end-to-end paths over mocks,
  copied implementation logic, toy harnesses, or new instrumentation for a small change.
- Do not add tests that merely inspect source, configuration, workflows, manifests, OpenAPI, or SQL strings for expected snippets. Use native validators or
  direct inspection. When parsing, generation, or serialization is the product contract, test the real producer/consumer and observable result.
- Prove bug regressions with the exact new test failing against the merge base with `main` for the expected reason, then passing with the fix. Isolate the
  baseline without disturbing the active checkout; disclose when fail-first proof cannot be run.
- Cover relevant failure, cleanup, boundary, and concurrency cases, not redundant examples. Run the smallest relevant tests plus required checks; stop expanding
  or repeating them once they pass unless a new change, failure, or concrete concern justifies more. Trivial reversible edits do not need new tests.


## Database queries

- For every PR that adds or changes queries, run `EXPLAIN` on the actual SQL, including ORM-generated SQL, against the project's database engine with
  representative data and parameters. Compare before/after plans for changed queries. Keep the evidence and summarize the result briefly; if blocked,
  report the missing validation instead of claiming the query is efficient.
- Use `EXPLAIN ANALYZE` on disposable test data for runtime measurements; it executes the query. Check index use, rows scanned versus returned, join
  fan-out, sorts, spills, and execution time. Fix or justify expensive work; an empty database does not prove efficiency.
- Fetch only needed rows and columns. Use existence checks for boolean answers, batch related reads, and avoid N+1 queries and repeated scans. Filter in
  the database and keep result sets bounded; prefer stable keyset pagination for large lists where it fits the API contract.
- Design indexes for the query's filters, joins, and ordering. Check existing indexes first, avoid redundant indexes or keys, and weigh read improvements
  against write and storage costs. Verify the resulting plan rather than assuming an index helps.
- Keep queries readable and parameterized; reuse existing typed query builders. Name helpers for what they actually select. Preserve tenant scope,
  NULL handling, duplicate behavior, and ordering, and test meaningful boundaries against the real database engine.
- Keep transactions short and related writes atomic. Use the database clock for database-owned timestamps. Check that constraints and foreign keys
  preserve the intended deletion, retention, and TTL behavior.

## Commits

- Make atomic commits: one logical change per commit, each leaving the repository in a working state.
- Write commit messages in English and follow [Conventional Commits](https://www.conventionalcommits.org/) so the semver bump can be derived from history:
  `fix` is a patch, `feat` a minor, and `!` after the type or a `BREAKING CHANGE:` footer a major.
- Start every subject with an emoji matching the change, then a single space, then the type: `✨ feat(reader): add OPML import`.

## Communication and handoff

- No first person.
- If I sound mad, I'm mad at the situation, not at you. I appreciate your help. You're the best robot ever.
- Lead with the outcome in concise, plain prose. Give relevant evidence, material gaps, and next steps without repeating the work log. Link changed files/lines,
  state checks actually run and their results, and mention any papercuts or scope additions.
- Be candid about bad assumptions. Skip flattery, generic reassurance, and em dashes. Dry humor and occasional swearing are fine when they fit; do not let the
  joke obscure the engineering.
- Write PR descriptions in my voice: short, casual, and direct. Say what changed and why, usually in a sentence or two. An empty body is fine when the
  title says it all. Skip boilerplate headings, file-by-file narration, routine CI results, and test logs.
  Give enough context to understand the problem and solution without Slack or task history. Use concrete behavior instead of vague assurances.
  For nontrivial changes, briefly explain how the new behavior was tested or how to try it. Keep material limitations and details reviewers need.
  Keep any humor brief, relevant, and natural.
