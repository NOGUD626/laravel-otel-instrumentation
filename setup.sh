#!/bin/bash
set -euo pipefail

# === laravel-otel-lab 初回セットアップスクリプト ===

cd "$(dirname "$0")"

echo "==> 1. Laravel プロジェクトを作成 (初回のみ)"
if [ ! -f laravel/artisan ]; then
  docker run --rm -v "$PWD":/work -w /work composer:2.8 \
    create-project laravel/laravel laravel "11.*" --no-interaction --prefer-dist
  echo "   ✓ Laravel 11 をインストール"
else
  echo "   - laravel/ ディレクトリ既存、スキップ"
fi

echo "==> 2. OpenTelemetry / auto-laravel を composer.json に追加 (vendor 構築は PHP コンテナ内で行う)"
# ext-opentelemetry が composer image にないので --ignore-platform-req と --no-install で
# composer.json への追記だけにとどめる。実際の install は docker compose 起動後にコンテナ内で実行。
docker run --rm -v "$PWD/laravel":/app -w /app composer:2.8 \
  require --no-interaction --no-install \
    --ignore-platform-req=ext-opentelemetry \
    --ignore-platform-req=ext-otel_instrumentation \
    "open-telemetry/sdk" \
    "open-telemetry/exporter-otlp" \
    "open-telemetry/opentelemetry-auto-laravel" \
    "php-http/guzzle7-adapter"
# 注: transport-grpc は ext-grpc が必要なため外している。OTLP HTTP/protobuf を使う構成。

echo "==> 3. デモ用 routes / migration / seeder / Event / Job / Listener を配置"
cp -f stubs/web.php       laravel/routes/web.php
cp -f stubs/api.php       laravel/routes/api.php   2>/dev/null || true
mkdir -p laravel/database/migrations
cp -f stubs/migration_create_races.php \
   laravel/database/migrations/2026_01_01_000000_create_races_table.php
mkdir -p laravel/app/Models
cp -f stubs/Race.php  laravel/app/Models/Race.php
mkdir -p laravel/database/seeders
cp -f stubs/RaceSeeder.php     laravel/database/seeders/RaceSeeder.php
cp -f stubs/DatabaseSeeder.php laravel/database/seeders/DatabaseSeeder.php

# Event / Job / Listener (各カテゴリのデモ用)
mkdir -p laravel/app/Events laravel/app/Jobs laravel/app/Listeners
cp -f stubs/RaceRegistered.php       laravel/app/Events/RaceRegistered.php
cp -f stubs/SendWelcomeEmail.php     laravel/app/Jobs/SendWelcomeEmail.php
cp -f stubs/NotifyParticipants.php   laravel/app/Listeners/NotifyParticipants.php
cp -f stubs/AppServiceProvider.php   laravel/app/Providers/AppServiceProvider.php

echo "==> 4. Laravel の APP_KEY / .env を docker compose の environment と整合"
# .env は compose の environment で上書きされるので最小限でOK
cat > laravel/.env <<'EOF'
APP_NAME="Laravel OTel Lab"
APP_ENV=local
APP_KEY=base64:Hk5Y3qm7g8WJZqkgQ7Pl2KQrgUaDk8wPp4Yqj7BFr0o=
APP_DEBUG=true
APP_URL=http://localhost:8080

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
EOF

echo "==> 5. パーミッション調整"
mkdir -p laravel/storage/framework/{sessions,views,cache}
mkdir -p laravel/bootstrap/cache
chmod -R 777 laravel/storage laravel/bootstrap/cache

echo "==> 6. (keepsuit 版) laravel-v2 プロジェクトを作成"
if [ ! -f laravel-v2/artisan ]; then
  docker run --rm -v "$PWD":/work -w /work composer:2.8 \
    create-project laravel/laravel laravel-v2 "11.*" --no-interaction --prefer-dist
fi

echo "==> 7. laravel-v2 に keepsuit/laravel-opentelemetry を追加"
docker run --rm -v "$PWD/laravel-v2":/app -w /app composer:2.8 \
  require --no-interaction --no-install \
    --ignore-platform-req=ext-opentelemetry \
    "open-telemetry/sdk" \
    "open-telemetry/exporter-otlp" \
    "keepsuit/laravel-opentelemetry" \
    "php-http/guzzle7-adapter"

echo "==> 8. laravel-v2 にも同じデモコードを配置"
cp -f stubs/web.php       laravel-v2/routes/web.php
mkdir -p laravel-v2/database/migrations
cp -f stubs/migration_create_races.php \
   laravel-v2/database/migrations/2026_01_01_000000_create_races_table.php
mkdir -p laravel-v2/app/Models laravel-v2/app/Events laravel-v2/app/Jobs laravel-v2/app/Listeners
cp -f stubs/Race.php                  laravel-v2/app/Models/Race.php
cp -f stubs/RaceRegistered.php        laravel-v2/app/Events/RaceRegistered.php
cp -f stubs/SendWelcomeEmail.php      laravel-v2/app/Jobs/SendWelcomeEmail.php
cp -f stubs/NotifyParticipants.php    laravel-v2/app/Listeners/NotifyParticipants.php
cp -f stubs/AppServiceProvider.php    laravel-v2/app/Providers/AppServiceProvider.php
mkdir -p laravel-v2/database/seeders
cp -f stubs/RaceSeeder.php            laravel-v2/database/seeders/RaceSeeder.php
cp -f stubs/DatabaseSeeder.php        laravel-v2/database/seeders/DatabaseSeeder.php

echo "==> 9. laravel-v2 の .env"
cat > laravel-v2/.env <<'EOF'
APP_NAME="Laravel OTel Lab (keepsuit)"
APP_ENV=local
APP_KEY=base64:Hk5Y3qm7g8WJZqkgQ7Pl2KQrgUaDk8wPp4Yqj7BFr0o=
APP_DEBUG=true
APP_URL=http://localhost:8081

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=laravel_v2
DB_USERNAME=laravel
DB_PASSWORD=secret
EOF

mkdir -p laravel-v2/storage/framework/{sessions,views,cache}
mkdir -p laravel-v2/bootstrap/cache
chmod -R 777 laravel-v2/storage laravel-v2/bootstrap/cache

echo
echo "✓ セットアップ完了 (laravel/ = 公式 + laravel-v2/ = keepsuit)"
echo
echo "次のコマンドで起動:"
echo "  docker compose up -d --build                                # ビルド + 起動"
echo "  docker compose exec php    composer install                 # 公式版 vendor"
echo "  docker compose exec php-v2 composer install                 # keepsuit 版 vendor"
echo "  docker compose exec php-v2 php artisan vendor:publish --tag=opentelemetry-config  # keepsuit config"
echo "  docker compose exec php    php artisan migrate --seed --force"
echo "  docker compose exec php-v2 php artisan migrate --seed --force"
echo
echo "アクセス先:"
echo "  Laravel:  http://localhost:8080/"
echo "  Grafana:  http://localhost:3000/   (匿名 Admin)"
echo "  Tempo:    http://localhost:3200/   (HTTP API)"
