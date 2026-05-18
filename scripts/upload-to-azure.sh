#!/bin/bash
# Upload fix-rankmath-content.php to Azure App Service via Kudu VFS API.
#
# Get credentials: Azure Portal → App Service → Deployment Center → FTPS credentials
#   (or Deployment credentials / publish profile).
#
# Usage:
#   export AZURE_APP_NAME="your-app-name"          # without .azurewebsites.net
#   export AZURE_PUBLISH_USER="..."                # often \$app-name or custom
#   export AZURE_PUBLISH_PASSWORD="..."
#   bash scripts/upload-to-azure.sh
#
# Then SSH and run:
#   cd /home/site/wwwroot && wp eval-file fix-rankmath-content.php all --allow-root

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="${SCRIPT_DIR}/fix-rankmath-content.php"
APP="${AZURE_APP_NAME:?Set AZURE_APP_NAME (e.g. gasconltd-wp)}"
USER="${AZURE_PUBLISH_USER:?Set AZURE_PUBLISH_USER}"
PASS="${AZURE_PUBLISH_PASSWORD:?Set AZURE_PUBLISH_PASSWORD}"

if [[ ! -f "$SOURCE" ]]; then
  echo "Missing $SOURCE"
  exit 1
fi

KUDU="https://${APP}.scm.azurewebsites.net/api/vfs/site/wwwroot/fix-rankmath-content.php"

echo "Uploading to ${APP} ..."
http_code=$(curl -sS -w "%{http_code}" -o /tmp/kudu-upload-response.txt -X PUT \
  -u "${USER}:${PASS}" \
  -H "Content-Type: application/octet-stream" \
  --data-binary @"${SOURCE}" \
  "${KUDU}")

if [[ "$http_code" != "200" && "$http_code" != "201" && "$http_code" != "204" ]]; then
  echo "Upload failed (HTTP ${http_code}). Response:"
  cat /tmp/kudu-upload-response.txt
  exit 1
fi

echo "OK: Uploaded to /home/site/wwwroot/fix-rankmath-content.php"
echo ""
echo "Next (Azure SSH):"
echo "  cd /home/site/wwwroot"
echo "  wp eval-file fix-rankmath-content.php all --allow-root"
