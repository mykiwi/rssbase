@AGENTS.md

## Claude Code specifics

- `forge/` (vision, stack, backlog, stories) is produced and read by the `/forge:*` skills. Update it through those skills rather than by hand.
- The conventions of each area are in `.claude/rules/` and load when a file of the area is opened: `config.md`,
  `translations.md`, `templates.md`, `stimulus.md`, `e2e.md`.
