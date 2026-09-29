# Stale need-count gates (card 10350113286 / T-43)

Plant a throwaway need owned by the buyer: `_listora_need_response_count = 2`, zero rows in `wp_listora_need_responses`, status open.

**Expect (live COUNT is 0):**

1. `/post-need/?edit_need={id}` as the author — no "already has quotes" banner.
2. My Needs — "0 responses", Delete offered; Confirm **trashes** the need (`need_deleted`), does not close it.
3. Admin Needs → Open — Quotes column is 0.
4. REST PATCH as the author — `wb_listora_pro_need_updated_after_quotes` does not fire.
5. REST DELETE as the author — `{deleted: true}`, post trashed, not `{closed: true}`.

**Positive control:** one real response row still shows the banner, hides Delete, REST closes, Quotes ≥ 1.

Delete the throwaway after the walk.
