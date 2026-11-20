#!/usr/bin/env bash
# PostToolUse hook: formats and checks with Mago the PHP or Twig file of app/ that Claude just wrote.
# Exit code 2 hands the findings back to Claude.
set -uo pipefail

file=$(jq -r '.tool_input.file_path // empty')
app="${CLAUDE_PROJECT_DIR:?}/app"

case "$file" in
    "$app"/vendor/* | "$app"/var/*) exit 0 ;;
    "$app"/*.php | "$app"/bin/console) commands='mago format "$1" && mago lint "$1" && mago analyze "$1"' ;;
    "$app"/*.twig) commands='mago twig:fmt "$1" && mago twig:lint "$1"' ;;
    *) exit 0 ;;
esac

cd "$app" || exit 0

# Entering the dev shell costs about a second, Mago itself a few milliseconds: use Mago directly when
# Claude Code runs inside the dev shell.
if command -v mago >/dev/null; then
    run=(sh -c "$commands" sh "${file#"$app/"}")
else
    run=(nix develop "$CLAUDE_PROJECT_DIR" -c sh -c "$commands" sh "${file#"$app/"}")
fi

if ! output=$("${run[@]}" 2>&1); then
    printf '%s\n' "$output" >&2
    exit 2
fi
