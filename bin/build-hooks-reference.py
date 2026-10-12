#!/usr/bin/env python3
"""Generate the hooks reference for a Listora plugin from its manifest.

The reference used to be hand-maintained and drifted badly: Free's was written
for 1.1.0 and still claimed 233 hooks against 328 in source, and Pro's listed 6
of its 258. Both are now emitted from audit/manifest.json, which is itself
verified against source, so a manifest refresh is the only step needed.

Fire sites, types and deprecations are read from the PHP source, so a hook
that no longer fires cannot stay listed; the manifest supplies arg signatures
and `consumed_by`, plus hooks fired through a constant or variable name, which
a source scan cannot see.

Free's docs are the one documentation set for Free and Pro (Pro CLAUDE.md), so
every plugin given is written into the first plugin's
docs/website/developer-guide/hooks-reference.md, one part per plugin.

Usage: build-hooks-reference.py [--check] <free-dir> [<pro-dir>]

--check writes nothing and exits 1 when the committed file differs from what
the source produces now; local CI runs it so the reference cannot drift.
"""
import json
import os
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


FIRE = re.compile(
    r"\b(do_action|apply_filters|do_action_ref_array|apply_filters_ref_array|"
    r"do_action_deprecated|apply_filters_deprecated)\s*\(\s*(['\"])(wb_listora[^'\"]*)\2"
)
# Tail of a *_deprecated() call: version, optional replacement, optional message.
DEPRECATED_TAIL = re.compile(r",\s*'([0-9.]+)'\s*(?:,\s*'([^']*)')?\s*(?:,\s*'([^']*)')?\s*\)\s*;")
SKIP_DIRS = {"vendor", "libs", "node_modules", "tests", "build", "audit"}


def scan_source(plugin_dir):
    """Map each literal wb_listora* hook name to its first fire site."""
    found = {}
    for root, dirs, files in os.walk(plugin_dir):
        dirs[:] = sorted(d for d in dirs if d not in SKIP_DIRS and not d.startswith("."))
        for name in sorted(files):
            if not name.endswith(".php"):
                continue
            path = Path(root) / name
            text = path.read_text(errors="ignore")
            for m in FIRE.finditer(text):
                hook = m.group(3)
                if hook in found:
                    continue
                func = m.group(1)
                entry = {
                    "type": "action" if func.startswith("do_action") else "filter",
                    "where": f"{path.relative_to(plugin_dir)}:{text.count(chr(10), 0, m.start()) + 1}",
                }
                if func.endswith("_deprecated"):
                    tail = DEPRECATED_TAIL.search(text, m.end(), m.end() + 600)
                    entry["deprecated"] = tail.group(1) if tail else "?"
                    entry["replacement"] = (tail.group(2) or "") if tail else ""
                    # The message names the removal version: 'Removed in 2.2.0.'
                    removed = re.search(r"Removed in ([0-9.]*[0-9])", (tail.group(3) or "") if tail else "")
                    entry["removed"] = removed.group(1) if removed else "-"
                found[hook] = entry
    return found


def collect(plugin_dir):
    manifest = json.loads((plugin_dir / "audit" / "manifest.json").read_text())
    slug = manifest.get("plugin", {}).get("slug") or plugin_dir.name
    source = scan_source(plugin_dir)
    listed = {h["name"]: h for h in manifest.get("hooks_fired", []) if h["name"].startswith("wb_listora")}
    hooks = []
    for name, fire in source.items():
        hook = dict(listed.get(name, {"name": name}))
        hook.update(fire)
        hooks.append(hook)
    # Names built from a constant or variable never match FIRE; keep the
    # manifest entry only when its recorded fire site still names the hook.
    for name, hook in listed.items():
        if name in source:
            continue
        file_part = str(hook.get("where", "")).split(":")[0]
        target = plugin_dir / file_part
        stem = re.split(r"[{$]", name)[0]
        if file_part and target.is_file() and stem in target.read_text(errors="ignore"):
            hooks.append(hook)
    hooks.sort(key=lambda h: h["name"])
    return slug, hooks


def table(hooks):
    rows = ["| Hook | Type | Args | Fired at | Consumed by |", "|---|---|---|---|---|"]
    for h in hooks:
        rows.append(
            f"| `{h['name']}` | {h['type']} | {esc(signature(h))} | "
            f"`{esc(h.get('where', '?'))}` | {consumers(h)} |"
        )
    return rows


def part(slug, hooks):
    live = [h for h in hooks if "deprecated" not in h]
    old = [h for h in hooks if "deprecated" in h]
    actions = sum(1 for h in live if h["type"] == "action")
    out = [
        f"# `{slug}` hooks",
        "",
        f"**{len(live)} hooks** - **{actions} actions** + **{len(live) - actions} filters**"
        + (f", plus {len(old)} deprecated." if old else "."),
        "",
    ]
    grouped = OrderedDict()
    for hook in live:
        grouped.setdefault(categorise(hook["name"]), []).append(hook)
    order = [c for c in CATEGORIES if c in grouped] + (["Other"] if "Other" in grouped else [])
    for cat in order:
        out += [f"## {cat} ({len(grouped[cat])})", ""] + table(grouped[cat]) + [""]
    if old:
        out += [
            f"## Deprecated ({len(old)})",
            "",
            "Still fired, with a deprecation notice. Move to the replacement before the version that removes them.",
            "",
            "| Hook | Type | Deprecated in | Removed in | Use instead | Fired at |",
            "|---|---|---|---|---|---|",
        ]
        for h in old:
            use = f"`{h['replacement']}`" if h.get("replacement") else "_(no replacement)_"
            out.append(f"| `{h['name']}` | {h['type']} | {h['deprecated']} | {h['removed']} | {use} | `{esc(h['where'])}` |")
        out.append("")
    return out


def build(plugin_dirs, check=False):
    dirs = [Path(d) for d in plugin_dirs]
    out = [
        "# Hooks Reference (Actions & Filters)",
        "",
        "Every action and filter Listora fires, read from the plugin source with arg",
        "signatures from `audit/manifest.json`. Pro hooks fire only when Listora Pro is active.",
        "",
        "Regenerate with `bin/build-hooks-reference.py <free-dir> <pro-dir>`.",
        "Do not hand-edit: this file is overwritten.",
        "",
        "`Consumed by` lists sibling plugins already listening, so you can see which",
        "hooks have proven wiring before you rely on one.",
        "",
    ]
    for d in dirs:
        slug, hooks = collect(d)
        out += part(slug, hooks)
        print(f"  {slug}: {len(hooks)} hooks")
    target = dirs[0] / "docs" / "website" / "developer-guide" / "hooks-reference.md"
    text = "\n".join(out)
    if check:
        if not target.is_file() or target.read_text() != text:
            sys.exit(f"  {target} is stale: run bin/build-hooks-reference.py {' '.join(plugin_dirs)}")
        print("  hooks reference matches the source")
        return
    target.write_text(text)
    print(f"  -> {target}")


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if a != "--check"]
    if not args:
        sys.exit(__doc__)
    build(args, check="--check" in sys.argv[1:])
