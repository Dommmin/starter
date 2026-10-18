#!/bin/sh
# Merge the latest starter into a client repository on a new branch.
#
# Run in a client repository that was created by cloning the starter (shared
# history). Fetches STARTER_REPOSITORY at STARTER_REF, creates the branch
# starter-sync/<version> from the current HEAD and merges the starter into it.
# Never pushes and never touches the current branch. Exit codes:
#   0  merged, branch ready to push (prints the branch name)
#   2  already up to date, nothing to do
#   3  conflicts: the merge is aborted, conflicted files are listed
#
# Usage: STARTER_REPOSITORY=https://github.com/owner/starter.git sh scripts/starter-sync.sh
set -eu

STARTER_REPOSITORY=${STARTER_REPOSITORY:?Set STARTER_REPOSITORY to the starter Git URL}
STARTER_REF=${STARTER_REF:-main}
REMOTE=starter

if git remote get-url "$REMOTE" >/dev/null 2>&1; then
    git remote set-url "$REMOTE" "$STARTER_REPOSITORY"
else
    git remote add "$REMOTE" "$STARTER_REPOSITORY"
fi

git fetch --no-tags "$REMOTE" "$STARTER_REF"
target="$REMOTE/$STARTER_REF"

if git merge-base --is-ancestor "$target" HEAD; then
    echo "Already up to date with $target."
    exit 2
fi

version=$(git show "$target:STARTER_VERSION" 2>/dev/null | tr -d '[:space:]' || true)
[ -n "$version" ] || version=$(git rev-parse --short "$target")
branch="starter-sync/$version"

git switch --create "$branch"

if ! git merge --no-ff --no-edit -m "chore(starter): merge starter $version" "$target"; then
    echo "Conflicts with the starter $version:"
    git diff --name-only --diff-filter=U
    git merge --abort
    git switch -
    git branch -D "$branch" >/dev/null
    exit 3
fi

echo "$branch"
