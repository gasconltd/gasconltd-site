#!/usr/bin/env python3
"""Build index.html static snapshot from gasconltd.com (or a local HTML file)."""
import re
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "index.html"
FETCH_URL = "https://gasconltd.com/"


def load_html() -> str:
    if len(sys.argv) > 1 and not sys.argv[1].startswith("-"):
        path = Path(sys.argv[1])
        return path.read_text(encoding="utf-8", errors="replace")
    req = urllib.request.Request(
        FETCH_URL,
        headers={"User-Agent": "Mozilla/5.0 (compatible; GasconStaticMirror/1.0)"},
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        return resp.read().decode("utf-8", errors="replace")


def main() -> None:
    text = load_html()

    # Protocol-relative URLs break under file://; normalize to https
    text = re.sub(r"(?<!:)//gasconltd\.com/", "https://gasconltd.com/", text)
    text = re.sub(r"(?<!:)//fonts\.googleapis\.com", "https://fonts.googleapis.com", text)
    text = re.sub(r"(?<!:)//www\.googletagmanager\.com", "https://www.googletagmanager.com", text)
    text = re.sub(r"(?<!:)//gmpg\.org/", "https://gmpg.org/", text)

    # Elementor: don't strip background images when JS lazy-load never runs (static / file)
    text = re.sub(
        r"\s*<style>\s*\.e-con\.e-parent:nth-of-type\(n\+4\)[\s\S]*?</style>\s*",
        "\n",
        text,
        count=1,
    )

    # Revolution Slider: show hero image without waiting for RS lazy loader
    def _rev_img(m: re.Match) -> str:
        tag = m.group(0)
        lm = re.search(r'data-lazyload="([^"]+)"', tag)
        if lm:
            real = lm.group(1).replace("&#038;", "&")
            if real.startswith("//"):
                real = "https:" + real
            tag = re.sub(r'src="[^"]*"', f'src="{real}"', tag, count=1)
        return tag

    text = re.sub(r"<img[^>]*class=\"[^\"]*rev-slidebg[^\"]*\"[^>]*>", _rev_img, text)

    text = re.sub(
        r"<script[^>]*id=\"google_gtagjs-js-consent-mode-data-layer\"[\s\S]*?</script>\s*",
        "",
        text,
        count=1,
    )
    text = re.sub(
        r"<script type=\"text/javascript\">\s*\(function\(c, l, a, r, i, t, y\)[\s\S]*?</script>\s*",
        "",
        text,
        count=1,
    )

    OUT.write_text(text, encoding="utf-8")
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes)", file=sys.stderr)


if __name__ == "__main__":
    main()
