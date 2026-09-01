#!/usr/bin/env python3
"""Generate the hooks reference for a Listora plugin from its manifest.

The reference used to be hand-maintained and drifted badly: Free's was written
for 1.1.0 and still claimed 233 hooks against 328 in source, and Pro's listed 6
of its 258. Both are now emitted from audit/manifest.json, which is itself
verified against source, so a manifest refresh is the only step needed.

Usage: build-hooks-reference.py <plugin-dir> [<plugin-dir> ...]
"""
import json
import re
import sys
from collections import OrderedDict
from pathlib import Path

# Ordered: first pattern that matches wins, so specific beats general.
CATEGORIES = OrderedDict([
    ("Bootstrap",            r"_(loaded|init|rest_api_init|ready)$"),
    ("Listings",             r"_listing|_listings"),
    ("Submission",           r"_submit|_submission"),
    ("Media",                r"_media|_image|_gallery|_attachment"),
    ("Reviews",              r"_review"),
    ("Claims",               r"_claim"),
    ("Search & Filters",     r"_search|_filter_|_facet|_query"),
    ("Credits & Payments",   r"_credit|_payment|_checkout|_price|_pricing|_coupon|_plan|_invoice|_receipt"),
    ("Needs (Reverse)",      r"_need|_response|_reverse"),
    ("Members & Roles",      r"_member|_user|_role|_cap|_moderat"),
    ("Notifications",        r"_notif|_email|_digest|_webhook"),
    ("Admin & Settings",     r"_admin|_setting|_page|_menu|_notice"),
    ("Templates & Display",  r"_template|_render|_card|_detail|_grid|_badge|_map"),
])


def categorise(name):
    for label, pattern in CATEGORIES.items():
        if re.search(pattern, name):
            return label
    return "Other"


def esc(text):
    """Escape pipes so a signature never breaks the markdown table."""
    return str(text).replace("|", "\\|")


def signature(hook):
    """One-line, bounded arg signature.

    The manifest scraper sometimes captures a whole multi-line array literal
    as the "argument", which renders as an unreadable wall inside a table
    cell. Collapse whitespace and cap the length -- the reference points at
    the fire site, which is where the real signature lives.
    """
    args = hook.get("args_signature") or []
    if not args:
        return "_(none)_"
    parts = []
    for a in args:
        a = re.sub(r"\s+", " ", str(a)).strip()
        if len(a) > 60:
            a = a[:57].rstrip() + "..."
        parts.append(a)
    return ", ".join(parts)


def consumers(hook):
    names = set()
    for c in hook.get("consumed_by", []):
        # Entries are dicts on a fresh manifest but bare strings on older ones.
        name = c.get("plugin", "") if isinstance(c, dict) else str(c)
        if name and name != "unknown":
            names.add(name)
    names = sorted(names)
    return ", ".join(f"`{n}`" for n in names) if names else "-"


def build(plugin_dir):
    plugin_dir = Path(plugin_dir)
    manifest = json.loads((plugin_dir / "audit" / "manifest.json").read_text())
    slug = manifest.get("plugin", {}).get("slug") or plugin_dir.name
    version = manifest.get("plugin", {}).get("version", "")

    own = [h for h in manifest.get("hooks_fired", []) if h["name"].startswith("wb_listora")]
    own.sort(key=lambda h: h["name"])
    actions = sum(1 for h in own if h["type"] == "action")
    filters = len(own) - actions

    grouped = OrderedDict()
    for hook in own:
        grouped.setdefault(categorise(hook["name"]), []).append(hook)
    # Keep declared category order, with Other last.
    order = [c for c in CATEGORIES if c in grouped] + (["Other"] if "Other" in grouped else [])

    out = [
        "# Hooks Reference (Actions & Filters)",
        "",
        f"Every `{slug}` action and filter, generated from `audit/manifest.json`"
        f"{f' at {version}' if version else ''}. The plugin fires **{len(own)} hooks** - "
        f"**{actions} actions** + **{filters} filters**.",
        "",
        "Regenerate with `bin/build-hooks-reference.py <plugin-dir>` after a manifest",
        "refresh. Do not hand-edit: this file is overwritten.",
        "",
        "`Consumed by` lists sibling plugins already listening, so you can see which",
        "hooks have proven wiring before you rely on one.",
        "",
    ]

    for cat in order:
        hooks = grouped[cat]
        out += [
            f"## {cat} ({len(hooks)})",
            "",
            "| Hook | Type | Args | Fired at | Consumed by |",
            "|---|---|---|---|---|",
        ]
        for h in hooks:
            out.append(
                f"| `{h['name']}` | {h['type']} | {esc(signature(h))} | "
                f"`{esc(h.get('where', '?'))}` | {consumers(h)} |"
            )
        out.append("")

    target = plugin_dir / "docs" / "website" / "developer-guide" / "hooks-reference.md"
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text("\n".join(out))
    print(f"  {slug}: {len(own)} hooks ({actions}a/{filters}f) in {len(order)} categories -> {target.relative_to(plugin_dir)}")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    for d in sys.argv[1:]:
        build(d)
