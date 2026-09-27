#!/usr/bin/env bash
# Restore and restore drill for backups created by backup.sh.
#
# Usage:
#   restore.sh --drill [BACKUP]           restore into a temporary database and
#                                         directory, verify, measure, clean up
#   restore.sh --restore BACKUP --target-db NAME --storage-target DIR
#                                         restore into a NEW database and an empty
#                                         directory (never overwrites live data;
#                                         switching the application is an
#                                         operator step, see doc 10)
#
# BACKUP is a backup directory (default for --drill: newest complete backup in
# BACKUP_DIR). The drill verifies SHA-256 checksums from manifest.json,
# pg_restore exit status, the number of tables, exact row counts per table and
# the number of storage files, and reports the elapsed time (RTO of the data
# restore). The result is written as JSON to BACKUP/drill-<UTC>.json.
#
# Environment: PGHOST PGPORT PGUSER PGPASSWORD (role allowed to CREATE/DROP
# DATABASE), BACKUP_DIR, BACKUP_AGE_IDENTITY (age key file) or a gpg keyring
# for encrypted backups, DRILL_STRICT_ROWS=1 (default) fails on row count
# differences; set 0 for backups of a busy database (differences reported).
set -Eeuo pipefail
umask 077

readonly SCRIPT_NAME=restore
DRILL_STRICT_ROWS=${DRILL_STRICT_ROWS:-1}

log() {
    local level=$1 event=$2 message=$3
    printf '{"time":"%s","level":"%s","service":"%s","event":"%s","message":"%s"}\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$level" "$SCRIPT_NAME" "$event" "$message" >&2
}

fail() {
    log critical ops.restore.failed "$1"
    exit 1
}

mode=''
backup=''
target_db=''
storage_target=''
while [ $# -gt 0 ]; do
    case "$1" in
        --drill) mode=drill; shift; if [ $# -gt 0 ] && [ "${1#--}" = "$1" ]; then backup=$1; shift; fi ;;
        --restore) mode=restore; backup=${2:-}; shift 2 || fail '--restore needs a backup directory' ;;
        --target-db) target_db=${2:-}; shift 2 || fail '--target-db needs a name' ;;
        --storage-target) storage_target=${2:-}; shift 2 || fail '--storage-target needs a directory' ;;
        *) fail "unknown argument: $1" ;;
    esac
done
[ -n "$mode" ] || fail 'use --drill [BACKUP] or --restore BACKUP --target-db NAME --storage-target DIR'

for tool in pg_restore psql createdb dropdb tar sha256sum; do
    command -v "$tool" >/dev/null 2>&1 || fail "missing tool: $tool"
done

if [ -z "$backup" ]; then
    [ -n "${BACKUP_DIR:-}" ] || fail 'BACKUP_DIR is required when no backup is given'
    backup=$(find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name 'starter-*' ! -name '*.partial' | sort -r | head -n 1)
    [ -n "$backup" ] || fail "no complete backup in $BACKUP_DIR"
fi
[ -f "$backup/manifest.json" ] || fail "missing manifest: $backup/manifest.json"

started_ns=$(date +%s%N)
work_dir=$(mktemp -d)
drill_db=''
cleanup() {
    if [ -n "$drill_db" ]; then
        dropdb --if-exists "$drill_db" >/dev/null 2>&1 || log error ops.restore.cleanup "could not drop $drill_db"
    fi
    rm -rf -- "$work_dir"
}
trap cleanup EXIT

# Minimal JSON reading without jq (not present on every host): the manifest
# is produced by backup.sh with a fixed layout; psql parses it reliably.
manifest=$(tr -d '\n' < "$backup/manifest.json")
# psql interpolates :'var' only in scripts read from stdin, never in -c.
json_query() {
    printf '%s;\n' "$1" | psql --no-psqlrc --tuples-only --no-align --quiet --set ON_ERROR_STOP=1 \
        --dbname=postgres --set manifest="$manifest"
}

# 1. Checksums of every file listed in the manifest.
checksum_errors=0
while IFS='|' read -r file expected; do
    [ -n "$file" ] || continue
    [ -f "$backup/$file" ] || { log error ops.restore.checksum "missing $file"; checksum_errors=$((checksum_errors + 1)); continue; }
    actual=$(sha256sum "$backup/$file" | cut -d ' ' -f 1)
    if [ "$actual" != "$expected" ]; then
        log error ops.restore.checksum "checksum mismatch for $file"
        checksum_errors=$((checksum_errors + 1))
    fi
done < <(json_query "select key || '|' || (value->>'sha256') from json_each(:'manifest'::json->'files')")
[ "$checksum_errors" -eq 0 ] || fail "$checksum_errors checksum error(s) in $backup"

# 2. Decrypt into the work directory when needed.
encryption=$(json_query "select :'manifest'::json->>'encryption'")
materialize() {
    local file=$1
    case "$encryption" in
        none) printf '%s\n' "$backup/$file" ;;
        age)
            [ -n "${BACKUP_AGE_IDENTITY:-}" ] || fail 'BACKUP_AGE_IDENTITY is required for age backups'
            age --decrypt --identity "$BACKUP_AGE_IDENTITY" --output "$work_dir/$file" "$backup/$file.age"
            printf '%s\n' "$work_dir/$file"
            ;;
        gpg)
            gpg --batch --yes --output "$work_dir/$file" --decrypt "$backup/$file.gpg"
            printf '%s\n' "$work_dir/$file"
            ;;
        *) fail "unsupported encryption: $encryption" ;;
    esac
}
dump_file=$(materialize db.dump)
storage_file=$(materialize storage.tar.gz)

# 3. Database restore into a database that must not exist yet.
if [ "$mode" = drill ]; then
    target_db="restore_drill_$(date -u +%Y%m%d%H%M%S)_$$"
    drill_db=$target_db
else
    [ -n "$target_db" ] || fail '--target-db is required'
    [ -n "$storage_target" ] || fail '--storage-target is required'
    [[ "$target_db" =~ ^[a-z_][a-z0-9_]{0,62}$ ]] || fail 'target database name must match [a-z_][a-z0-9_]*'
fi
exists=$(printf '%s\n' "select 1 from pg_database where datname = :'name';" \
    | psql --no-psqlrc --tuples-only --no-align --quiet --set ON_ERROR_STOP=1 --dbname=postgres --set name="$target_db")
[ -z "$exists" ] || fail "database $target_db already exists; refusing to overwrite"

createdb "$target_db"
pg_restore --exit-on-error --no-owner --no-privileges --dbname="$target_db" "$dump_file"
db_restored_ns=$(date +%s%N)

# 4. Verify tables and exact row counts against the manifest.
restored_counts=$(psql --no-psqlrc --tuples-only --no-align --quiet --set ON_ERROR_STOP=1 --dbname="$target_db" <<'SQL'
SELECT coalesce(json_object_agg(t.table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', t.table_schema, t.table_name), false, true, '')))[1]::text::bigint), '{}'::json)
FROM information_schema.tables t
WHERE t.table_schema = 'public' AND t.table_type = 'BASE TABLE';
SQL
)
comparison=$(psql --no-psqlrc --tuples-only --no-align --quiet --set ON_ERROR_STOP=1 --dbname=postgres \
    --set manifest="$manifest" --set restored="$restored_counts" <<'SQL'
WITH expected AS (SELECT key, value::text::bigint AS n FROM json_each(:'manifest'::json->'tables')),
     actual AS (SELECT key, value::text::bigint AS n FROM json_each(:'restored'::json))
SELECT (SELECT count(*) FROM expected) || '|' || (SELECT count(*) FROM actual) || '|' ||
       (SELECT coalesce(sum(n), 0) FROM actual) || '|' ||
       coalesce((SELECT string_agg(coalesce(e.key, a.key) || ':' || coalesce(e.n::text, '-') || '->' || coalesce(a.n::text, '-'), ',' ORDER BY coalesce(e.key, a.key))
                 FROM expected e FULL JOIN actual a ON a.key = e.key
                 WHERE e.n IS DISTINCT FROM a.n), '')
SQL
)
IFS='|' read -r expected_tables restored_tables restored_rows row_differences <<< "$comparison"

# 5. Storage: extract into an empty directory and count files.
if [ "$mode" = drill ]; then
    storage_target="$work_dir/storage"
fi
if [ -e "$storage_target" ] && [ -n "$(ls -A "$storage_target" 2>/dev/null)" ]; then
    fail "storage target $storage_target is not empty; refusing to overwrite"
fi
mkdir -p "$storage_target"
tar -xzf "$storage_file" -C "$storage_target" --no-same-owner
restored_files=$(find "$storage_target" -type f | wc -l | tr -d ' ')
expected_files=$(json_query "select :'manifest'::json->>'storage_files'")
finished_ns=$(date +%s%N)

elapsed_ms=$(((finished_ns - started_ns) / 1000000))
db_ms=$(((db_restored_ns - started_ns) / 1000000))

status=passed
problems=()
[ "$expected_tables" = "$restored_tables" ] || problems+=("tables $expected_tables->$restored_tables")
[ "$expected_files" = "$restored_files" ] || problems+=("storage files $expected_files->$restored_files")
if [ -n "$row_differences" ]; then
    if [ "$DRILL_STRICT_ROWS" = 1 ]; then
        problems+=("rows $row_differences")
    else
        log warning ops.restore.rows "row count differences (non-strict): $row_differences"
    fi
fi
[ "${#problems[@]}" -eq 0 ] || status=failed

result=$(cat <<JSON
{
  "mode": "$mode",
  "backup": "$(basename "$backup")",
  "status": "$status",
  "checked_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "checksums": "ok",
  "tables_expected": $expected_tables,
  "tables_restored": $restored_tables,
  "rows_restored": $restored_rows,
  "row_differences": "$row_differences",
  "storage_files_expected": $expected_files,
  "storage_files_restored": $restored_files,
  "database_restore_ms": $db_ms,
  "total_ms": $elapsed_ms
}
JSON
)
printf '%s\n' "$result"

if [ "$mode" = drill ] && [ -w "$backup" ]; then
    printf '%s\n' "$result" > "$backup/drill-$(date -u +%Y%m%dT%H%M%SZ).json"
fi

if [ "$status" != passed ]; then
    fail "verification failed: ${problems[*]}"
fi
if [ "$mode" = restore ]; then
    log info ops.restore.completed "restored into database $target_db and $storage_target; the application still uses its configured database"
else
    log info ops.restore.drill_passed "drill passed in ${elapsed_ms} ms"
fi
