# Owner Questions — the QA question bank (ALL plugins and themes)

> **QA stands where the site owner stands.** Owners never read code. They judge a
> plugin or theme by its options, its defaults, its templates, its emails, and
> whether the thing they bought does the thing they bought it for. Every
> question here is one an owner could ask — and answer — without opening a file.
>
> **This file grows.** Every bounce, every reopened card, every "how did QA miss
> that" adds one question, tagged with the card that paid for it. Never delete a
> question; retire it with a strike and the reason. Questions are cited by id in
> verdicts (`Questions asked: O-3 O-7 T-2`) so coverage is auditable per card.

Each question has: **id · the question · how an owner would check · what counts
as evidence.** `OWNER_INVENTORY.md` (generated) feeds the O/T/E/D/A lists;
`CORE_PATHS.md` (confirmed) feeds C.

---

## O — Options & settings

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| O-1 | Does every setting do what its label says? | Toggle it, reload the frontend, look | Before/after screenshot |
| O-2 | Is every default the one a fresh owner would expect? | Fresh install, read each default, ask "would I change this on day one?" | Inventory default column vs judgement |
| O-3 | When a setting is off, is the feature *gone* — not just hidden? | Turn off, then hit the URL / REST / shortcode directly | Direct hit returns nothing or a clean denial |
| O-4 | Can the owner tell the current state without reading the database? | Look at the settings screen only | State visible on screen |
| O-5 | Does a saved setting survive an update, a migration, a theme switch? | Save, bump version / switch theme, re-read | Value unchanged |
| O-6 | Is a setting that exists in code reachable from an admin screen? (three-entry-points rule) | Inventory: option with reads but no admin string | Every own option has a UI or a documented reason |
| O-7 | Do dependent settings hide or explain themselves when their parent is off? | Turn parent off, look at children | Children hidden/disabled, or a note |
| O-8 | Does the setting name match the frontend wording? | Compare settings label to what members see | Same vocabulary |

## T — Templates & display

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| T-1 | Does it look right on a theme we did not write? | Twenty Twenty-Five + one classic theme | Screenshots on both |
| T-2 | Can the owner override a template from their theme? | Inventory: theme-override loader yes/no | Copy a template to the theme, see it used |
| T-3 | Does every shipped template render without the plugin's own CSS being special-cased? | Disable theme-specific CSS, look | Layout holds |
| T-4 | Does it hold at 390px, and in RTL? | Resize; `?lang=ar` or RTL plugin | Screenshots |
| T-5 | Does it honour the theme's colours / dark mode rather than hard-coding? | Switch theme palette / dark toggle | No raw hex bleed |
| T-6 | Are empty, loading and error states designed — not blank? | Empty site, slow network, forced failure | Three screenshots |
| T-7 | Does every plugin-created page have the site chrome (header/footer/menu) on block AND classic themes? | Visit each activation page on both | Chrome present |

## E — Emails & notifications

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| E-1 | What emails does this send, and when? Can the owner list them? | Inventory email table vs. any settings screen | A list the owner can see |
| E-2 | Does each email fire exactly once per trigger? | Trigger, check mail log | One entry |
| E-3 | Can the owner edit subject and body without code? | Look for a template/settings surface | Editable, or a filter documented |
| E-4 | Does the email say who it is from, and does the from-address match the site? | Read the headers | Site name + admin email |
| E-5 | Are in-app notifications (BuddyPress / Woo / own) cleared when acted on? | Act on the item, check the bell | Count drops |
| E-6 | Does a notification link land on the thing it names, as the right role? | Click it as the recipient | Lands correctly |

## D — Developer-friendliness

| id | Question | Check as the owner's developer | Evidence |
|---|---|---|---|
| D-1 | Is there a hook on every core path (before/after save, before render, on output)? | Inventory hooks vs CORE_PATHS | Each core path names its hooks |
| D-2 | Is every own hook documented with `@since` and its args? | Inventory documented count | Undocumented list is empty or justified |
| D-3 | Can a developer change a default without editing the plugin? | Look for a `*_default(s)` filter | Filter exists |
| D-4 | Are hook names consistent with the prefix and readable? | Scan the list | No orphans, no typos |
| D-5 | Do REST endpoints exist for everything the UI can do? (three entry points) | Inventory REST count vs UI actions | Parity, or documented gaps |
| D-6 | Is there a CLI for the bulk things (seed, migrate, recount)? | `wp <prefix>` | Commands exist |

## A — Activation & lifecycle

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| A-1 | On a clean activate, what appears? Pages, menus, roles, tables — and is each expected? | Inventory activation table; visit the site | Nothing surprising |
| A-2 | Does deactivate leave the site clean, and uninstall remove data (with a warning)? | Deactivate; uninstall on a scratch site | No orphan pages/menus; uninstall confirms |
| A-3 | Does an upgrade from the previous version keep existing data rendering? | Upgrade a seeded old site | Old content shows |
| A-4 | Does the setup wizard / first-run notice lead somewhere useful, and dismiss for good? | Activate, follow it, dismiss, reload | Gone, and stays gone |
| A-5 | Does the free/pro pair activate in either order without a fatal? | Pro first, then free; and reverse | No fatal, clear notice |

## C — Core paths (the 60–70%)

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| C-1 | Do the ranked core paths in `CORE_PATHS.md` all complete, as their named role, on a clean install? | Walk them in rank order | One line per path, PASS/FAIL |
| C-2 | Does the first thing a new owner would try work with **zero configuration**? | Activate and try rank 1 with no settings touched | Works |
| C-3 | Is the most-used feature reachable in ≤ 2 clicks from the plugin's own landing? | Count clicks | ≤ 2 |
| C-4 | Are the core paths the ones the ledger says people actually report on? | Compare `plan/basecamp/ledger.md` hot-spots to the ranking | Ranking adjusted or justified |

## P — People (the role ladder)

| id | Question | Check | Evidence |
|---|---|---|---|
| P-1 | Reproduced as the role that reported it? | `personas` + `?autologin=` | Verdict `Roles walked` row 1 |
| P-2 | Walked with **two** same-role members — owner and not-owner? | Two logins | Both rows |
| P-3 | Was admin the *last* rung, and never the only one? | Verdict | Admin row is the control |
| P-4 | Does `ROLE_MATRIX.md` agree with what you saw? If not, which is wrong? | Compare | Finding filed either way |

## Triage — three axes, then the priority

Answer all three for every card, in the verdict.

| Axis | Question | Values |
|---|---|---|
| **R — Reach** | Reproduces on a clean install, default settings, default theme? | `universal` · `site-specific` (only with their theme / plugin mix / config) |
| **I — Impact** | Can the owner or member complete the task? | `road-block` (no workaround) · `degraded` (partial or workaround) · `cosmetic` |
| **L — Location** | On a core path or an edge? | `core` (in CORE_PATHS) · `edge` |

| | core | edge |
|---|---|---|
| universal · road-block | **P0** | P1 |
| universal · degraded | P1 | P2 |
| site-specific · road-block | P1 | P2 |
| site-specific · degraded | P2 | P3 |
| cosmetic (either reach) | P2 | P3 |

**Site-specific is a finding, not a dismissal.** It means the product breaks on a
configuration a real owner runs. The follow-up is "which configuration, and do
we support it" — never "works for me".

**Edge cases are welcome, never first.** Walk C before anything marked edge.

---

## Growing this file

When a card is bounced, reopened, or a customer finds what QA did not:

1. Write the question that would have caught it, in the owner's words.
2. Give it the next id in its section, and tag it: `(card 10263273931)`.
3. Put the check in the "as the owner" column — if the check needs code, it is
   the wrong question; find the owner-visible symptom instead.
4. Sync the copy in every plugin's `docs/standards/` at its next release.

## Added 2026-09-09 — from the WPMediaVerse 2.4.x cycle

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| T-8 | Is anything meant to be hidden still visible on a theme that ships **no** `[hidden]` reset? *(card 10266317652 — Reign/BuddyX silently rescued it; Astra did not)* | On Astra or Twenty Twenty-Five, every element with `hidden` must have `offsetParent === null` | List of hidden-but-visible elements, per theme |
| O-9 | If a setting holds a value that used to be legal and no longer is, does the feature fall back **visibly**, or fail silent? *(card P6 — `feed_layout='default'` disabled the feed with no message)* | Set each enum option to a retired value, reload | Fallback shown, or an admin notice |
| P-5 | To a role that is denied an item, does it look **identical** to an item that does not exist — same status, same page? *(2026-08-11 F2 — a denied document returned a 403 login page, confirming it existed)* | Request a denied id and a nonexistent id as `member-other` and as anonymous | Same status code, same body |
| C-5 | Before writing CANNOT-REPRO, does every fixture the repro depends on actually resolve? *(card 10264236711 — "cannot reproduce" was a deleted-media fixture)* | Confirm the row exists, the slug is live, the user is the stated role | Fixture check listed in the verdict, or BLOCKED |

## Added 2026-09-09 — admin state contract and promises (from external QA feedback)

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| O-10 | Does every settings screen with nothing configured say what to do **next**, rather than showing an empty shell? | Fresh install, open each admin page | A next action (button or link) on every empty screen |
| O-11 | Is every field label a real label - not a placeholder that vanishes on focus, not the option key? | Tab through each form | Labels persist; none read like `mvs_foo_bar` |
| O-12 | When a save fails, is the error on the **field** that failed, not a page-level banner? | Submit an invalid value | Inline field error, focus moved to it |
| M-1 | Does every promise the readme or marketing makes ("reversible", "never leaves your server", "owner can always get back in") hold as a walked row? | `owner-inventory.py` lists promise candidates; confirm each into `promises[]` in qa-config | One row per promise, PASS with evidence |
| M-2 | Is every artefact that exists in code alive on the site - shortcode renders, block renders, route answers, admin page loads? | `owner-inventory.py --json`, then walk | `coverage.code_to_live` with no `missing-from-live` left unexplained |
| T-9 | Have the controls that only render under a **condition** (a first-visit prompt, a banner, an empty state, a cover-less card) been measured too, not just the always-visible ones? *(card 10266320513's sweep said "0 under the floor"; the profile-prompt close was 17×22 — it only renders for a member with no profile)* | Walk as a fresh member with nothing configured; measure every control that appears | Conditional controls listed in the sweep |
| M-3 | Is an **absence** the inventory reports actually absent, or just ungreppable? *(2026-09-09: "theme-override loader: NO" became a filed card; the loader existed and was documented — `locate_template()` built its argument from variables, so a literal-only grep saw nothing)* | Prove absence live before filing: exercise the thing the tool says is missing. Drop the file, call the hook, set the option | The live attempt, and its result, in the card |
| P-6 | Was the probe itself valid? A single PHP process that **writes state then reads it back** proves nothing about cached reads — the repository's request cache survives `wp_cache_flush()`. *(2026-09-09: happened twice in one session. `exists()` returned true for a row deleted moments earlier, hiding a real bug; `can_view()` returned YES for anonymous on private media, inventing a P0 that did not exist.)* | Set up in one process, read in a **separate** process. One `wp eval` per read. | The verdict names how many processes the probe used |
| P-7 | Was an admin-UI claim checked in a **real browser session**, or simulated? `wp eval` with `do_action('admin_menu')` does not register what an actual wp-admin request registers. *(2026-09-09: a CLI enumeration reported "zero admin screens"; the browser showed nine. The card was still valid, but the evidence would have sent a developer hunting a missing menu.)* | Log in as the role and load the page. For access claims, request each URL with that user's cookie and confirm the session identity on the response | The verdict names the browser/HTTP method, and the identity it confirmed |
| S-1b | Before walking anything: would the change this card asks for **edit a file we ship**? *(2026-09-09, card 10281693577: a card on the Learnomy board named three files, and all three belonged to BuddyNext — two in BuddyNext Free, and the bridge in BuddyNext **Pro**, which was not even installed on the QA site. A full walk reached the same answer that opening the file paths would have given in one step.)* | Resolve every file, class and function the card names to a plugin directory before anything else. A companion's own docs are authoritative about what its Pro tier registers. Then ask the second half: does OUR side already expose what their fix needs — the hook, the payload, the lookup? | The verdict names the owning plugin for each named symbol, and says explicitly whether our extension points block the proposal |
| S-1 | Is the finding **inside the plugin's scope**? A plugin owns its own routes, templates, options, tables and REST surface. It does **not** own the site's nav menus, the theme's markup, another plugin's data, or the server config. | Name the thing that would change. If it belongs to the theme, the site owner, or another plugin, the finding is context for a verdict — not a card against us | The card names a file or surface the plugin owns |

## Added 2026-09-10 — from the BuddyNext 1.2.0 RFT walk

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| T-10 | Does the selector a CSS fix is scoped to actually **exist on the page it is meant to fix**? *(cards 10281148124 / 10281446723 — a `font-family: inherit` reset was scoped to `.bn-wrap`, a class that appears **zero** times on any of the six BuddyNext hub pages, which carry 61–353 form controls between them. The rule was dead CSS; both cards' symptoms were still on screen after the "fix".)* | On the surface the card names, run `document.querySelectorAll('<the fix's scope selector>').length` — it must be > 0 — then compare the computed property on the actual element against its container | The selector count on the real page, plus the before/after computed value on the element the card is about |

## Added 2026-09-11 — from the BuddyNext 1.2.0 RFT walk (30 cards) and the fresh-eye pass

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| T-11 | With scripts blocked, does this page **paint anything the member would have to un-believe**? *(card 10285486889 — a pending-approval notice relied only on `data-wp-bind--hidden` and flashed "Your account is awaiting approval" at every guest before hydration. Two more instances survived on Settings and Search.)* | Fetch the page's real server response, strip every `<script>`, mount it at full width and measure what has a box. A binding of the form `!state.x` with no static `hidden` attribute is the shape to look for | The painted element list from the script-free render, plus the same element's `hidden` state after hydration |
| T-12 | Is the surface I am testing **actually rendering**, or has a second gate upstream replaced it with a fallback? *(card 10268330095 — the space Media tab is gated twice, by a site-wide integration toggle AND a per-space `mvs_media_tab` field. That field was off on all 14 spaces, so the page silently fell back to the space feed and my first "verification" exercised none of the fixed code.)* | Before measuring anything, assert a marker element that only the surface under test renders. If it is absent, you are testing a fallback | The marker's presence, and the value of every gate between the route and the template |
| T-13 | Does this **content type exist on the test site at all**, or am I about to verify a fix against a surface that never renders? *(card 10268294892 — no audio media existed anywhere, so "clicking audio opens the lightbox" could not have been tested by clicking around. Three cards this round needed a seeded fixture first.)* | Count rows of the type the card is about before starting. Zero means seed a fixture, verify, then remove it | The count before, the verification, and the count after cleanup |
| T-14 | Does the element at those coordinates **belong to the thing I think is on top**? *(card 10259580162 — a report modal was closed as fixed on the z-index scale alone. A correct z-index still loses to a stacking context created by a transform or filter on an ancestor.)* | `document.elementFromPoint()` at the centre of the panel AND on its primary button — both must resolve inside the modal | The hit-test result at both points, not the computed `z-index` |
| D-7 | Does anything upstream **synthesise a value for "unset"**, so the default branch I just fixed can never run? *(card 10268684581 — Free's owner-default resolver was correct, but Pro's `inject_into_prefs()` backfills every type with `on_site => true` hardcoded, which the resolver reads as the member's explicit choice. Verifying inside Free alone passes cleanly; the defect exists only in the configuration every customer runs.)* | Call the resolver with the sibling plugin's filter unhooked, then hooked, and compare. Any difference means the pair, not the plugin, is the unit under test | Both return values, and the line that manufactures the synthetic row |
| D-8 | Did the commit that added this migration **also move the version that gates it**? *(card 10285715373 — `maybe_migrate_year_fields()` was added while `SCHEMA_VERSION` stayed at 54, so `maybe_upgrade()` early-returns and the migration never fires on any site already stamped 54. Same root cause as the 1.2.0 posting blocker. Four migrations now hang off that one gate.)* | `git show <sha>:<installer>` and `<sha>~1:<installer>`, compare the constant. Then rewind the stored option on a test site and confirm the migration actually runs | The constant on both sides of the commit, and the before/after row state on a genuine upgrade path |
| M-4 | If a green run **cannot go red on demand**, what is it actually proving? *(card 10289688055 — a fixture-isolation fix whose bug does not reproduce by repetition. "Ran it twice, it passed" would have been a false green; the dirt has to be seeded for the test to mean anything.)* | Seed the exact state the fix is supposed to survive, then run the pre-fix and post-fix versions against it | The counterfactual failing by name, next to the fixed version passing |
| M-5 | Does this link resolve to **the page it claims**, or merely return 200? *(card 10286140549 — a hardcoded docs URL map with no test that fails when the far end moves. A WordPress docs site answers 200 with a "nothing found" page.)* | Fetch every URL the map can produce and read the `<title>`; it must name the subject the admin section is about | Status code AND page title for every generated URL |
| O-9 | Is the editor's **source of truth the same filtered list the frontend consumes**? *(card 10286076480 — the Account Dropdown editor read a display-filtered catalogue, so hiding an item removed it from the editor too and every hide was one-way. The other four nav scopes read a static array and were never affected.)* | Hide an item, save, reload the editor — the item must still be listed, switched off, and switchable back on | The editor's item list and the frontend's, after the same save |
| C-4 | After login, does the visitor **arrive where they were going**? *(card 10281747733 — a guest clicking a member's name reached a dead-end login screen. The fix went to the route gate, not the link, so the hrefs are unchanged and reading the template suggests it was never fixed.)* | Click the real link logged out, log in on the screen it lands on, and see where you end up | The redirect chain and the final URL after a real login |
| P-4 | Does this product call **one concept by one name** on every surface the member sees? *(card 10294314847 — the activity hub is "Activity" as a page, "Feed" in the nav and "Activity Feed" in the browser title; notifications is "Notifications" on desktop and "Alerts" on mobile.)* | Map every visible label to the URL it points at. Two labels on one destination is the defect | The label-to-URL map across desktop, mobile and the admin |
| O-10 | Does this setting's label describe **what the code actually does**, or what someone intended it to do? *(BuddyNext safeguards — "Hold duplicate posts for review" and "Posts … are held for review" both describe a pre-publish queue; `PostService` publishes the post and files a report instead, deliberately, per its own comment.)* | Turn the setting on, perform the action it governs as a member, and check whether the content is visible to other members right now | The member-visible state of the content, not the admin notice |

## Added 2026-09-11 — from the Member Blog 4.1.1 Bugs sweep (10 cards)

| Id | Question | How to check | Evidence |
|---|---|---|---|
| C-6 | When **two validators** (free + pro, or JS + server) check the same field, does the member still see exactly **one** message for it? *(card 10268650852 — the title is focused on load, so Publish fired Free's blur check AND Pro's server check: "Title is required." twice, in two classes, neither clearing the other. It only reproduced with the field focused, which is why a plain click said CANNOT-REPRO the first time.)* | Submit the empty form twice: once from a blank click, once with the field focused. Count every text node carrying the message | The count and each node's class, in both runs |
| C-7 | Does every surface that **explains** a refusal (popup, form replacement, warning, notice) read the same answer as the gate that **enforces** it? *(cards 10294363870, 10294363633 — the gate honoured personal overrides and all five periods; the popup, form replacement and warning each re-read the role table, so a capped member saw a normal form and their post was silently drafted.)* | Put a member at the limit through each source (role, member type, override) and each period; the gate must refuse AND every explanation must appear | One row per source × surface, refusal next to explanation |
| O-13 | Does a switch whose feature **cannot work** (its foundation is off) say so, instead of reading ON and doing nothing? *(card 10290258494 — "Clap and save on post lists" read ON with Claps and Reading list both off; an upgraded site starts exactly there.)* | Turn each foundation off, open the settings screen, look at the dependent switch | Disabled with a named reason, owner's saved choice kept |
| P-8 | Is another plugin on the test site **masking** the defect? *(card 10290638663 — WPMediaVerse widens BuddyPress's activity allow-list, so classes survived on the QA site and were stripped on the reporter's.)* | Before CANNOT-REPRO, list every callback on the filter the card is about and repeat with only ours registered | The callback list, and the result with the others removed |
| D-9 | Does the write that **changes** a cached number clear that cache? *(card 10294456008 — post counts were cached 60 s and never flushed on publish; the gate counted before the insert, so a 1-per-hour member published twice inside a minute.)* | Two separate requests: act, then act again inside the cache TTL | Both results, and the line that invalidates |
| D-10 | Is a stored `'yes'`/`'no'` ever tested for **truthiness** (`! $setting`, `if ( $setting )`)? `'no'` is a non-empty string, so OFF reads as ON. *(card 10294810599 — "Group Activity for New Posts" off still created group activity; the same `! 'no'` was on the linking switch)* | Grep every read of the option key; each must compare to `'yes'` | The list of reads and their comparison |
| T-15 | Does the plugin's own rule for this control **actually win** on the page, or does the theme's wrapper selector (`.buddypress .buddypress-wrap button:hover`, 0,3,1) outrank a single class? *(card 10294944703 — the fixed `.bpmb-feature-btn:hover` shipped, built and loaded, and the button still hovered at 3.45:1)* | Hover the real element and read the computed value, then list every matching rule and its sheet | The computed hover colours, and the winning selector |

## Added 2026-09-14 — from the Member Blog Bugs sweep (3 cards)

| Id | Question | How to check | Evidence |
|---|---|---|---|
| T-16 | On a BuddyPress member page, does **every** plugin button win the cascade against BP Nouveau's `.buddypress .buddypress-wrap button` (0,2,1) — in the **resting** state, not only on hover? Check `type="button"` and no-type buttons specifically: a `button[type="submit"]` tie-breaker matches neither. *(card 10294944703 — T-15 caught the hover; the resting state was still lost. All five series-panel buttons rendered as BP's white/#555/#ccc default in both light and dark, Edit at 1.74:1, Delete indistinguishable from Cancel. The same root cause was also live on the co-authors "Accept Invite" button, which omits the `type` attribute entirely.)* | Scan every surface for the signature `color: rgb(85,85,85)` + `border-color: rgb(204,204,204)`. For each hit, the fix must be a selector that **outranks** (0,2,1) — a BP-scoped twin at (0,3,0) — not another dark-mode override | The computed colour/background/border per button before and after, plus the `type` attribute of each |
| T-17 | Does the **design system have a rule for this shape**, or is the screen doing something the vocabulary never named? *(card 10300311999 — three separate "spacing/alignment" complaints on three tabs were one missing vocabulary: a caption that belongs to a row had no component, and a card nested inside a card had no demotion, so the inner head was pixel-identical to the outer one.)* | Before nudging a margin on one screen, ask what component the element IS. If the stylesheet has no rule for it, the fix is the rule, not the nudge — then measure how many other screens it touches | The new rule, and the count of elements it changes across every tab |
| O-14 | When a feature ships new markup, does **every container class it introduces actually have a CSS rule**? *(card 10299737472 — the Manual Credit Management panel shipped in the v4.0.0 rebuild with 15 new class names and a stylesheet block that was never written. 13 of the 15 had zero rules anywhere, so every container fell back to `display: block`. Nothing fails loudly: the panel renders, it just has no layout.)* | Grep each new class name across the plugin **and** its RTL variants. Then open the panel and confirm the containers report a real `display`/`gap`, not `block`/`normal` | The per-class rule count, and the computed `display` of each container |

## Added 2026-09-15 — from the WP Sell Services 1.7.1 RFT walk (21 cards)

| Id | Question | How to check | Evidence |
|---|---|---|---|
| T-18 | Does any element the plugin prints carry a **bare WordPress core class** that core's own CSS or JS acts on? *(card 10304173477 — the Vendors list wrapped its Approve button in `<span class="approve">`, and core's list-tables.css ships `.approve, .unapproved .unapprove { display: none; }` for comment moderation. Core hid our button; the owner could not approve a vendor from the list at all. The sibling Reject button still uses a bare `trash`, which is safe today by luck, not design.)* | For every element the plugin renders in wp-admin, match it against each loaded stylesheet rule and keep any that resolve to `display:none`/`visibility:hidden`. Sweep for the core comment-row names specifically: `approve`, `unapprove`, `spam`, `trash`, `reply`, `edit` | The matching rule and its stylesheet, plus the element's computed box |
| T-19 | If the plugin prints a notice **inside a metabox**, does it still carry WordPress's `notice` class? *(card 10289819803 — the "why your service stayed a draft" notice used `notice notice-warning`. wp-admin's own JS hoists any `.notice` into the page's notice area, which in the block editor sits inside `div.wrap.hide-if-js.block-editor-no-js` — `display:none` for every real owner. The reasons rendered, were relocated, and were never seen: the exact silent failure the notice existed to prevent.)* | Count occurrences of the notice in the document, then check how many are inside the metabox that printed it. Zero-inside means core moved it | Occurrence count, the ancestor chain of each, and the computed display of the winning ancestor |
| T-20 | When the server **refuses** what the editor asked for, does the editor still claim it succeeded? *(card 10304304974 — an invalid service was correctly kept as `draft`, but the block editor's status indicator read "Published". Gutenberg saves over REST first and posts metaboxes second, so a rule enforced in the metabox request lands after the editor has already been told "publish" — and Gutenberg discards the metabox response, so the server cannot correct the label through it.)* | Compare the DB status against the editor's indicator immediately after the refused action, before any reload. Then repeat with a **valid** save and confirm the correction does not fire | Both statuses at the same moment, and whether a valid save triggers a reload it should not |
| C-5 | Is the thing being measured **one component or several states**? *(card 10240423992 — `.wpss-conversation-card__time` was recorded as a single 4.46 failure. It is actually two grounds: 6.99 on a normal card, 4.4993 on the unread/active indigo card. The fix moved the default case and left the unread state failing by 0.0007, which reads as "not fixed" unless the states are measured separately.)* | Measure **every** instance of a selector on the page, not the first one `querySelector` returns. Group by effective background before concluding anything | Per-instance table: ground, ratio, pass/fail |
| O-15 | Does the fix exist in the **source** and not in what the browser actually loads? *(this cycle — five dark-mode components measured byte-identical to the original report while their source CSS already used tokens. The cause turned out to be a concurrent fix landing mid-run, but the same symptom is produced by a stale `.min.css`, and the two are indistinguishable from the browser alone.)* | When source and measurement disagree, check the built artifact and its mtime against the source, and check `git log` for commits newer than the measurement | Source rule, minified rule, both mtimes, and the commit timestamps either side of the test |
| C-6 | Was the code **the same** when the fix was written and when QA tested it? *(this cycle — five commits landed on the branch while the 21-card walk was still running. I re-tested one bounce against code fixed twenty minutes earlier and wrongly concluded my own measurement had been faulty, then had to retract it on the card.)* | Record the branch HEAD sha at the start of a walk. Before retracting or softening any finding, re-check `git log` for commits touching that file since the measurement | HEAD at walk start, HEAD at verdict, and any commits between them |
| C-7 | Did the card actually **move**, or did the tool only say it did? *(this cycle — `basecamp cards move --to <numeric column id>` returned `Moved card #… to '9381846253'` for three cards that stayed in Ready for Testing. Passing `--to "Bugs" --card-table <id>` worked. The success envelope is not evidence.)* | After every move, read the card back and check `parent.title` is the column you intended. Never trust the move response | The card's `parent.title` after the move |

## Added 2026-09-19 — from the WP Sell Services 1.7.2 RFT walk (28 cards, 11 bounced)

| Id | Question | How to check | Evidence |
|---|---|---|---|
| P-9 | When a fix **hides** something from the public, can the people who must still see it - the owner of the item and the admin reviewing it - still open it? *(card 10313837841 — pending services were correctly hidden from guests, but moving the moderation query filter from admin-only to every front-end request also 404'd the vendor's own "View" and the admin moderation queue's "View": the reviewer could no longer preview what they were approving.)* | After a visibility fix, open the item as anon, as its owner, and as the reviewing admin, via the exact links each role is given | HTTP status per role, and the `edit_post` check result for each |
| D-11 | Does the fix **reuse a variable** that neighbouring lines still depend on? Read the whole render block, not just the changed line. *(card 10312882728 — the file label fix assigned the filename to `$ev_name`, which the same template printed three lines later as the message author, so every dispute bubble was signed "Attachment" or a filename. The dev verified the label and never looked at the line above it.)* | Diff the template, then list every later read of each variable the fix writes | The author line and the file label, both rendered, on a thread with text AND file messages |
| D-12 | Does a status gate compare against the **stored value** (the class constant), not a guessed literal? Walk the item through every state the workflow can put it in, including the ones only the API reaches. *(card 10312923900 — Withdraw/Escalate were gated on `'pending'`; the stored status is `pending_review`, which the REST respond route sets, so both buttons vanished the moment the other party replied.)* | Grep the gate's literals against the service's STATUS_* constants; drive the item into each state (REST included) and re-render | Buttons shown per status per role |
| C-8 | Does a NOT-REPRODUCED use the reporter's **input size and path**, crossing every threshold in the code? *(card 10313470306 — "1,330 characters renders in full" was true for that path; answers over 300 chars get `--collapsed` and the unclaimed "Additional notes" answer gets no Show-more control at all. A 1,703-char note was clipped to 4 lines on the first try.)* | Grep for length thresholds and collapse classes on the surface; test one input below and one above each, on each answer path (claimed field, free-text notes, request brief) | clientHeight vs scrollHeight and the presence of the toggle, per path |
| T-21 | When a verdict says **"by design"** because of an interactive state (hover underline, focus ring), does that state actually render in the shipped cascade on the real page? *(card 10313294062 — the text-button defence rested on `.wpss-btn--link:hover { underline }`; frontend.css re-declared `.wpss-btn:hover { text-decoration: none }` later at equal specificity, so hover showed nothing.)* | Hover and focus the real element with the page's full stylesheet set loaded; read computed values; list matching rules in load order | Computed text-decoration/outline on hover and focus, and the winning rule |
| C-9 | When a fix comment says it covers **every sibling** ("the repeaters", "the analytics tab"), was each sibling and each entry point exercised? *(cards 10317310038 and 10317649874 — extras were fixed but the requirements repeater still threw the same TypeError; the vendor Today filter worked in the template while the REST enum still returned 400 for `period=today`, which the reporter had named.)* | List the siblings the claim names or implies (every repeater, template + REST + admin) and run the failing action on each | Per-sibling result table |

## Added 2026-09-19 — from the WPMediaVerse 2.5.1 RFT walk (21 cards, 9 bounced, 5 new bugs)

| Id | Question | How to check | Evidence |
|---|---|---|---|
| T-22 | Did the fix change a **property the card never asked about**? *(card 10309690361 — the card asked for controls to inherit the theme's font-family; the fix also added `font-size: inherit` to the same rule at (0,1,2), which outranked every button's own size, and every MediaVerse button and select grew from 13.8-14.9px to body size. The family was verified; nobody measured the size.)* | Diff every declaration in the changed rule, not the one the card names. For each extra property, measure the computed value on 3-5 real elements before and after (re-apply the old rule in-page) | Per-property before/after table on real elements |
| T-23 | Does a label that fits at desktop still fit the **actual phone width of its control**, after every wrapper's padding? *(card 10313754840 — "no fixed cap remains" was true, but four nested paddings left the privacy select 218px on a 390px phone against a 272px label, so it clipped mid-word; the same 160px cap lived on in the bulk bar. Card 10313672574 — stats aligned at 1440 and wrapped into three different layouts at 390.)* | At 390, measure the control's content box and the widest option or label it can show; screenshot the longest value selected | Control width, widest label width, the screenshot |
| P-10 | When the owner turns a member capability **off**, does it hold on every surface that edits the same field — edit modals, bulk, inline edit, REST — not only the one the setting names? *(card 10320619418 — "Allow Users to Set Privacy" off hid the picker at upload only; Edit, bulk and PATCH still changed privacy.)* | List every surface and route that writes the field; as a member with the switch off, attempt a change through each | Per-surface result: picker shown y/n, REST status |
| C-10 | Does every control on an overlay respond to a **real pointer click** at both widths, not just exist in the DOM? *(card 10317099934 — the audio story rendered its controls, but the author row sat on top; `elementFromPoint` on play returned the header and a real click left the audio paused. At 390 the close button covered Next.)* | `elementFromPoint` at the centre and edges of each control, then a real mouse click and a state read (paused, index) | Hit-test element per point, state before/after the click |
| O-16 | Can a **stale or concurrent edit** leave a "there must be one" setting at zero? *(card 10317737947 — editing a package another admin had just deleted cleared the default flag on every package first, then updated nothing: the site had no default and new members got no quota.)* | Submit the edit with the id of a row deleted after the form opened (tamper the hidden id); read the table after | Count of rows holding the flag, and the notice shown |
| D-13 | Does the **API / app surface** make the same promise the UI now makes? *(card 10252887791 — Space privacy was hidden in every web picker off-BuddyNext, but `/app/config` still offered "Everyone on this drive" to the mobile app.)* | For every option hidden or gated in the UI, read the app-config / REST schema that feeds the app with the same condition | The value list from the endpoint under each condition |
| P-11 | Does a grant based on "can reach the space" separate **visitors from members**? *(card 10312532955 — a link grant checked `drive_access != 'none'`, and BuddyNext answers `read` to non-members of an open space, so any signed-in user could open a private file linked there.)* | For every grant, test a member, a non-member of an OPEN space, a non-member of a private space and a logged-out visitor | HTTP status per role |
| D-14 | Is a failure **swallowed** where the feature silently stops? *(card 10320619363 — stories never auto-advanced: the timer threw on `getContext()` outside a scope and an empty `catch` hid it; the progress bar still animated, so it looked alive.)* | Grep the changed JS for empty `catch` blocks; wait out every timer-driven behaviour in the browser and check the state actually changed | State before and after the timer, and the console |
| C-11 | When a fix gates a field, did it cover **every route in every plugin** that writes it — Free *and* Pro REST namespaces, templates in every layout? *(card 10320619418, second bounce — the privacy lock guarded `/mvs/v1/media/{id}` and the dashboard, but Pro's `/mvs-pro/v1/media/{id}/privacy` and `/media/bulk-privacy` still changed privacy (200), and the Explore/profile bulk bar in `explore.php` still showed the picker. The fixer had just written P-10 and still missed the second plugin.)* | `grep -rn "'privacy'" includes/` in BOTH plugins, list every `register_rest_route` whose callback writes the field, and every template that renders a picker for it; attempt a change through each as the locked role | Per-route status and per-template picker presence |
| C-12 | Does a behaviour that waits for a media event (`ended`, `timeupdate`) assume **playback has started**? *(card 10320619363 — video/audio stories were changed to advance on `ended`, but nothing autoplays, so `ended` never fires and the viewer stalls on every AV story. The fix verified "does not advance at 5s" and never waited for it to advance at all.)* | Open the sequence and wait without touching anything; each item must eventually advance or clearly invite a tap | Time to advance per item type, and the element's `paused` state |
| D-15 | Does the **write path** refuse what the offer side now hides? *(card 10252887791, second bounce — `space` vanished from every picker and the app config off-BuddyNext, but `PATCH /documents/{id} {privacy: space}` still returned 200 and stored it, because validation used `PRIVACY_VALUES` instead of the gated list.)* | For every value removed from a picker, POST/PATCH it directly through each write route under the same condition | Status and stored value per route |

## Added 2026-09-19 — from the Learnomy 2.0.0 RFT walk (Batch A)

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| O-17 | When a module's rule changes, does the **catalog sentence** on the Modules card match the badge next to the switch? *(card 10312632417 — the badge correctly said "Required while anything is priced"; the description still said "Always on. … a free-only academy just never sets a paid price.")* | Read the module's title, badge, and description as one card. If they tell two stories, the description is the defect | The three strings, on the Modules screen |
| C-13 | Can this money-path card be marked done **without a buyer finishing the rail**? *(card 10298559359 — PayPal auto-fulfil on thank-you. Sandbox keys were present; a green tick without a buyer return and a broken webhook would have been a lie.)* | Name the buyer action that takes money or grants access. If you did not do that action, the verdict is BLOCKED | The buyer action, and whether it was completed |

## Added 2026-09-19 — from the Learnomy 2.0.0 RFT walk (Batch B)

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| P-12 | When a route is public on purpose (pricing, catalog), does a logged-out prospect see **that page** — not the signed-in account chrome, and never a Log out link? *(card 10298559160 blast — `/learning-spaces/plans/` is 200 with prices, but the account sidebar still paints Log out while the header says Sign in.)* | Open the public URL with cookies cleared. Header, sidebar, and footer must agree that nobody is signed in | Screenshot of the guest view; presence of Log out |
| C-14 | Does a personal-data export contain **every** row of the subject's data, past the first page and past any old cap? *(card 10308509919 — bookmarks fetched once on page 1, capped at 1000, `done` computed from notes.)* | Seed past the old cap, walk the exporter the same way Tools → Export Personal Data does, count rows against the database | Exported count vs DB count, and the page the walk ended on |

## Added 2026-09-19 — from the Learnomy 2.0.0 RFT walk (Batches D–G)

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| D-16 | Does `audit/manifest.json` (and `LEARNOMY_VERSION`) still name the **previous** release after the branch is already the next one? *(card 10298607598 — 2.0.0 branch, manifest and plugin header still 1.9.5.)* | Open audit/manifest.json `plugin.version` and the main-file Version header. They must equal the branch being shipped | The three strings: branch, manifest, header |

## Added 2026-09-20 — from the Learnomy 2.0.0 Bugs sweep (23 cards, 16 closed, 2 refuted)

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| M-4 | Did the surface actually load **the thing you think it loaded** before you called it broken? *(card 10299663092 — `?lesson=` instead of `?lesson_id=` rendered a blank editor that read exactly like lessons losing their course and section.)* | Confirm an identifying field on screen matches the record you targeted — title, name, id — before recording a NO | The identifying value on screen vs the row in the database |
| M-5 | Is the NO you measured the product's answer, or **your fixture's**? *(same card — an arbitrary instructor "proved" a permission gap that was course ownership, and an attempt with zero stored responses "proved" a missing drill-down.)* | Re-run as the role that genuinely owns the object, against a record that genuinely has the data | Who you ran as, what the record contains |
| S-2 | Does the code you are about to change carry a **comment recording a decision** that this card reverses? *(card 10264437438 — lesson-editor.php says in full "the panels are not duplicates and neither should be removed (Basecamp 10203589087)", which is exactly what the card proposes.)* | Read the comments around the named file:line before proposing the fix. A prior Basecamp id in a comment is a settled question | The prior card id and what it decided |
| U-1 | Does this fix **add** a control, a step, or a second way to do one thing? | Prefer the change that removes a control over the one that adds or migrates. Name what the owner expects on that screen, then check the fix moves toward it | The control count before and after |
| U-2 | Is the card's proposed fix **bigger than the problem it describes**? *(card 10264437438 — a schema migration on released sites to fix two confusing sidebar cards.)* | Price the card's proposal and the smallest fix that removes the confusion. If they differ by an order of magnitude, say so on the card and let the owner choose | Both options, with effort and risk |
| U-3 | Who actually hits this — **every owner, or a specific kind of site**? *(Learnomy sweep: schedule overlap hits every Pro site with drip on; instructor directory and multi-tenant provenance hit only marketplaces and tenanted sites.)* | Name the site shape that meets the problem. A capability only a narrow segment needs is a roadmap item, not a release defect | The segment, and roughly what share of installs it is |
| P-13 | Did the probe authenticate the way the **product** does? A bare `fetch()` from a logged-in page sends cookies but **no `X-WP-Nonce`**, so the WP REST API treats it as logged-out — and a permission-shaped result then looks like a data-shaped one. *(2026-09-09, card 10285755501: a raw fetch of `/wp/v2/users` returned `[]` for the ADMIN too, which read as "that user does not exist"; re-run through the app's own `restFetch` it returned 1 for admin and 0 for a subscriber — isolating a `list_users` capability gap.)* | Call through the app's own authenticated helper, or attach the nonce. When a role differential is the claim, run the identical call as both roles in the same way | The helper used, and the same call's result per role |
| P-14 | For a "does this control disappear once state changes?" claim, did the check include a **fresh page load in the changed state**? An action's own success handler usually writes the new state into client memory, so the control hides for the rest of that session whether or not the server ever says so. Acting then looking is the one path where the bug cannot appear. *(2026-09-10, card 10285886066: I registered for a tournament through the UI, saw the Register button hide, and closed the card CANNOT-REPRO. `register()` had populated an in-memory `registeredIds` array; the detail page's endpoint - `get_bracket()` - never returned `viewer_registered` at all, so on a hard load as an already-registered member the button came back. The reporter was right and my verdict was wrong.)* | Put the account into the changed state **out of band** - a service call, a DB write, a second browser session - then load the page cold. Verify the control both ways: hidden for the changed account, still offered to an unchanged one | The state was set out of band, the load was cold, and both the case and the control are reported |
| P-15 | Does the **endpoint this screen actually calls** carry the field the UI needs, or only its siblings? A resource with several read routes will grow a field on the ones someone was looking at and not on the rest. *(Same card: `get_items()` and `get_item()` both returned `viewer_registered`; `get_bracket()`, the one the detail page fetches, did not - and its docblock already claimed it returned what the Register button needed.)* | Call the exact route the page uses, as the affected user, and look for the field - not a neighbouring route that looks equivalent. Check the nesting too: the field may be one level down | The route, the user, and the field's value quoted from that response |

## Added 2026-09-17 — from the BuddyPress Statistics 2.0.0 Loco ticket

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| A-6 | Does the release zip ship **exactly one** translation template, named after the text domain, and does it contain every string the plugin shows? *(card 10308337654 — 2.0.0 shipped `buddypress-stats.pot` missing 219 of 782 strings plus a stale `bp-stats.pot` that grunt `makepot` kept regenerating under the old slug; Loco reported "Partially configured" and the profile journey/impact/streak text was untranslatable)* | Install the release zip with Loco Translate: Setup tab must say "Bundle auto-configured" and Overview must list no "Additional files". Then `wp i18n make-pot` on the tagged source and diff msgids against the shipped POT | Loco Setup screenshot, `.pot` count in `languages/`, and zero msgids missing from the shipped POT |

## Added 2026-09-17 — from the Wbcom CAPTCHA Manager 2.2.0 cycle

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| C-7 | Do **wp-admin actions that reuse a front-end hook** still work with every protection toggle at its activation default? An admin screen never renders the front-end widget, so any validator on a shared core hook (`lostpassword_post`, `preprocess_comment`, `wp_authenticate_user`, `registration_errors`) blocks the owner's own tool. *(card 10258489144 - Users > Send password reset said "sent to 0 users"; sibling card 10313463526 - Comments > Reply failed "Security verification failed")* | Fresh activate, touch no settings, then as admin: Users > Send password reset, Edit User > Send Reset Link, Comments > Reply. Each must succeed. Then prove the front-end form still rejects a submit with no token | Per admin action: the success notice. Plus the anonymous no-token rejection as the control |

## Added 2026-09-17 - from the Wbcom CAPTCHA Manager 2.2.1 bug sweep

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| C-8 | Does every protected form still work when **two of them share a page** (header login + sidebar widget, login block + comment form)? A form tested alone can pass while the same form beside a sibling fails. *(card 10313746056 - reCAPTCHA v3 AJAX login worked on its own page; next to the core Login/Logout block its token never arrived and login always failed)* | Put two protected forms on one page, load cold as the target role, submit each | Per form: token/widget present at submit and the server outcome, alone vs paired |
| P-16 | Is a **text-match** probe matching the result, or static text that was always on the page? *(2026-09-17: a "required fields" error check matched the field label "Group Name (required)", so the control read as "message shown" when nothing was shown)* | Match on the element that only appears for the outcome (`.bp-feedback`, `#login_error`, a notice id), or on text absent before the action; capture before/after | The selector used and its count before vs after the action |

## Added 2026-09-17 - from the WB Listora 1.8.0 RFT re-walk

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| O-13 | When a **Private / Coming Soon / members-only** mode says the content is hidden, is it hidden **everywhere a visitor can read it**, not just the rendered page? *(card 10305325404 - the page gate held while `/wp-json/wp/v2/listings`, the core sitemap, oEmbed, SEO landing pages rendered at `template_redirect` 5, and the BuddyPress activity RSS all served listings to anonymous visitors)* | As anon with no cookies, curl: the plugin's REST namespace and core `/wp/v2/<rest_base>` for its CPTs and taxonomies, `/wp/v2/search`, `/wp-sitemap.xml`, oEmbed for a hidden URL, every custom rewrite page, and any activity/RSS feed that mentions the content. Control: the same URLs in Public mode | Per surface: status code and whether content appears, hidden vs public |
| O-14 | Does a **site-wide setting that a block also carries** change the website, or only the app/API? *(card 10230658539 - Marker clustering fed only `/settings/app-config`; the map block read its own attribute, and block.json `default: true` meant the setting could never reach it)* | Flip the setting, load a page whose block never set the attribute, and observe. Then check block.json: a default there is applied to render `$attributes` and silently defeats a `?? setting` fallback | The rendered difference with the setting on vs off, for an unset block and an explicit one |
| O-15 | Does a status row say **"not set"** when the owner has set it but the thing is not public yet (draft page, pending item)? *(card 10313405198 - core `get_privacy_policy_url()` is empty for a draft, and WordPress' own Create flow saves a draft; the terms dropdown also hid a selected draft, so saving Settings reset it to 0)* | Map the page, set it to Draft, reload the settings screen, then save the settings form untouched and re-read the stored ID | The row text for draft / none / published, and the stored ID after a save |
| O-16 | Is a metric **counted where the event happens**, or inside a feature that may not be loaded? *(card 10226117669 - leads were counted in the Lead Form class, which the feature manager skips when its toggle is off; the fallback form then delivered messages that counted nowhere)* | Turn the owning feature off, perform the event through the other door (fallback form, app route), and read the metric's stored count before and after | Count before/after with the feature on and off, and which hook recorded it |
| T-10 | Does a **deep link** still work when the hash is not a valid CSS selector (`#a:b`, `#x[`, a malformed `%` escape)? *(card 10304369374 - `querySelector('#tab-' + hash)` threw, aborting tab init, so the no-JS `:target` reveal stayed live and the Reviews panel leaked again)* | Open the page with each odd hash in a fresh context, watch for uncaught errors, then switch tabs | Console errors (none), the ready class present, the panel hidden after switching |
| T-11 | With **two instances of the same block** on one page, does each one's Load More / pagination / default view act on its own settings? *(2026-09-17, filed as 10314572173 - two grids share the global `listora/directory` state; the first grid's Load More fetched the second grid's type and page size)* | Put two instances with different type and page size on one page, use each control, and inspect the network request each fires | The request params per click, and which container received the results |

## Added 2026-09-22 — merged back from the WB Listora repo copy

These seven were written into `wb-listora/docs/standards/owner-questions.md`
and never reached this file, so they were invisible to every other plugin.
**Each one carried an id this bank had already given to a different question**
(a repo copy and this file both appended, independently, to the same series),
so they are renumbered here. A Listora verdict written before 2026-09-22 cites
the left column; the question it meant is the right one:

| Cited as (Listora, pre-2026-09-22) | Now |
|---|---|
| `T-10` | `T-24` |
| `P-8` | `P-17` |
| `C-6` | `C-15` |
| `P-9` | `P-18` |
| `P-10` | `P-19` |
| `P-11` | `P-20` |
| `P-12` | `P-21` |

| id | Question | Check as the owner | Evidence |
|---|---|---|---|
| T-24 | After a client-side rewrite of a notice (success becoming error), can the member still dismiss it? *(card 10322935144 — server-rendered error has ×; a failed claim `replaceChildren` wipes it, so a Stripe return with an unknown session is stuck again)* | Return with `?wbcom_credits=success&session_id=cs_test_unknown`, wait for the claim to fail | Dismiss control still present, clickable, and the URL cleans |
| P-17 | For a **CSS state** claim (`:hover`, `:focus`, `:active`), was the state triggered by a **real pointer/keyboard**? A dispatched `mouseover`/`mouseenter` does not trigger CSS `:hover` — the element never matches, so nothing changes and the probe looks like a pass. *(2026-09-09: a synthetic hover reported "no jitter" before a real hover confirmed it.)* | Use the driver's own hover/focus, then assert `el.matches(':hover')` before reading geometry | The verdict shows the `:hover` assertion and the before/after geometry |
| C-15 | When a feature **hides** something, is it hidden on **every** route — listing, permalink, direct file/serve URL, REST, feed — or only on the one surface the feature happened to touch? *(2026-09-09, card 10278781363: the report auto-hide set `moderation_status='flagged'`; the explore listing filtered it out because that one caller opted in, while the permalink and the signed serve URL both returned 200 to a logged-out visitor with the full image.)* | After triggering the hide, request the item **anonymously** on each route in turn. A visibility rule that lives in the caller will be missed by exactly one caller — put the guard in the shared visibility path and re-walk | Status code and body per route, anonymous, after the hide |
| P-18 | Does the card assume a threshold/default the product does not actually use? A "does nothing on the first X" report is usually an N-threshold working correctly. | Read the option and its registered default before calling the behaviour wrong; then drive the flow N times to prove the threshold fires | The default, its admin location, and the run that crossed it |
| P-19 | Was a state-change probe contaminated by **state that already existed**? Reusing an entity created before the setting changed can bypass the very gate under test. *(2026-09-09, card 10281289161: a DM appeared to bypass a followers-only restriction; the pair already had an open conversation from before the setting was set, and an open thread stays open by design. Deleting it and re-running gave the correct `request_pending`.)* | Delete or recreate the entity so the guarded path is the one actually taken; check creation timestamps against the moment the setting changed | The verdict names the pre-existing state that was cleared |
| P-20 | Is the browser **actually** logged in as the role the verdict names? A `?autologin=<user>` URL does nothing when a session already exists, so after any admin step the next "member" check silently runs as admin - and admin passes almost everything. *(2026-09-23, cards 10322935501 + 10327885984: a banner check and a claim submit were reported as `credituser`/`claimer`; the claim row came back `user_id=1`. The banner verdict held, but it had been posted on admin evidence.)* | Clear cookies before switching roles, then confirm identity from the server - `GET /wp/v2/users/me?context=edit` (username + roles), or the `user_id` on the row the action wrote - not from the URL you typed | The username and roles the server returned, per role walked |
| P-20 | Did the probe sample the DOM **while the message was still on screen**? A toast that auto-dismisses or a view that reloads will read as "nothing was shown" if sampled once, late. *(2026-09-09, card 10280477806: a duplicate-upload warning was reported missing; the modal shows it, then reloads 2.5s later. A single check at 4s saw an empty page.)* | Poll from the moment of the action (150ms interval), or freeze the reload, before concluding nothing rendered | The polling interval and the captured text |
| P-21 | For a **contrast or colour** claim, was the measured node the one that actually **paints the text**? A container whose text lives in a child that sets its own colour has a `color` that never reaches the screen. *(2026-09-09, card 10263186020: `h1.site-title` computed `rgb(32,38,46)` against a dark header — a reported 1.01:1 — but it had zero direct text nodes; the `<a>` inside it painted at 13.13:1 and the title was plainly readable in a screenshot.)* | Assert the node has direct text (`[...el.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())`) before measuring it; otherwise measure the descendant that does. Composite every semi-transparent background layer down before computing the ratio — an `alpha 0.12` tint read as opaque produces a wrong number in the other direction | The painting node named, its direct-text assertion, and a screenshot |
