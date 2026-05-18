#!/bin/bash
# Run ONCE on Azure App Service (WordPress) via SSH/Kudu console.
# cd /home/site/wwwroot && bash /path/to/update-rankmath-seo.sh
#
# Requires WP-CLI (usually present on WordPress App Service images).
# Updates Rank Math SEO title + meta description + focus keyword only.

set -euo pipefail

WP_ROOT="${WP_ROOT:-/home/site/wwwroot}"
cd "$WP_ROOT"

if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: wp (WP-CLI) not found. Run from WordPress wwwroot or install WP-CLI."
  exit 1
fi

FOCUS="plumbers bolton"

update_page() {
  local id="$1"
  local title="$2"
  local desc="$3"
  echo "Updating page ID $id ..."
  wp post meta update "$id" rank_math_focus_keyword "$FOCUS" --allow-root 2>/dev/null || true
  wp post meta update "$id" rank_math_title "$title" --allow-root
  wp post meta update "$id" rank_math_description "$desc" --allow-root
  echo "  title: $title"
  echo "  done."
}

# Homepage (front page) — ID from live site API
update_page 25420 \
  "Plumbers Bolton | Gas Safe Heating & Boiler Repairs | GASCON Ltd" \
  "Plumbers Bolton — GASCON Ltd offers Gas Safe plumbing, boiler repairs, servicing and central heating across Bolton. 5-star local reviews. Call 07828 623 767."

# Plumbing Bolton service page
update_page 27246 \
  "Plumbers Bolton | Plumbing Services | GASCON Ltd" \
  "Plumbers Bolton — expert plumbing repairs and installations from GASCON Ltd. Gas Safe registered, local 5-star reviews. Free quote: 07828 623 767."

# Boiler repairs Bolton
update_page 27069 \
  "Boiler Repairs Bolton | Plumbers & Gas Engineers | GASCON Ltd" \
  "Plumbers Bolton and boiler repair specialists — fast Gas Safe service in Bolton. Breakdowns, servicing and replacements. Call 07828 623 767."

echo ""
echo "Clear any cache plugin after this, then re-check Rank Math in Elementor."
echo "For H2/body/alt-text Rank Math checks, run: wp eval-file fix-rankmath-content.php all --allow-root"
