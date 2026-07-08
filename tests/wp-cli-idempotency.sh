#!/usr/bin/env bash
set -euo pipefail

blueprint="${1:-beauty}"
wp agency blueprint import "$blueprint"
before_services="$(wp post list --post_type=agency_service --format=count)"
before_faq="$(wp post list --post_type=agency_faq --format=count)"
before_pages="$(wp post list --post_type=page --format=count)"
wp agency blueprint import "$blueprint"
test "$before_services" = "$(wp post list --post_type=agency_service --format=count)"
test "$before_faq" = "$(wp post list --post_type=agency_faq --format=count)"
test "$before_pages" = "$(wp post list --post_type=page --format=count)"
echo "Idempotency OK: $blueprint"

