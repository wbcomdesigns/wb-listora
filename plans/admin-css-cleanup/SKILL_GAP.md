# Why agents keep introducing inline CSS / JS — and the skill patch

## What the skill says today

`~/.claude/skills/wp-plugin-development/references/admin-ux-rulebook.md`, line 1131, in a "What NOT to Do" bullet list near the bottom of an 1138-line file:

> - Do NOT use inline styles in PHP

That's it. There is **no** mention of:

- inline `<style>` blocks (the bigger source of debt — we just shipped 5 of them on the settings page)
- inline `<script>` blocks (~14 KB across admin)
- a `wp_localize_script` / `wp_add_inline_script` data-only pattern
- a PHPCS sniff to enforce it
- examples of right vs. wrong
- the Jetonomy reference token list to copy

There's also nothing about scoping tokens, the page-header convention, the sidebar settings pattern, or how to migrate inline-styled markup to utility classes — which means even agents who *want* to follow the rule have to invent the replacement pattern from scratch every time.

## Why agents miss it

1. **The rule is at line 1131 of 1138.** Agents who read the skill in chunks (or stop reading after the active patterns section) never reach it.
2. **It's only one line.** No emphasis, no callout, no "RULE N:" prefix like the rules earlier in the file.
3. **No examples.** Agents need to see the replacement, not just the prohibition.
4. **No CI enforcement.** Without PHPCS catching it, every PR review is manual, and reviewers miss it too.
5. **The rule is incomplete.** It only forbids `style="…"` attributes. Inline `<style>` and `<script>` blocks are technically not "inline styles in PHP" by a literal reading — and that's exactly where most of our debt is.
6. **Jetonomy's token list isn't in the skill.** When an agent is told "follow Jetonomy's pattern", they have nothing to copy from inside the skill — they'd have to read another plugin's source. So they invent their own tokens, which fragments the system.

## Proposed patch to `admin-ux-rulebook.md`

Add a new section at the *top* of the rulebook — directly after Rule 5, before Rule 6 — titled "Rule 6 — No Inline CSS or JS in PHP" (renumber the rest). Body:

```markdown
### Rule 6: No Inline CSS or JS in PHP

**Forbidden — never emit any of these from PHP:**

1. `<style>...</style>` blocks
2. `<script>...</script>` blocks (except `<script type="application/ld+json">` for structured data)
3. `style="..."` attributes on HTML elements (except in `templates/emails/*.php` — email clients strip `<style>`)

**Allowed alternatives:**

| Use case | Forbidden | Use this instead |
|----------|-----------|------------------|
| One-off layout for a button row | `<div style="display:flex;gap:.5rem;">` | utility class `.<plugin>-cluster` |
| Hide-on-load that JS toggles | `<div style="display:none;">` | `.is-hidden` + `el.classList.toggle('is-hidden')` |
| Pass server data to JS | `<script>var X = <?php echo … ?>;</script>` | `wp_localize_script( $handle, 'pluginFeature', [ … ] );` |
| Per-instance dynamic CSS (e.g., user-set color on a block) | `<style>.block-{id} { color: <?php … ?>; }</style>` | `wp_add_inline_style( $handle, $css )` enqueued from PHP |
| Behavior tied to a feature toggle | inline `<script>` | enqueue a per-feature JS file conditionally in `class-assets.php` |

**Enforcement:** `phpcs.xml` includes a sniff that fails the build on any of these patterns. CI rejects the PR. The MCP rule-checker (`wppqa_check_plugin_dev_rules`) flags them on a per-file basis.

**Why this rule exists:**

- Inline blocks bypass the design-token system entirely — every `<style>` block hardcodes colors instead of using `var(--<plugin>-*)`.
- Inline scripts can't be cached, can't be deferred, can't be CSP-locked, and force a re-parse on every page load.
- They make plugin co-existence painful — three plugins all emitting their own inline `<style>` for the same selector causes order-of-load bugs.
- They make minification and concatenation impossible.
- The code is unreviewable — no single file owns the CSS, so audits become "grep across all PHP".
```

And add a "Reference token list" section near the existing token guidance, listing Jetonomy's 56 tokens so agents have something concrete to mirror.

## Process changes

1. **Add `wppqa_check_plugin_dev_rules` checks** for inline `<style>` and `<script>` blocks (not just `onclick=`). The sniff already exists for `onclick`; extend the same regex. This makes the rule visible in every PR's QA report.
2. **Add a CLAUDE.md rule** at the top level of any new plugin: "No inline CSS or JS in PHP. See admin-ux-rulebook Rule 6." So even agents that don't load the full skill catch the rule from CLAUDE.md.
3. **The constitutional feedback memory** at `~/.claude/projects/.../memory/feedback_no_inline_css_js.md` is project-specific. Promote a generalized version to global memory or to the `wp-plugin-development` skill itself so it applies to every Wbcom plugin going forward.
