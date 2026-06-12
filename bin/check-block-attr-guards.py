#!/usr/bin/env python3
"""Block-attribute guard detector (coding-rules Rule 7).

The trust-boundary rule this enforces (born from BC #9989784605, where
columns:0 reached listing-featured/render.php and threw an uncaught
DivisionByZeroError for customers): editor-side JS constraints (NumberControl
min/max) do NOT protect the server. The block-renderer REST API and saved
post content deliver raw attributes to render.php. Therefore:

1. Any render.php variable assigned from a block attribute and later used as
   a DIVISOR (or modulo operand) MUST be floor-clamped at the assignment
   (max( N, ... )). A block.json "minimum" alone is not enough for division:
   schema validation can be refactored away without touching render.php.
2. Any number-typed block.json attribute whose attribute name feeds a CSS
   grid track count (a *-columns custom property or repeat()) MUST either be
   floor-clamped at the assignment OR declare "minimum" in block.json
   (a 0 here renders a zero-track, invisible layout).

Usage: check-block-attr-guards.py [blocks_dir]   (default: <plugin>/blocks)
Exit 0 = clean, exit 1 = violations (printed one per line).
"""

import json
import re
import sys
from pathlib import Path

ASSIGN_RE = re.compile(
    r"^\s*\$([a-z0-9_]+)\s*=\s*(.+\$attributes\[\s*['\"]([A-Za-z0-9_]+)['\"]\s*\].*);",
    re.M,
)


def main() -> int:
    plugin_dir = Path(__file__).resolve().parent.parent
    blocks_dir = Path(sys.argv[1]) if len(sys.argv) > 1 else plugin_dir / "blocks"
    if not blocks_dir.is_dir():
        return 0

    violations = []

    for block_json in sorted(blocks_dir.glob("*/block.json")):
        render = block_json.parent / "render.php"
        if not render.is_file():
            continue
        try:
            meta = json.loads(block_json.read_text())
        except (ValueError, OSError):
            continue
        attrs = meta.get("attributes", {})
        numeric_attrs = {
            name: ("minimum" in spec)
            for name, spec in attrs.items()
            if isinstance(spec, dict) and spec.get("type") in ("number", "integer")
        }
        src = render.read_text()
        rel = block_json.parent.name

        for m in ASSIGN_RE.finditer(src):
            var, rhs, attr = m.group(1), m.group(2), m.group(3)
            if attr not in numeric_attrs:
                continue
            clamped = "max(" in rhs
            has_min = numeric_attrs[attr]
            line_no = src[: m.start()].count("\n") + 1

            # 1. Division / modulo by the attribute-sourced variable.
            div_re = re.compile(
                r"[/%]\s*(?:\(\s*(?:int|float)\s*\)\s*)?\$" + re.escape(var) + r"\b"
            )
            if div_re.search(src) and not clamped:
                violations.append(
                    f"{rel}/render.php:{line_no} — ${var} (attribute '{attr}') is used as a "
                    f"divisor but the assignment has no max() floor. "
                    f"Fix: ${var} = max( 1, (int) ( $attributes['{attr}'] ?? <default> ) );"
                )

            # 2. CSS grid track count fed by the variable.
            css_re = re.compile(
                r"(-columns[:'\"]|repeat\()[^\n;]*\$" + re.escape(var) + r"\b"
                r"|\$" + re.escape(var) + r"\b[^\n;]*(-columns|repeat\()"
            )
            if css_re.search(src) and not clamped and not has_min:
                violations.append(
                    f"{rel}/render.php:{line_no} — ${var} (attribute '{attr}') feeds a CSS "
                    f"column track count with no max() floor AND no \"minimum\" in block.json. "
                    f"Add at least one (both preferred)."
                )

    for v in violations:
        print(v)
    return 1 if violations else 0


if __name__ == "__main__":
    sys.exit(main())
