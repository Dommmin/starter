# MySQL helpers shared by backup.sh and restore.sh (sourced, not executed).
#
# Credentials come only from the environment: MYSQL_HOST (default
# 127.0.0.1), MYSQL_TCP_PORT (default 3306), MYSQL_USER, MYSQL_PASSWORD. They
# are written to a 0600 option file in a private temporary directory, so the
# password never appears in the process list. The caller provides fail() and
# removes the directory on exit.

mysql_cnf=''

# Write the client option file into directory $1.
mysql_prepare() {
    local dir=$1
    [ -n "${MYSQL_USER:-}" ] || fail 'MYSQL_USER is required'
    [ -n "${MYSQL_PASSWORD:-}" ] || fail 'MYSQL_PASSWORD is required'
    case "$MYSQL_PASSWORD" in
        *'"'* | *'\'* | *$'\n'*) fail 'MYSQL_PASSWORD must not contain quotes, backslashes or newlines' ;;
    esac
    mysql_cnf="$dir/client.cnf"
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\ndefault-character-set=utf8mb4\n' \
        "${MYSQL_HOST:-127.0.0.1}" "${MYSQL_TCP_PORT:-3306}" "$MYSQL_USER" "$MYSQL_PASSWORD" > "$mysql_cnf"
}

# mysql client in batch mode without column names; extra arguments follow.
mysql_cli() {
    mysql --defaults-extra-file="$mysql_cnf" --batch --skip-column-names --raw "$@"
}

# Exact row count of every base table of database $1 as a JSON object,
# the same shape as the PostgreSQL json_object_agg in backup.sh.
mysql_table_counts() {
    local database=$1 tables table count json=''
    tables=$(mysql_cli --database="$database" \
        -e "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")
    while IFS= read -r table; do
        [ -n "$table" ] || continue
        [[ "$table" =~ ^[A-Za-z0-9_]+$ ]] || fail "unsupported table name: $table"
        count=$(mysql_cli --database="$database" -e "SELECT COUNT(*) FROM \`$table\`")
        json+="${json:+, }\"$table\" : $count"
    done <<< "$tables"
    printf '{%s}\n' "${json:+ $json }"
}
