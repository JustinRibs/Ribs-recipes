#!/usr/bin/env bash
#
# Regenerate the PWA icons, the favicons and the .ico from the brand SVGs.
#
# Run this only when resources/brand/*.svg changes — the generated PNGs are
# committed, so a normal build and deploy never needs a browser.
#
#   ./scripts/build-icons.sh
#
# Requires a Chromium-family browser (used purely as an SVG rasteriser, because
# it is the only renderer guaranteed to agree with what the site itself shows)
# and PHP with the imagick extension for the multi-resolution .ico.

set -euo pipefail

cd "$(dirname "$0")/.."

BRAND_DIR="resources/brand"
ICON_DIR="public/icons"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

find_browser() {
    for candidate in \
        "${CHROME_BIN:-}" \
        "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
        "/Applications/Chromium.app/Contents/MacOS/Chromium" \
        "$(command -v google-chrome || true)" \
        "$(command -v chromium || true)" \
        "$(command -v chromium-browser || true)"
    do
        if [ -n "$candidate" ] && [ -x "$candidate" ]; then
            echo "$candidate"
            return 0
        fi
    done

    echo "No Chromium-family browser found. Set CHROME_BIN to one." >&2
    return 1
}

BROWSER="$(find_browser)"

render() {
    local source="$1" size="$2" output="$3"

    cat > "$WORK_DIR/render.html" <<HTML
<!doctype html>
<html><head><meta charset="utf-8"><style>
html,body{margin:0;padding:0;background:transparent}
svg{display:block;width:${size}px;height:${size}px}
</style></head><body>
$(cat "$source")
</body></html>
HTML

    "$BROWSER" --headless --disable-gpu --hide-scrollbars \
        --default-background-color=00000000 \
        --screenshot="$output" --window-size="$size,$size" \
        "$WORK_DIR/render.html" > /dev/null 2>&1

    echo "  $output (${size}px)"
}

mkdir -p "$ICON_DIR"

echo "Rendering icons…"
render "$BRAND_DIR/icon.svg"          512 "$ICON_DIR/icon-512.png"
render "$BRAND_DIR/icon.svg"          192 "$ICON_DIR/icon-192.png"
render "$BRAND_DIR/icon.svg"          180 "$ICON_DIR/apple-touch-icon.png"
render "$BRAND_DIR/icon.svg"           32 "$ICON_DIR/favicon-32.png"
render "$BRAND_DIR/icon.svg"           16 "$ICON_DIR/favicon-16.png"
render "$BRAND_DIR/icon-maskable.svg" 512 "$ICON_DIR/icon-maskable-512.png"

cp "$BRAND_DIR/icon.svg" "$ICON_DIR/favicon.svg"
echo "  $ICON_DIR/favicon.svg"

echo "Building favicon.ico…"
php -r '
$ico = new Imagick();
foreach (["public/icons/favicon-16.png", "public/icons/favicon-32.png"] as $file) {
    $frame = new Imagick($file);
    $frame->setImageFormat("png32");
    $ico->addImage($frame);
}
$ico->setImageFormat("ico");
$ico->writeImages("public/favicon.ico", true);
'
echo "  public/favicon.ico"

echo "Done."
