---
journey: leads-admin-retention-privacy-ownership
plugin: wb-listora
priority: high
roles: [administrator, member-owner, member-other, editor, anonymous]
covers: [leads-admin, leads-retention, leads-privacy-export-erase, leads-follow-owner, leads-rest-delete, 2.0.0-leads]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} via ?autologin=1"
  - "Fixtures seeded (docs/qa/fixtures/2.0-fixtures.php seed): {listing} owned by {owner}; leads inserted in Setup so the journey is self-contained"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default light]
site_profile: required
supersedes: ["ora:admin/2.0-leads-admin-retention-privacy-ownership.md"]
---

# Site owner: Listings > Leads, retention, privacy tools, ownership

## Why
Leads live in `{prefix}listora_leads`. The site owner reads them all under Listora > Listings > Leads, decides how long they are kept, answers privacy requests from them, and a lead must follow its listing to a new author. Visitor-to-owner path: `customer/16-listing-contact-form.md`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Screen `admin.php?page=listora-leads` (cap `manage_listora_settings`).
- Insert three leads without the form:
  ```bash
  wp eval '
  global $wpdb; $t=$wpdb->prefix."listora_leads";
  $l=(int)$wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_title=\"<listing title>\" AND post_type=\"listora_listing\" LIMIT 1");
  $o=(int)get_post_field("post_author",$l);
  foreach([["J20 New","j20-new@example.com","now"],["J20 Old","j20-old@example.com","-13 months"],["J20 Mid","j20-mid@example.com","-5 months"]] as $r){
    $wpdb->insert($t,["listing_id"=>$l,"owner_id"=>$o,"sender_id"=>0,"name"=>$r[0],"email"=>$r[1],"phone"=>"","message"=>"J20 lead ".$r[0],"created_at"=>gmdate("Y-m-d H:i:s",strtotime($r[2]))]);
  } echo "listing $l owner $o\n";'
  ```
- Set retention to "Until deleted" first (step 3 needs a known start).
- Code map: `Leads::prune` / `Leads::follow_owner` / `Leads::export` (`includes/core/class-leads.php`), `Leads_Page::handle_action`, `Admin::register_menus`.

## Steps

### 1. The screen ({admin}, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-leads&autologin=1`.
- **Expect**: `.listora-page-header__title` "Leads", description "Every message visitors sent to listing owners through the contact form. Owners see their own under My Dashboard > Leads."; `.listora-hub-tabs` "All Listings" and "Leads" (aria-current on Leads); "Keep leads for" select `#listora-leads-retention` ("3 months, 6 months, 12 months, 24 months, Until deleted"); views All / Last 7 days / Last 30 days / Last 90 days; search "Search names, emails and messages"; a "Listing ID or title" filter; columns Received, From, Listing, Message; count text "<n> leads"; each row has sender name + mailto + phone, listing link to its edit screen, clamped message, actions "Reply" and "Delete".
- **Read-back**: count equals `SELECT COUNT(*) FROM {prefix}listora_leads`.

### 2. Filters and the 7-day view
- **Action**: "Last 7 days"; search `J20 Old`; clear search.
- **Expect**: 7 days shows "J20 New" only; search shows only "J20 Old"; clearing restores the list.
- **Big-site step**: with >= 2 pages of leads (seed thousands, QA-prefixed) the pager shows numbered pages; `&paged=999` shows the last page, not an empty table; sort by Received flips order. Not verified live.

### 3. Retention prunes immediately and says so
- **Action**: Keep leads for = "12 months", Save; then "3 months"; then "Until deleted" with one 14-month-old row inserted and `wp listora cleanup`.
- **Expect**: notice "Retention saved. Older leads were removed."; "J20 Old" (13 months) gone, "J20 Mid" and "J20 New" stay; at 3 months "J20 Mid" gone, "J20 New" stays; at "Until deleted" the old row is kept (0 = never prune).
- **Read-back**: option `wb_listora_lead_retention_months` = 12 / 3 / 0.

### 4. Delete one lead (confirm, then gone for the owner too)
- **Action**: Delete on "J20 New" -> confirm text "This lead is deleted for good. The listing owner can no longer see it either." -> confirm; then delete the same lead again from a stale tab.
- **Expect**: notice "Lead deleted."; row gone; `/my-listings/?tab=leads` as {owner} no longer lists it ("No messages yet" when it was the last); the stale-tab delete causes no fatal, the page reloads with no notice and the list unchanged.
- **Read-back**: `SELECT COUNT(*)` for the id = 0.

### 5. Privacy: export and erase by email
- **Action**: Tools > Export Personal Data > Add Data Export Request for `j20-guest@example.com` (untick the confirmation email), then "Email data"/"Download Personal Data"; then Tools > Erase Personal Data for the same email.
- **Expect**: the export has the group "Listora contact messages" with fields Listing, Name, Phone, Message, Sent; erase lists "Listora contact messages" with items removed >= 1.
- **Read-back**: `wp eval 'print_r(\WBListora\Core\Leads::export("j20-guest@example.com"));'` returns group `wb-listora-leads`; `wp eval 'echo isset(apply_filters("wp_privacy_personal_data_exporters",[])["wb-listora-leads"]) ? "REGISTERED":"MISSING";'`; `SELECT COUNT(*) ... WHERE email='j20-guest@example.com'` = 0 after erase.
- **Member under another address**: send a lead signed in as {other} with a DIFFERENT email; export/erase for {other}'s ACCOUNT email must include it (`sender_id` match). This is the 2.0.0 Security fix.

### 6. A lead follows its listing to a new author (Quick Edit)
- **Action**: Listings list > the {listing} > Quick Edit > Author = {other} > Update.
- **Expect**: `SELECT DISTINCT owner_id FROM {prefix}listora_leads WHERE listing_id=<id>` = {other}'s ID; `/my-listings/?autologin={other}&tab=leads` now has the "Leads" tab with the rows; {owner}'s Leads tab no longer lists them (tab may disappear if they own nothing else). The claim-approval and reversal path: `admin/03-approve-claim.md` step 7.
- **Read-back**: owner_id query before and after.

### 7. Delete of a hard-deleted listing's leads
- **Expect**: leads are NOT in the listing delete cascade; they age out with retention (`system/listing-delete-cascade.md` step 6).

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {admin} | `admin.php?page=listora-leads` | full list |
| {editor} (no `manage_listora_settings`) | same URL | 403 "Sorry, you are not allowed to access this page." and no "Leads" tab in the Listings hub |
| member-owner | wp-admin URL | WordPress permission error, never the table |
| member-owner | `DELETE /listora/v1/leads/<id>` for their own lead | `200 {deleted:true}` (owners may delete their own) |
| member-other | same call for someone else's lead | `404 listora_lead_not_found` |
| {guest} | `GET /listora/v1/leads` | `401` |

## Pass criteria
1. Leads screen renders under the shared header with retention, views, search, listing filter and numbered pages.
2. Saving retention prunes immediately with the exact notice; "Until deleted" never prunes.
3. Delete asks for confirmation, removes the row for the owner too, and tolerates a repeat.
4. Personal-data export and erase find leads by email and by account.
5. Leads follow the new author after Quick Edit, and nobody else gains access.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Retention save keeps old rows | `prune()` not called | `class-leads-page.php::handle_action` |
| Export finds only the typed email | `sender_id` branch removed | `Leads::export` / `sender_id()` |
| Owner column stale after author change | `post_updated` hook not registered | `class-plugin.php`, `class-leads.php::init` |
| Editor can open the screen | menu cap widened | `Admin::register_menus` (Leads item) |

## Not verified
Step 2 scale seed, 390 layout, the Tools UI download (code-level export verified), claim path of step 6.

## Cleanup
`wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean`; `wp option update wb_listora_lead_retention_months 12`.
