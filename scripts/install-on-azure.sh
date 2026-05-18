#!/bin/bash
# Run this ONCE inside Azure App Service SSH (WordPress wwwroot).
# Downloads fix-rankmath-content.php from GitHub and runs it on all key pages.

set -euo pipefail

cd /home/site/wwwroot

# Pin to a commit SHA if GitHub CDN serves stale main (see repo commits).
URL="https://raw.githubusercontent.com/gasconltd/gasconltd-site/main/scripts/fix-rankmath-content.php"

echo "Downloading script..."
curl -fsSL -o fix-rankmath-content.php "$URL"
chmod 644 fix-rankmath-content.php
ls -la fix-rankmath-content.php

if ! command -v wp >/dev/null 2>&1; then
  echo "Downloaded OK. WP-CLI not in PATH — run manually:"
  echo "  wp eval-file fix-rankmath-content.php all --allow-root"
  exit 0
fi

echo "Running Rank Math content fix..."
wp eval-file fix-rankmath-content.php all --allow-root

echo ""
echo "Done. Clear cache, then Elementor → Update → refresh Rank Math SEO score."
