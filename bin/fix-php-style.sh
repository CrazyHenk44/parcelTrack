#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

if [ ! -f "$ROOT_DIR/vendor/bin/php-cs-fixer" ]; then
  echo "php-cs-fixer not installed. Run: composer install --dev"
  exit 1
fi

echo "Running php-cs-fixer..."
"$ROOT_DIR/vendor/bin/php-cs-fixer" fix "$ROOT_DIR" --config="$ROOT_DIR/.php-cs-fixer.dist.php" --allow-risky=yes
echo "Done."
