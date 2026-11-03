#!/usr/bin/env bash
#
# The git commit script that runs on the server, for a site whose content is edited there.
#
# DRAFT. UNTESTED ON A REAL SERVER. It has only been run on a Mac against
# throwaway local repositories. Read Avoca's hosting notes before using it.
#
# A site gets its own copy at scripts/server-git.sh, written by
#
#   php please avoca:site:script
#
# and the site owns it from then on: change it if this site needs something
# different, and commit it. The add-on never reads the site's copy back, and
# php please avoca:site:check says when the add-on's copy has moved on.
#
# Run it from the site's root directory:
#
#   ./scripts/server-git.sh commit [--now]
#   ./scripts/server-git.sh sync
#   ./scripts/server-git.sh status
#
# commit  For cron, every minute. Commits content that Statamic left uncommitted
#         once nothing has changed for AVOCA_GIT_QUIET_MINUTES, then pushes the
#         server's commits when the remote can fast-forward. It never merges, so
#         it never changes code on the server. --now skips the quiet period.
# sync    For the deploy script, before the build. Commits all uncommitted
#         content, fetches, fast-forwards or merges the deploy branch, and pushes
#         the result. On a conflict it undoes the merge, pushes the server's
#         commits to a server-content/<date> branch and exits with an error.
# status  Prints the state. Changes nothing and does not use the network.
#
# Settings, all optional environment variables:
#
#   AVOCA_GIT_REMOTE          Remote name. Default: origin
#   AVOCA_GIT_BRANCH          Deploy branch. Default: the checked-out branch
#   AVOCA_GIT_QUIET_MINUTES   Minutes without changes before commit mode commits. Default: 10
#   AVOCA_GIT_LOCK_WAIT       Seconds sync waits for a running commit. Default: 120
#   AVOCA_PHP                 PHP binary that reads config/statamic/git.php. Default: php
#   AVOCA_GIT_PATHS           Colon-separated paths to use instead of the config
#                             (for testing, or for a site that cannot boot)
#   AVOCA_GIT_USER_NAME       Committer name when AVOCA_GIT_PATHS is set
#   AVOCA_GIT_USER_EMAIL      Committer email when AVOCA_GIT_PATHS is set
#   AVOCA_GIT_SUBJECT         Subject for what commit mode commits, such as the add-on's
#                             "Feedback: comment on /partners" when a comment is made.
#                             [BOT] is added when it is missing.
#                             Default: Content saved on the server [BOT]
#
# State lives in .git/avoca/, which git never commits: the lock, when
# uncommitted content was first seen, push failures, the last run, and the
# result of the last sync. The deploy scripts add the build status.

set -euo pipefail

# Run git's occasional automatic housekeeping in the foreground. In the background
# it would inherit the lock file and keep the lock held after this script ends.
export GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=gc.autoDetach GIT_CONFIG_VALUE_0=false

MODE="${1:-}"
FLAG="${2:-}"

REMOTE="${AVOCA_GIT_REMOTE:-origin}"
QUIET_MINUTES="${AVOCA_GIT_QUIET_MINUTES:-10}"
LOCK_WAIT="${AVOCA_GIT_LOCK_WAIT:-120}"
PHP_BIN="${AVOCA_PHP:-php}"
SUBJECT="${AVOCA_GIT_SUBJECT:-Content saved on the server [BOT]}"
case "$SUBJECT" in
    *"[BOT]") ;;
    *) SUBJECT="$SUBJECT [BOT]" ;;
esac
PUSH_RETRY_MINUTES=10

GIT_ENABLED=0
BOT_NAME=""
BOT_EMAIL=""
BRANCH=""
PATHS=()
DIRTY=()

# A remote's address can carry a token, as Ploi's https://<token>@github.com/... does, and a cron log or a status
# pasted into a chat must never show it. Everything this script prints passes through redact, and git's own messages
# from the network commands go through it too.
redact() {
    sed -E 's#([a-zA-Z][a-zA-Z0-9+.-]*://)[^/@[:space:]]+@#\1***@#g; s#(gh[opsur]_|github_pat_)[A-Za-z0-9_]{8,}#\1***#g'
}

say() {
    printf '%s  %s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$MODE" "$*" | redact
}

# Runs a git command that talks to the remote, passing its messages through redact and keeping its exit status.
git_net() {
    local output status=0
    output="$(git "$@" 2>&1)" || status=$?
    if [ -n "$output" ]; then
        printf '%s\n' "$output" | redact >&2
    fi
    return "$status"
}

fail() {
    say "FAILED: $*" >&2
    exit 1
}

when() {
    # Epoch seconds as a local time, with GNU date (Linux) or BSD date (macOS).
    date -d "@$1" '+%Y-%m-%d %H:%M' 2>/dev/null || date -r "$1" '+%Y-%m-%d %H:%M'
}

minutes_since() {
    echo $(( ($(date +%s) - $1) / 60 ))
}

case "$MODE" in
    commit | sync | status) ;;
    *)
        echo "Usage: server-git.sh commit [--now] | sync | status" >&2
        exit 64
        ;;
esac

TOP="$(git rev-parse --show-toplevel 2>/dev/null)" || fail "$(pwd) is not inside a git working copy."
cd "$TOP"
GIT_DIR="$(git rev-parse --absolute-git-dir)"
STATE="$GIT_DIR/avoca"
mkdir -p "$STATE"

operation_in_progress() {
    [ -e "$GIT_DIR/MERGE_HEAD" ] || [ -d "$GIT_DIR/rebase-merge" ] || [ -d "$GIT_DIR/rebase-apply" ]
}

check_branch() {
    local current
    current="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
    if [ -z "$current" ]; then
        fail "HEAD is detached. Check out the deploy branch by hand first."
    fi
    # The deploy branch: set explicitly, else as recorded by the last sync, else the checked-out branch.
    BRANCH="${AVOCA_GIT_BRANCH:-$(cat "$STATE/branch" 2>/dev/null || echo "$current")}"
    if [ "$current" != "$BRANCH" ]; then
        fail "The working copy is on '$current', not the deploy branch '$BRANCH'."
    fi
    if operation_in_progress; then
        fail "A merge or rebase is in progress in $TOP. Finish or abort it by hand."
    fi
}

take_lock() {
    # $1 is "wait" or "nowait". flock ships with Ubuntu. The mkdir fallback exists
    # only so the script can be tried on macOS, which has no flock.
    if command -v flock >/dev/null 2>&1; then
        exec 9>"$STATE/git.lock"
        if [ "$1" = nowait ]; then
            flock -n 9
        else
            flock -w "$LOCK_WAIT" 9
        fi
        return
    fi
    local waited=0
    until mkdir "$STATE/git.lock.d" 2>/dev/null; do
        if [ "$1" != wait ] || [ "$waited" -ge "$LOCK_WAIT" ]; then
            return 1
        fi
        sleep 1
        waited=$((waited + 1))
    done
    trap 'rmdir "$STATE/git.lock.d" 2>/dev/null || true' EXIT
}

php_config_reader() {
    cat <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'ENABLED=', config('statamic.git.enabled') ? '1' : '0', "\n";
echo 'NAME=', (string) config('statamic.git.user.name'), "\n";
echo 'EMAIL=', (string) config('statamic.git.user.email'), "\n";
foreach ((array) config('statamic.git.paths', []) as $path) {
    echo 'TRACK=', $path, "\n";
}
PHP
}

read_config() {
    # Reads statamic.git.enabled, the fallback git user and the tracked paths from
    # the site's own config, so config/statamic/git.php stays the one list.
    # Returns 1 instead of failing when $1 is "soft".
    PATHS=()
    if [ -n "${AVOCA_GIT_PATHS:-}" ]; then
        GIT_ENABLED=1
        BOT_NAME="${AVOCA_GIT_USER_NAME:-Avoca server}"
        BOT_EMAIL="${AVOCA_GIT_USER_EMAIL:-hosting@avoca.design}"
        local path old_ifs="$IFS"
        IFS=':'
        for path in $AVOCA_GIT_PATHS; do
            if [ -n "$path" ]; then
                PATHS+=("$path")
            fi
        done
        IFS="$old_ifs"
        return 0
    fi

    local output line
    if [ ! -f vendor/autoload.php ] || ! output="$(php_config_reader | "$PHP_BIN" 2>&1)"; then
        if [ -n "${output:-}" ]; then
            printf '%s\n' "$output" | tail -n 20 >&2
        fi
        if [ "${1:-}" = soft ]; then
            say "Warning: could not boot the site to read config/statamic/git.php."
            return 1
        fi
        fail "Could not boot the site to read config/statamic/git.php. Run composer install, or set AVOCA_GIT_PATHS."
    fi

    GIT_ENABLED=0
    BOT_NAME=""
    BOT_EMAIL=""
    while IFS= read -r line; do
        case "$line" in
            ENABLED=*) GIT_ENABLED="${line#ENABLED=}" ;;
            NAME=*) BOT_NAME="${line#NAME=}" ;;
            EMAIL=*) BOT_EMAIL="${line#EMAIL=}" ;;
            TRACK=*) PATHS+=("${line#TRACK=}") ;;
        esac
    done < <(printf '%s\n' "$output")
    if [ -z "$BOT_NAME" ]; then
        BOT_NAME="Avoca server"
    fi
    if [ -z "$BOT_EMAIL" ]; then
        BOT_EMAIL="hosting@avoca.design"
    fi
}

find_dirty() {
    # The tracked paths with uncommitted changes. Like Statamic, it skips paths
    # that do not exist and paths git ignores.
    DIRTY=()
    local path relative inside=0
    for path in ${PATHS[@]+"${PATHS[@]}"}; do
        if [ "${path#/}" != "$path" ] && [ -e "$path" ]; then
            # Resolve symlinks so the path compares with git's physical top level.
            path="$(cd "$(dirname "$path")" && pwd -P)/$(basename "$path")"
        fi
        case "$path" in
            "$TOP") relative="." ;;
            "$TOP"/*) relative="${path#"$TOP"/}" ;;
            /*) continue ;;
            *) relative="$path" ;;
        esac
        inside=$((inside + 1))
        if [ ! -e "$relative" ] && ! git ls-files --error-unmatch -- "$relative" >/dev/null 2>&1; then
            continue
        fi
        if [ -n "$(git status --porcelain --untracked-files=all -- "$relative")" ]; then
            DIRTY+=("$relative")
        fi
    done
    if [ "${#PATHS[@]}" -gt 0 ] && [ "$inside" -eq 0 ]; then
        say "Warning: none of the tracked paths from config/statamic/git.php are inside $TOP, so nothing can be committed."
    fi
}

file_mtime() {
    stat -c %Y "$1" 2>/dev/null || stat -f %m "$1"
}

newest_change() {
    # Modification time of the most recently changed uncommitted file.
    local newest=0 file mtime
    while IFS= read -r -d '' file; do
        if [ -f "$file" ]; then
            mtime="$(file_mtime "$file")"
            if [ "$mtime" -gt "$newest" ]; then
                newest="$mtime"
            fi
        fi
    done < <(git ls-files -z --modified --others --exclude-standard -- "${DIRTY[@]}")
    echo "$newest"
}

commit_dirty() {
    # $1 subject, $2 body. Commits only the tracked content paths, never other files.
    git add -A -- "${DIRTY[@]}"
    if git diff --cached --quiet -- "${DIRTY[@]}"; then
        rm -f "$STATE/dirty-since"
        return 0
    fi
    git -c "user.name=$BOT_NAME" -c "user.email=$BOT_EMAIL" \
        commit --quiet -m "$1" -m "$2" -- "${DIRTY[@]}"
    rm -f "$STATE/dirty-since"
    say "Committed $(git log -1 --format='%h "%s"'), $(git diff-tree --no-commit-id --name-only -r HEAD | wc -l | tr -d ' ') file(s)."
}

remote_ref() {
    echo "refs/remotes/$REMOTE/$BRANCH"
}

ahead_count() {
    if git rev-parse --verify --quiet "$(remote_ref)" >/dev/null; then
        git rev-list --count "$(remote_ref)..HEAD"
    else
        echo unknown
    fi
}

clear_push_failure() {
    rm -f "$STATE/push-failed-since" "$STATE/push-last-attempt" "$STATE/push-failed-output"
}

push_branch() {
    # Never forces. $1 "loud" prints git's output on every failure; otherwise only
    # when pushing starts to fail, so a stuck site does not fill the cron log.
    local output
    local status=0
    output="$(git push --porcelain "$REMOTE" "HEAD:refs/heads/$BRANCH" 2>&1)" || status=$?
    output="$(printf '%s\n' "$output" | redact)"
    if [ "$status" -eq 0 ]; then
        if [ -f "$STATE/push-failed-since" ]; then
            say "Pushing works again."
        fi
        clear_push_failure
        say "Pushed to $REMOTE/$BRANCH, which is now at $(git rev-parse --short HEAD)."
        return 0
    fi
    printf '%s\n' "$output" > "$STATE/push-failed-output"
    date +%s > "$STATE/push-last-attempt"
    if [ "${1:-}" = loud ] || [ ! -f "$STATE/push-failed-since" ]; then
        say "Push to $REMOTE/$BRANCH failed. Git said:"
        printf '%s\n' "$output" | sed 's/^/    /'
    fi
    if [ ! -f "$STATE/push-failed-since" ]; then
        date +%s > "$STATE/push-failed-since"
    fi
    return 1
}

warn_other_changes() {
    local changes
    changes="$(git status --porcelain --untracked-files=no)"
    if [ -n "$changes" ]; then
        say "Warning: these tracked files have uncommitted changes. If the incoming commits touch them, git stops the sync:"
        printf '%s\n' "$changes" | sed 's/^/    /'
    fi
}

undo_merge_and_fail() {
    if [ ! -e "$GIT_DIR/MERGE_HEAD" ]; then
        fail "Git refused to merge (its message is above). Nothing was changed."
    fi
    local conflicts rescue
    conflicts="$(git diff --name-only --diff-filter=U)"
    git merge --abort
    rescue="server-content/$(date +%Y%m%d-%H%M%S)"
    if git_net push --quiet "$REMOTE" "HEAD:refs/heads/$rescue"; then
        say "The server's commits are saved on the branch $rescue."
    else
        say "Could not push the branch $rescue. The server's commits are still on $BRANCH in $TOP."
    fi
    say "Files in conflict:"
    printf '%s\n' "$conflicts" | sed 's/^/    /'
    fail "The merge was undone, so the site keeps its current code and content. To fix it on your computer: git fetch, git merge origin/$rescue, resolve the conflicts, push $BRANCH, then deploy again."
}

cmd_commit() {
    if ! take_lock nowait; then
        exit 0 # A deploy is syncing, or the previous run is still going. Next minute.
    fi
    check_branch
    date +%s > "$STATE/last-run"

    if [ -z "$(git status --porcelain --untracked-files=all)" ]; then
        rm -f "$STATE/dirty-since"
    else
        read_config
        if [ "$GIT_ENABLED" != 1 ]; then
            exit 0
        fi
        find_dirty
        if [ "${#DIRTY[@]}" -eq 0 ]; then
            rm -f "$STATE/dirty-since"
        else
            local now since newest quiet
            now="$(date +%s)"
            quiet=$((QUIET_MINUTES * 60))
            if [ ! -f "$STATE/dirty-since" ]; then
                echo "$now" > "$STATE/dirty-since"
            fi
            since="$(cat "$STATE/dirty-since")"
            newest="$(newest_change)"
            if [ "$FLAG" = --now ] || { [ $((now - since)) -ge "$quiet" ] && [ $((now - newest)) -ge "$quiet" ]; }; then
                commit_dirty "$SUBJECT" \
                    "Committed by the Avoca server script because Statamic had not committed it."
            fi
        fi
    fi

    local ahead
    ahead="$(ahead_count)"
    if [ "$ahead" = 0 ]; then
        clear_push_failure
        exit 0
    fi
    if [ -f "$STATE/push-last-attempt" ] && [ "$FLAG" != --now ]; then
        if [ "$(minutes_since "$(cat "$STATE/push-last-attempt")")" -lt "$PUSH_RETRY_MINUTES" ]; then
            exit 1
        fi
    fi
    if ! push_branch; then
        if [ "$(minutes_since "$(cat "$STATE/push-failed-since")")" -lt 1 ]; then
            say "If the remote has commits this server does not, the next deploy merges them and pushes. Until then this script retries every $PUSH_RETRY_MINUTES minutes."
        fi
        exit 1
    fi
}

cmd_sync() {
    rm -f "$STATE/sync-result"
    if ! take_lock wait; then
        fail "Waited ${LOCK_WAIT}s for the server's commit script to finish. Is a run stuck?"
    fi
    check_branch
    echo "$BRANCH" > "$STATE/branch"

    if read_config soft; then
        if [ "$GIT_ENABLED" = 1 ]; then
            find_dirty
            if [ "${#DIRTY[@]}" -gt 0 ]; then
                commit_dirty "Content saved on the server before a deploy [BOT]" \
                    "Committed by the Avoca deploy so the merge includes it."
            fi
        else
            say "Git automation is off for this site (STATAMIC_GIT_ENABLED), so content is not committed."
        fi
    else
        say "Carrying on without committing content, so a broken site can still receive a fix."
    fi

    warn_other_changes

    local remote result=up-to-date attempt
    remote="$(remote_ref)"
    for attempt in 1 2 3; do
        if ! git_net fetch --quiet "$REMOTE" "+refs/heads/$BRANCH:$remote"; then
            fail "Could not fetch $REMOTE/$BRANCH. Check the server's key or connected account."
        fi

        if [ "$(git rev-parse HEAD)" = "$(git rev-parse "$remote")" ]; then
            :
        elif git merge-base --is-ancestor HEAD "$remote"; then
            if ! git merge --ff-only --quiet "$remote"; then
                fail "Could not fast-forward to $REMOTE/$BRANCH (git's message is above). Files changed on the server outside the content paths are the usual cause."
            fi
            say "Fast-forwarded to $(git log -1 --format='%h "%s"')."
            result=integrated
        elif git merge-base --is-ancestor "$remote" HEAD; then
            say "The server has $(git rev-list --count "$remote..HEAD") commit(s) that $REMOTE/$BRANCH does not."
        else
            say "The server and $REMOTE/$BRANCH both have new commits. Merging."
            if ! git -c "user.name=$BOT_NAME" -c "user.email=$BOT_EMAIL" merge --no-edit --quiet \
                -m "Merge $REMOTE/$BRANCH into content saved on the server [BOT]" "$remote"; then
                undo_merge_and_fail
            fi
            say "Merged as $(git rev-parse --short HEAD)."
            result=integrated
        fi

        if [ "$(git rev-list --count "$remote..HEAD")" -eq 0 ]; then
            break
        fi
        if push_branch loud; then
            break
        fi
        if [ "$attempt" -eq 3 ]; then
            fail "Could not push to $REMOTE/$BRANCH after 3 attempts."
        fi
        say "Fetching again in case the remote moved during the sync."
    done

    clear_push_failure
    echo "$result" > "$STATE/sync-result"
    say "Sync finished: $result."
}

cmd_status() {
    local current
    current="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || echo 'detached HEAD')"
    # The deploy branch: set explicitly, else as recorded by the last sync, else the checked-out branch.
    BRANCH="${AVOCA_GIT_BRANCH:-$(cat "$STATE/branch" 2>/dev/null || echo "$current")}"
    echo "Working copy      $TOP"
    echo "Branch            $current, at $(git log -1 --format='%h %s')"
    echo "Remote            $(git remote get-url "$REMOTE" 2>/dev/null | redact || echo "no remote called $REMOTE")"
    echo "Not yet pushed    $(ahead_count) commit(s), as of the last fetch or push"
    echo "Uncommitted       $(git status --porcelain --untracked-files=all | wc -l | tr -d ' ') path(s) in the working copy"
    if [ -f "$STATE/dirty-since" ]; then
        echo "Content waiting   since $(when "$(cat "$STATE/dirty-since")")"
    fi
    if [ -f "$STATE/push-failed-since" ]; then
        echo "Push failing      since $(when "$(cat "$STATE/push-failed-since")"). Last output:"
        redact < "$STATE/push-failed-output" 2>/dev/null | sed 's/^/    /' || true
    fi
    if [ -f "$STATE/last-run" ]; then
        echo "Commit script     last ran $(minutes_since "$(cat "$STATE/last-run")") minute(s) ago"
    else
        echo "Commit script     has never run in this working copy. Is the cron job set up?"
    fi
    if [ -f "$STATE/sync-result" ]; then
        echo "Last sync         $(cat "$STATE/sync-result")"
    fi
    if [ -f "$STATE/build-status" ]; then
        echo "Last build        $(cat "$STATE/build-status")"
    fi
    if command -v flock >/dev/null 2>&1; then
        exec 9>"$STATE/git.lock"
        if flock -n 9; then
            flock -u 9
            echo "Lock              free"
        else
            echo "Lock              held by a commit or sync that is running"
        fi
    elif [ -d "$STATE/git.lock.d" ]; then
        echo "Lock              held (mkdir lock)"
    fi
    if operation_in_progress; then
        echo "WARNING           a merge or rebase is in progress"
    fi
}

case "$MODE" in
    commit) cmd_commit ;;
    sync) cmd_sync ;;
    status) cmd_status ;;
esac
