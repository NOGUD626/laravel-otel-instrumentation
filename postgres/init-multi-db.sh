#!/bin/bash
# 複数 DB を作成 (POSTGRES_MULTIPLE_DATABASES でカンマ区切り)
set -e

if [ -n "$POSTGRES_MULTIPLE_DATABASES" ]; then
  for db in $(echo "$POSTGRES_MULTIPLE_DATABASES" | tr ',' ' '); do
    # デフォルト DB (POSTGRES_DB) 以外を作る
    if [ "$db" != "$POSTGRES_DB" ]; then
      echo "Creating database: $db"
      psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<EOSQL
        CREATE DATABASE $db;
        GRANT ALL PRIVILEGES ON DATABASE $db TO $POSTGRES_USER;
EOSQL
    fi
  done
fi
