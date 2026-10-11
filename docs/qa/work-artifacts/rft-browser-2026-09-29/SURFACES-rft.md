# Surface rows walked 2026-09-29 (remaining RFT)

| Behaviour | Surfaces | file:line | grep |
|---|---|---|---|
| Danger confirm focuses Cancel | Dashboard More→Deactivate; Close need form | `assets/js/shared/confirm.js:160` | `tone ? parts.cancel` |
| Dark `--listora-primary-text` | Credits tab, Post a Need, dashboard | `assets/css/listora-base.css:172` | `primary-text-toward` |
| Review CAPTCHA token | Detail reviews tab, listing-reviews block, submission | `src/utils/captcha.js`; `templates/blocks/listing-detail/tabs.php:855`; `templates/blocks/listing-reviews/review-form.php:123` | `captchaFields` / `render_widget` |
| Needs accept hides Edit/Close | My Needs tab | `src/dashboard-needs.js:138`; `templates/dashboard/tab-needs.php:171` | `needActions.hidden` |
| Audit "On" user name | Tools → Audit Log | `class-audit-log.php:1493` | `object_type.*user` |
| Admin list labels/filters | Audit Log, Email Log, Webhooks | `helpers.php:514`; `class-email-log.php`; `class-outgoing-webhooks.php` | `wb_listora_event_label` |
| Fractional credits | Credits tab, Transactions, submission banner | `step-preview.php:25`; `class-pro-plugin.php:2708` | `(float) $credit_balance` / `data-amount` |
| Admin remove credits | Transactions modal + REST refund | `class-credits-controller.php`; `refund-modal.php` | `adjust_money` / `data-amount` |
| Wizard types + icons | Setup `rerun=1&step=type` / `location` | `class-setup-wizard.php:210,223,301,1216` | `selected_types` / `get_lucide_icon` |
| Need response count (display) | /needs/ grid, single, REST GET, matcher/expire, My Needs — live `COUNT(*)` after bd9bea5 | `get_counts_for_needs` | `_listora_need_response_count` / `get_counts_for_needs` |
| Need response count (gates) | Post a Need edit banner (meta); My Needs Delete handler (meta); REST PATCH after-quotes (meta); REST DELETE close (meta); admin Quotes (meta) | `post-need.php:58`; `class-reverse-listings.php:171,:995`; `class-needs-controller.php:767,:840` | `META_PREFIX . 'response_count'` |
