<?php
/**
 * Privacy / GDPR public helper functions — the plugin-level erasure policy.
 *
 * {@see wb_listora_get_erasure_map()} is the single answer to "what happens to
 * each table when a member's data must go". Pro and site owners extend it via
 * `wb_listora_erasure_map`; `bin/check-erasure-map.php` fails the build when a
 * table with a user column has no entry.
 *
 * Two plans per entry, kept separate: `on_account_deletion` (user row gone, a
 * leftover `user_id` is an orphan and may be retained) and `on_privacy_erasure`
 * (user row survives, so pointer rows still identify a person and must go).
 * A plan has `strategy`, `handled_by`, `columns` (anonymize only) and an
 * untranslated developer-facing `reason`.
 *
 * @package WBListora\Privacy
 * @since   1.2.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_get_erasure_map_defaults' ) ) {
	/**
	 * Free's built-in erasure policy, before any filtering.
	 *
	 * Deliberately PURE DATA: no `apply_filters()`, no `__()`, no `$wpdb`, no
	 * WordPress calls of any kind. That is what lets `bin/check-erasure-map.php`
	 * require this file and read the policy with zero WordPress bootstrap, so the
	 * Rule 9 build gate runs in local-CI without a database.
	 *
	 * Do not add WordPress calls here — put them in the filtered wrapper below.
	 *
	 * @since 1.2.3
	 *
	 * @return array<string, array<string, mixed>> Erasure map keyed by table short-name.
	 */
	function wb_listora_get_erasure_map_defaults(): array {
		return array(

			// -------------------------------------------------------------
			// Reviews — ANONYMIZE. Shipped pre-1.2.3; policy unchanged.
			// -------------------------------------------------------------
			'reviews'            => array(
				'owner'               => 'free',
				'user_columns'        => array( 'user_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'anonymize',
					'handled_by' => 'privacy_eraser',
					'columns'    => array(),
					'reason'     => 'The row is retained (identity stripped) so the listing\'s overall_rating stays in its aggregate. Deleting reviews would silently rewrite every listing\'s star rating whenever a member leaves — punishing listing owners for someone else\'s account closure. Owned by Privacy_Eraser, which also recomputes the affected listings\' aggregates through the canonical recompute entry point.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'anonymize',
					'handled_by' => 'privacy_eraser',
					'columns'    => array(),
					'reason'     => 'Same policy as account deletion: identity + free-text PII are stripped, the rating survives. Anonymised rows carry user_id = 0, so nothing links back to the live account.',
				),
			),

			// -------------------------------------------------------------
			// Favorites — DELETE. Shipped pre-1.2.3; policy unchanged.
			// -------------------------------------------------------------
			'favorites'          => array(
				'owner'               => 'free',
				'user_columns'        => array( 'user_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'delete',
					'handled_by' => 'privacy_eraser',
					'columns'    => array(),
					'reason'     => 'A favorite is pure personal data (what this person likes) with no shared aggregate to preserve. Nothing is lost by deleting it.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'delete',
					'handled_by' => 'privacy_eraser',
					'columns'    => array(),
					'reason'     => 'Same as account deletion — no retention basis.',
				),
			),

			// -------------------------------------------------------------
			// Claims — DELETE. Shipped pre-1.2.3; policy unchanged.
			// -------------------------------------------------------------
			'claims'             => array(
				'owner'               => 'free',
				// NOTE: `reviewed_by` is a SECOND user column, and it does NOT
				// point at the data subject — it points at the MODERATOR who
				// actioned the claim. It is declared here so the Rule 9 gate
				// sees the column is accounted for rather than silently
				// unmapped. Its handling is deliberately asymmetric: see
				// `secondary_user_columns` below.
				'user_columns'        => array( 'user_id', 'reviewed_by' ),
				'on_account_deletion' => array(
					'strategy'   => 'delete',
					'handled_by' => 'privacy_eraser',
					'columns'    => array(),
					'reason'     => 'The proof text / proof files are the claimant\'s PII. The listing itself is unaffected. Matched on user_id (the claimant) only.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'delete',
					'handled_by' => 'privacy_eraser',
					'columns'    => array(),
					'reason'     => 'Same as account deletion — no retention basis.',
				),
				// Columns that reference a user who is NOT the data subject.
				// Erasure must never match on these: deleting a claim because
				// the MODERATOR who approved it closed their account would
				// destroy an unrelated member's claim record.
				'secondary_user_columns' => array(
					'reviewed_by' => array(
						'strategy' => 'retain',
						'reason'   => 'Points at the moderator who reviewed the claim, not the claimant. When that moderator deletes their own account the value becomes an orphaned integer — a pointer to nobody, not personal data. Never used as an erasure match column.',
					),
				),
			),

			// -------------------------------------------------------------
			// Payments — ANONYMIZE, RETAIN THE ROW. The money trail.
			// -------------------------------------------------------------
			'payments'           => array(
				'owner'               => 'free',
				'user_columns'        => array( 'user_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'anonymize',
					'handled_by' => 'account_eraser',
					'columns'    => array(
						'billing_name'  => '',
						'billing_email' => '',
					),
					'reason'     => 'A financial record. Retention has a lawful basis under GDPR Art. 17(3)(b) (compliance with a legal obligation — tax/accounting record-keeping), so the row STAYS and the site owner keeps an intact money trail. Only the identity columns are stripped. user_id is deliberately KEPT: once wp_delete_user() has run it is an orphaned integer pointing at nobody, which is not personal data, and keeping it preserves the ability to group a former customer\'s payments for accounting/reconciliation.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'anonymize',
					'handled_by' => 'account_eraser',
					'columns'    => array(
						'billing_name'  => '',
						'billing_email' => '',
					),
					'reason'     => 'Same lawful retention basis (Art. 17(3)(b)), so the row stays here too. The identity columns are still stripped. user_id is retained on this path as well — but note the account is NOT deleted here, so that pointer DOES still resolve to a living person. That is accepted deliberately: an accounting record the law requires us to keep is worthless if it cannot be tied to the transaction it documents. The eraser reports items_retained = true and tells the user exactly this, which is what Art. 17(3) requires — not silence.',
				),
			),

			// -------------------------------------------------------------
			// Review votes — pointer-only. THE TWO PATHS DIVERGE HERE.
			// -------------------------------------------------------------
			'review_votes'       => array(
				'owner'               => 'free',
				'user_columns'        => array( 'user_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'retain',
					'handled_by' => 'none',
					'columns'    => array(),
					'reason'     => 'Verified against the live schema: the row is (user_id, review_id, created_at) — a pointer and a timestamp, no PII in the row itself. Once wp_delete_user() removes the wp_users row, user_id is an orphaned integer that resolves to nobody. Retaining it keeps each review\'s helpful_count honest.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'delete',
					'handled_by' => 'account_eraser',
					'columns'    => array(),
					'reason'     => 'DIVERGES from account deletion ON PURPOSE. WordPress core\'s eraser does NOT delete the user account, so user_id still resolves to a LIVE, identifiable person and "this person voted on that review" is personal data about them. The orphaned-pointer argument does not hold on this path, so the rows go.',
				),
			),

			// -------------------------------------------------------------
			// Search index — denormalised mirror; follows the listing.
			// -------------------------------------------------------------
			'search_index'       => array(
				'owner'               => 'free',
				'user_columns'        => array( 'author_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'retain',
					'handled_by' => 'listing_lifecycle',
					'columns'    => array(),
					'reason'     => 'Not independent PII — a denormalised mirror of a listing row, rebuilt by the indexer. Whatever happens to the listing (trash / delete / reassign) propagates here through the listing\'s own lifecycle hooks. Writing it directly from an eraser would fight the indexer and drift.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'retain',
					'handled_by' => 'listing_lifecycle',
					'columns'    => array(),
					'reason'     => 'Same reasoning. Core\'s privacy tool does not touch listings, so the mirror correctly keeps mirroring them.',
				),
			),

			// -------------------------------------------------------------
			// Space listings (BuddyNext showcase links) — RETAIN, follows the listing.
			// -------------------------------------------------------------
			'space_listings'     => array(
				'owner'                  => 'free',
				'user_columns'           => array( 'submitted_by', 'approved_by' ),
				'on_account_deletion'    => array(
					'strategy'   => 'retain',
					'handled_by' => 'listing_lifecycle',
					'columns'    => array(),
					'reason'     => 'A row records that a listing is shown in a space - no text, no contact data, only ids. It follows the listing: wp_delete_user() trashes or reassigns the member\'s listings, a trashed listing drops out of the showcase (it hydrates published listings only), and the before_delete_post listener in Plugin removes every row when the listing is permanently deleted. A reassigned listing keeps its link, and submitted_by becomes an orphaned integer pointing at nobody.',
				),
				'on_privacy_erasure'     => array(
					'strategy'   => 'retain',
					'handled_by' => 'listing_lifecycle',
					'columns'    => array(),
					'reason'     => 'Core\'s privacy tool does not touch listings, so the listing stays published and its space membership - approved by that space\'s team - stays with it. Deleting the row would silently pull a live business out of a space; the only personal data is the submitter id, which is the listing author already public on the listing itself.',
				),
				// approved_by points at the space moderator, not the data subject.
				'secondary_user_columns' => array(
					'approved_by' => array(
						'strategy' => 'retain',
						'reason'   => 'Points at the moderator who approved the link, not the member. When that moderator deletes their account it becomes an orphaned integer. Never used as an erasure match column.',
					),
				),
			),

			// -------------------------------------------------------------
			// SDK-owned financial tables. Listora must not write these.
			// -------------------------------------------------------------
			'credit_ledger'      => array(
				'owner'               => 'sdk',
				'user_columns'        => array( 'user_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'retain',
					'handled_by' => 'none',
					'columns'    => array(),
					'reason'     => 'Verified against the live schema: (user_id, item_id, entry_type, amount, note, created_at). `note` is free text but is SYSTEM-generated ("Submission deduction — Standard plan"), never member-authored, so it carries no PII. The ledger is a financial record (Art. 17(3)(b) retention) and is owned by the Wbcom Credits SDK — Listora does not write another plugin\'s ledger. After wp_delete_user() the user_id is an orphaned integer.',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'retain',
					'handled_by' => 'none',
					'columns'    => array(),
					'reason'     => 'Retained under the same lawful basis (financial record). Reported to the user as retained rather than silently kept.',
				),
			),

			'credit_gateway_log' => array(
				'owner'               => 'sdk',
				'user_columns'        => array( 'user_id' ),
				'on_account_deletion' => array(
					'strategy'   => 'retain',
					'handled_by' => 'none',
					'columns'    => array(),
					'reason'     => 'Verified against the live schema: gateway/session/payment-intent references + amounts. No name, no email, no free text. Payment-processor reconciliation record (Art. 17(3)(b)) and SDK-owned. Orphaned pointer after wp_delete_user().',
				),
				'on_privacy_erasure'  => array(
					'strategy'   => 'retain',
					'handled_by' => 'none',
					'columns'    => array(),
					'reason'     => 'Retained under the same lawful basis. Reported as retained.',
				),
			),
		);
	}
}

if ( ! function_exists( 'wb_listora_get_erasure_map' ) ) {
	/**
	 * The plugin-level erasure policy: what happens to every table that holds a
	 * pointer to a WordPress user when that user's data must go.
	 *
	 * Pro registers its own tables on this filter. Site owners extend it where
	 * they have their own lawful basis to retain (or a stricter policy to
	 * delete). See the file docblock for the entry shape and the two-path model.
	 *
	 * @since 1.2.3
	 *
	 * @return array<string, array<string, mixed>> Erasure map keyed by table short-name.
	 */
	function wb_listora_get_erasure_map(): array {
		/**
		 * Filters the plugin-level erasure map.
		 *
		 * This is where Pro (and any integration) declares the tables it owns,
		 * and where a site owner overrides Listora's default retention policy
		 * for their own jurisdiction / lawful basis.
		 *
		 * Removing an entry does NOT make the table safe — it makes it
		 * unmanaged, and the Rule 9 build gate will fail. To keep a table's
		 * rows, set its strategy to `retain` with a documented `reason`; that
		 * records the decision instead of hiding it.
		 *
		 * @since 1.2.3
		 *
		 * @param array<string, array<string, mixed>> $map Erasure map keyed by table short-name.
		 */
		return (array) apply_filters( 'wb_listora_erasure_map', wb_listora_get_erasure_map_defaults() );
	}
}

if ( ! function_exists( 'wb_listora_is_account_deactivated' ) ) {
	/**
	 * Whether a member's account is currently deactivated.
	 *
	 * The canonical check — every read site routes through this rather than
	 * reading the meta key directly, so the storage shape stays private.
	 *
	 * @since 1.2.3
	 *
	 * @param int $user_id User ID to test. Defaults to the current user.
	 * @return bool True when the account is deactivated.
	 */
	function wb_listora_is_account_deactivated( $user_id = 0 ): bool {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		$deactivated = (bool) get_user_meta( $user_id, '_listora_account_deactivated', true );

		/**
		 * Filters whether an account counts as deactivated.
		 *
		 * @since 1.2.3
		 *
		 * @param bool $deactivated Whether the account is deactivated.
		 * @param int  $user_id     User ID being tested.
		 */
		return (bool) apply_filters( 'wb_listora_is_account_deactivated', $deactivated, $user_id );
	}
}
