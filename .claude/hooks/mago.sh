#!/usr/bin/env bash
# PostToolUse hook: formats and checks with Mago the PHP or Twig file of app/ that Claude just wrote.
# Exit code 2 hands the findings back to Claude.
set -uo pipefail

# jq and Mago come from the dev shell. Entering it costs about a second and Mago itself a few
# milliseconds, so the hook enters it, once, only when Claude Code does not already run inside it.
if ! command -v mago >/dev/null && [ -z "${RSSBASE_MAGO_HOOK_IN_DEV_SHELL:-}" ]; then
    RSSBASE_MAGO_HOOK_IN_DEV_SHELL=1 exec nix develop "${CLAUDE_PROJECT_DIR:?}" -c "$0"
fi

file=$(jq -r '.tool_input.file_path // empty')
app="${CLAUDE_PROJECT_DIR:?}/app"

# The [source] paths of app/mago.toml: Mago checks a path given on the command line even outside them.
case "$file" in
    "$app"/src/*.php | "$app"/config/*.php | "$app"/public/*.php | "$app"/tests/*.php | "$app"/bin/console) kind=php ;;
    "$app"/templates/*.twig) kind=twig ;;
    *) exit 0 ;;
esac

cd "$app" || exit 0
relative=${file#"$app/"}

if [ "$kind" = php ]; then
    output=$({ mago format "$relative" && mago lint "$relative" && mago analyze "$relative"; } 2>&1)
else
    output=$({ mago twig:fmt "$relative" && mago twig:lint "$relative"; } 2>&1)
fi || {
    printf '%s\n' "$output" >&2
    exit 2
}
