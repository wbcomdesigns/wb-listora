<?php
/**
 * General-purpose public helper functions — the documented Free→Pro surface
 * for cross-cutting utilities that don't belong to one feature.
 *
 * Pro (and site builders) consume these functions instead of referencing
 * Free's internal helper classes directly. Per the architecture contract
 * (INV-3), the function is the documented Free→Pro surface; the implementation
 * class is internal.
 *
 * @package WBListora
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_is_bot_request' ) ) {
	/**
	 * Whether the current request's User-Agent looks like a bot / crawler.
	 *
	 * The single canonical "is this a bot?" check for the whole plugin. Used by
	 * Free's analytics-lite view recording and anti-spam scoring, and consumed
	 * by Pro instead of referencing Free's internal
	 * `\WBListora\Bot_Detection` class directly.
	 *
	 * Conservative by design — catches the common self-identifying crawlers,
	 * link-preview fetchers, headless agents, and SEO spiders. A missing /
	 * empty User-Agent is treated as a bot. The verdict is filterable via
	 * `wb_listora_is_bot_request` and the signature list via
	 * `wb_listora_bot_signatures`.
	 *
	 * @since 1.2.0
	 *
	 * @param string|null $ua Optional explicit User-Agent to classify. When
	 *                        null (default), the current request's
	 *                        `HTTP_USER_AGENT` is read.
	 * @return bool True when the request / User-Agent is treated as a bot.
	 */
	function wb_listora_is_bot_request( $ua = null ): bool {
		if ( null === $ua ) {
			return \WBListora\Bot_Detection::is_bot_request();
		}

		return \WBListora\Bot_Detection::is_bot_user_agent( (string) $ua );
	}
}

if ( ! function_exists( 'wb_listora_sanitize_tile_url' ) ) {
	/**
	 * Sanitize a map tile URL template, keeping Leaflet's {z}/{x}/{y} placeholders.
	 *
	 * A tile template is not an ordinary URL. Leaflet substitutes {z}, {x} and
	 * {y} — and optionally {s} for a subdomain and {r} for retina — at request
	 * time, so those curly braces have to survive sanitization or the template
	 * stops being a template.
	 *
	 * esc_url_raw() alone cannot do this. Braces are not legal URL characters,
	 * so it strips them and returns a value that looks saved but requests
	 * https://tiles.example.com/z/x/y.png and 404s every tile (BC 10217195006).
	 * sanitize_text_field() keeps the braces but will happily store a
	 * javascript: scheme.
	 *
	 * Percent-encoding the braces first lets esc_url_raw() do the part it is
	 * good at — rejecting a non-http(s) scheme and stripping control
	 * characters — after which the placeholders are put back.
	 *
	 * @since 1.6.0
	 *
	 * @param string $url Raw tile URL template.
	 * @return string Sanitized template, or '' when the value is not a usable http(s) URL.
	 */
	function wb_listora_sanitize_tile_url( $url ): string {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$encoded = strtr(
			$url,
			array(
				'{' => '%7B',
				'}' => '%7D',
			)
		);

		$clean = esc_url_raw( $encoded, array( 'http', 'https' ) );

		if ( '' === $clean ) {
			return '';
		}

		return strtr(
			$clean,
			array(
				'%7B' => '{',
				'%7b' => '{',
				'%7D' => '}',
				'%7d' => '}',
			)
		);
	}
}

if ( ! function_exists( 'wb_listora_get_feature_allowlist_map' ) ) {
	/**
	 * Which feature terms each listing type permits.
	 *
	 * The submission block built this inline in its render closure, which made
	 * it unavailable to anything else — so the search facets, which need the
	 * same answer to refilter when a visitor changes type, had no way to ask.
	 * One definition, both consumers.
	 *
	 * Types with an EMPTY allowlist are omitted deliberately: empty means "not
	 * restricted", and a caller that finds no entry for a type should show
	 * everything rather than nothing.
	 *
	 * @since 1.7.0
	 *
	 * @param object[]|null $types Listing types. Defaults to all registered.
	 * @return array<string, int[]> Type slug => allowed feature term ids.
	 */
	function wb_listora_get_feature_allowlist_map( $types = null ): array {
		if ( null === $types ) {
			$registry = \WBListora\Core\Listing_Type_Registry::instance();
			$registry->init();
			$types = $registry->get_all();
		}

		$map = array();

		foreach ( (array) $types as $type ) {
			// get_slug() is called below, so it belongs in this guard too -- a type
			// object carrying one method but not the other would fatal here.
			if ( ! is_object( $type )
				|| ! method_exists( $type, 'get_allowed_features' )
				|| ! method_exists( $type, 'get_slug' ) ) {
				continue;
			}

			$allowed = array_values( array_filter( array_map( 'absint', (array) $type->get_allowed_features() ) ) );

			if ( ! empty( $allowed ) ) {
				$map[ $type->get_slug() ] = $allowed;
			}
		}

		return $map;
	}
}

if ( ! function_exists( 'wb_listora_get_terms_for_listing_type' ) ) {
	/**
	 * Terms for a listing type allowlist, or the full taxonomy when none is set.
	 *
	 * Categories already have a per-type allowlist. Features gained the same
	 * shape in 1.6.0 (BC 10213603029). An empty allowlist is "no restriction"
	 * so existing types keep showing every term until the owner picks some.
	 *
	 * @since 1.6.0
	 *
	 * @param string $taxonomy  `listora_listing_cat` or `listora_listing_feature`.
	 * @param string $type_slug Listing type slug. Empty = no type filter.
	 * @param array<string, mixed> $args Extra get_terms() args (`hide_empty`, `orderby`, …).
	 * @return \WP_Term[]
	 */
	function wb_listora_get_terms_for_listing_type( $taxonomy, $type_slug = '', $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$args['taxonomy'] = $taxonomy;

		$type_slug = sanitize_title( (string) $type_slug );
		if ( '' !== $type_slug ) {
			$registry = \WBListora\Core\Listing_Type_Registry::instance();
			$registry->init();
			$type = $registry->get( $type_slug );
			if ( $type ) {
				$include = array();
				if ( 'listora_listing_cat' === $taxonomy ) {
					$include = array_values( array_filter( array_map( 'absint', $type->get_allowed_categories() ) ) );
				} elseif ( 'listora_listing_feature' === $taxonomy && method_exists( $type, 'get_allowed_features' ) ) {
					$include = array_values( array_filter( array_map( 'absint', $type->get_allowed_features() ) ) );
				}
				if ( ! empty( $include ) ) {
					$args['include'] = $include;
					$args['orderby'] = 'include';
				}
			}
		}

		$terms = get_terms( $args );

		return is_wp_error( $terms ) ? array() : $terms;
	}
}

if ( ! function_exists( 'wb_listora_listing_type_query_args' ) ) {
	/**
	 * Query args that narrow a listing query to one listing type.
	 *
	 * Lives here so the dashboard's server render and `GET /dashboard/listings`
	 * cannot drift on what "this page is a Jobs dashboard" means - the web and
	 * the app would otherwise show a member two different sets of their own
	 * listings (card 10213596281).
	 *
	 * An unknown slug returns a clause that matches nothing rather than an
	 * empty array: a mistyped type must show an empty dashboard, not silently
	 * widen back to every listing the member owns.
	 *
	 * @since 1.8.0
	 *
	 * @param string $type_slug Listing-type slug, or '' for every type.
	 * @return array<string, mixed> Args to merge into WP_Query / get_posts.
	 */
	function wb_listora_listing_type_query_args( $type_slug ) {
		$type_slug = sanitize_title( (string) $type_slug );

		if ( '' === $type_slug ) {
			return array();
		}

		return array(
			'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded by post_author on every caller.
				array(
					'taxonomy' => 'listora_listing_type',
					'field'    => 'slug',
					'terms'    => array( $type_slug ),
				),
			),
		);
	}
}

if ( ! function_exists( 'wb_listora_count_user_listings' ) ) {
	/**
	 * Count one member's listings, optionally narrowed to a listing type.
	 *
	 * The type-less path keeps the plain COUNT(*) both callers already ran;
	 * with a type it goes through WP_Query so the taxonomy join is WordPress'
	 * problem rather than a second hand-written query to keep in step.
	 *
	 * @since 1.8.0
	 *
	 * @param int      $user_id   Member.
	 * @param string[] $statuses  Post statuses to include.
	 * @param string   $type_slug Listing-type slug, or '' for every type.
	 * @return int
	 */
	function wb_listora_count_user_listings( $user_id, array $statuses, $type_slug = '' ) {
		$user_id = (int) $user_id;

		if ( empty( $statuses ) ) {
			return 0;
		}

		$type_args = wb_listora_listing_type_query_args( $type_slug );

		if ( empty( $type_args ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listora_listing' AND post_author = %d AND post_status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...array_merge( array( $user_id ), array_values( $statuses ) )
				)
			);
		}

		$query = new WP_Query(
			array_merge(
				array(
					'post_type'      => 'listora_listing',
					'author'         => $user_id,
					'post_status'    => $statuses,
					'fields'         => 'ids',
					'posts_per_page' => 1,
				),
				$type_args
			)
		);

		return (int) $query->found_posts;
	}
}

if ( ! function_exists( 'wb_listora_member_listing_statuses' ) ) {
	/**
	 * Every post status a member's own listing can hold on their dashboard.
	 *
	 * Defined once because the two surfaces had already drifted twice. The
	 * sidebar badge and the rows query disagreed within the block itself, and
	 * then `GET /dashboard/listings` was left without `listora_payment` while
	 * the block had it - so a listing paused awaiting credits showed on the
	 * web dashboard and was invisible in the app, with the app's `total`
	 * agreeing with the omission (card 10318160202).
	 *
	 * `listora_payment` matters most of all: it is the state a member is meant
	 * to act on by topping up, and the app is where they would buy the credits.
	 *
	 * @since 1.8.0
	 *
	 * @return string[]
	 */
	function wb_listora_member_listing_statuses() {
		/**
		 * Filter the statuses a member sees on their own dashboard.
		 *
		 * @since 1.8.0
		 *
		 * @param string[] $statuses Post statuses.
		 */
		return (array) apply_filters(
			'wb_listora_member_listing_statuses',
			array( 'publish', 'pending', 'draft', 'listora_expired', 'listora_rejected', 'listora_deactivated', 'pending_verification', 'listora_payment' )
		);
	}
}

if ( ! function_exists( 'wb_listora_get_listing_owner_name' ) ) {
	/**
	 * The name to show publicly as the person behind a listing.
	 *
	 * A visitor had no way to see who was behind a listing at all - only the
	 * owner themselves saw an owner bar - and every major directory shows one
	 * (card 10222089571). Anonymous listings read as untrustworthy.
	 *
	 * The order matters. A listing's own public contact name wins, because the
	 * business is what the listing is about and the WordPress account behind it
	 * may be an agency, a staff member, or "admin". The account's display name
	 * is the fallback, never a login or an email address.
	 *
	 * Returns '' when the Owner Name feature is off, so every surface goes dark
	 * together rather than the toggle hiding one and leaving the REST field.
	 *
	 * @since 1.8.0
	 *
	 * @param int $post_id Listing ID.
	 * @return string Display-ready name, or '' when there is nothing to show.
	 */
	function wb_listora_get_listing_owner_name( $post_id ) {
		$post_id = (int) $post_id;

		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'owner_name' ) ) {
			return '';
		}

		$name = trim( (string) get_post_meta( $post_id, '_listora_contact_name', true ) );

		if ( '' === $name ) {
			$author = (int) get_post_field( 'post_author', $post_id );
			$user   = $author ? get_userdata( $author ) : false;
			$name   = $user ? trim( (string) $user->display_name ) : '';

			// WordPress sets display_name to the login until the user picks
			// something else, so "Listed by admin" handed out half of the
			// credentials (BC 10331945361). Fall back to the real name, or to
			// nothing, which hides the row.
			if ( $user && 0 === strcasecmp( $name, (string) $user->user_login ) ) {
				$name = trim( $user->first_name . ' ' . $user->last_name );
			}
		}

		/**
		 * Filter the public owner name for a listing.
		 *
		 * @since 1.8.0
		 *
		 * @param string $name    Resolved name, '' when nothing to show.
		 * @param int    $post_id Listing ID.
		 */
		return (string) apply_filters( 'wb_listora_listing_owner_name', $name, $post_id );
	}
}

if ( ! function_exists( 'wb_listora_get_listing_owner_url' ) ) {
	/**
	 * Where the public owner name links, if anywhere.
	 *
	 * Defaults to the WordPress author archive. Filtered because a community
	 * site wants the member profile instead - BuddyPress, BuddyNext and any
	 * membership plugin all have a better page than /author/<slug>/, and
	 * returning '' renders the name as plain text.
	 *
	 * @since 1.8.0
	 *
	 * @param int $post_id Listing ID.
	 * @return string URL, or '' to render the name unlinked.
	 */
	function wb_listora_get_listing_owner_url( $post_id ) {
		$post_id = (int) $post_id;
		$author  = (int) get_post_field( 'post_author', $post_id );
		$url     = $author ? (string) get_author_posts_url( $author ) : '';

		/**
		 * Filter the URL the public owner name links to.
		 *
		 * @since 1.8.0
		 *
		 * @param string $url     Author archive URL, or '' for no link.
		 * @param int    $post_id Listing ID.
		 * @param int    $author  Author user ID.
		 */
		return (string) apply_filters( 'wb_listora_listing_owner_url', $url, $post_id, $author );
	}
}

if ( ! function_exists( 'wb_listora_member_review_statuses_sql' ) ) {
	/**
	 * The review statuses a member sees in their OWN review list, as a SQL IN list.
	 *
	 * Approved, pending and rejected: a member should see what is waiting and
	 * what was turned down, each labelled. Spam is left out, the way WordPress
	 * never shows a comment author their spam. The web Reviews tab counted
	 * approved only while listing every status, spam included (421 on the
	 * badge, 35 pages of rows, no labels - BC 10331641303), and the REST
	 * profile counted a third number. Every count and list of "reviews I have
	 * written" goes through this, on the website and in the REST API.
	 *
	 * Fixed literals, so it is safe to interpolate into a prepared query.
	 *
	 * @since 1.9.0
	 *
	 * @return string e.g. 'approved','pending','rejected'
	 */
	function wb_listora_member_review_statuses_sql() {
		return "'approved','pending','rejected'";
	}
}

if ( ! function_exists( 'wb_listora_notification_enabled' ) ) {
	/**
	 * Whether an email event is switched on in Settings > Notifications.
	 *
	 * On unless the owner switched it off. The one check for Free's emails
	 * and Pro's (Pro's email sender calls it too), so a switch on that screen
	 * always stops its email (card 10337185716).
	 *
	 * @since 1.9.0
	 *
	 * @param string $event Event key.
	 * @return bool
	 */
	function wb_listora_notification_enabled( $event ) {
		$toggles = wb_listora_get_setting( 'notifications', array() );
		return ! is_array( $toggles ) || ! array_key_exists( (string) $event, $toggles ) || (bool) $toggles[ (string) $event ];
	}
}

if ( ! function_exists( 'wb_listora_credit_entry_types' ) ) {
	/**
	 * Credit ledger entry types: readable label and which way the row moves
	 * the balance, for the member's credit history and the owner's
	 * Transactions screen alike, so the two never name one row differently.
	 *
	 * `hold_release` is its own type since 1.9.0: approving a held charge
	 * releases the hold and then deducts, and calling the release a "Refund"
	 * made every charge read as refunded-then-charged (card 10337183564).
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, array{label: string, sign: string}> sign is
	 *         'positive', 'negative' or 'neutral'.
	 */
	function wb_listora_credit_entry_types() {
		$types = array(
			'topup'        => array(
				'label' => __( 'Top-up', 'wb-listora' ),
				'sign'  => 'positive',
			),
			'refund'       => array(
				'label' => __( 'Refund', 'wb-listora' ),
				'sign'  => 'positive',
			),
			'hold'         => array(
				'label' => __( 'On hold', 'wb-listora' ),
				'sign'  => 'negative',
			),
			'hold_release' => array(
				'label' => __( 'Hold released', 'wb-listora' ),
				'sign'  => 'neutral',
			),
			'deduction'    => array(
				'label' => __( 'Spent', 'wb-listora' ),
				'sign'  => 'negative',
			),
		);

		/**
		 * Filter the credit ledger entry types.
		 *
		 * @since 1.9.0
		 *
		 * @param array $types type => [ label, sign ].
		 */
		return (array) apply_filters( 'wb_listora_credit_entry_types', $types );
	}
}

if ( ! function_exists( 'wb_listora_credit_gateway_label' ) ) {
	/**
	 * A gateway/adapter's display name, translated in this plugin's own
	 * text domain.
	 *
	 * The SDK's `get_label()` wraps its return value in the
	 * `wbcom-credits-sdk` text domain, but no consumer ever loads that
	 * domain (nor will one exist once the SDK finishes going headless —
	 * see the SDK's `docs/HEADLESS-PLAN.md`), so calling it directly always
	 * renders in English regardless of the site's language. This plugin
	 * owns the label instead.
	 *
	 * @since 1.10.0
	 *
	 * @param string $gateway_id SDK gateway/adapter id ('stripe', 'paypal',
	 *                           'woocommerce', 'pmpro', 'memberpress', …).
	 * @return string Translated label, or the id itself for an id this
	 *                plugin doesn't know yet.
	 */
	function wb_listora_credit_gateway_label( $gateway_id ) {
		$labels = array(
			'stripe'      => __( 'Stripe', 'wb-listora' ),
			'paypal'      => __( 'PayPal', 'wb-listora' ),
			'woocommerce' => __( 'WooCommerce', 'wb-listora' ),
			'pmpro'       => __( 'Paid Memberships Pro', 'wb-listora' ),
			'memberpress' => __( 'MemberPress', 'wb-listora' ),
		);

		/**
		 * Filter the credit gateway/adapter label map.
		 *
		 * @since 1.10.0
		 *
		 * @param array $labels gateway_id => translated label.
		 */
		$labels = (array) apply_filters( 'wb_listora_credit_gateway_labels', $labels );

		return isset( $labels[ $gateway_id ] ) ? (string) $labels[ $gateway_id ] : (string) $gateway_id;
	}
}

if ( ! function_exists( 'wb_listora_credit_reason_label' ) ) {
	/**
	 * A ledger row's `reason` column, as a translated line for the credit
	 * history "Note" column.
	 *
	 * The stored `note` column is written by the Credits SDK's adapters and
	 * gateways as a hardcoded English string (SDK `docs/HEADLESS-PLAN.md`
	 * row 13 — the SDK owns no user-visible text). `reason` and `reference`
	 * are the structured columns meant to replace it; this maps the reasons
	 * this plugin and its SDK actually write today. A row with no
	 * recognized reason (mainly older purchase rows, or rows the SDK wrote
	 * before it passed a reason) falls back to the raw stored `note`.
	 *
	 * @since 1.10.0
	 *
	 * @param string $reason    Ledger row's `reason` column.
	 * @param string $reference Ledger row's `reference` column.
	 * @return string Translated line, or '' when $reason isn't recognized
	 *                (caller should fall back to the row's `note`).
	 */
	function wb_listora_credit_reason_label( $reason, $reference ) {
		if ( '' === $reason ) {
			return '';
		}

		switch ( $reason ) {
			case 'admin_refund':
				return __( 'Refunded by an administrator', 'wb-listora' );
			case 'gateway_refund':
			case 'hold_refund':
				return __( 'Refunded', 'wb-listora' );
		}

		/**
		 * Filter the translated line for a ledger reason this plugin
		 * doesn't otherwise recognize (e.g. one Pro or a Pro feature adds).
		 *
		 * @since 1.10.0
		 *
		 * @param string $label     Empty by default — return '' to keep
		 *                          falling back to the row's `note`.
		 * @param string $reason    Ledger row's `reason` column.
		 * @param string $reference Ledger row's `reference` column.
		 */
		return (string) apply_filters( 'wb_listora_credit_reason_label', '', $reason, $reference );
	}
}

if ( ! function_exists( 'wb_listora_event_label' ) ) {
	/**
	 * A readable name for an event or action key ("listing_submitted" ->
	 * "New listing submitted").
	 *
	 * The Email Log, Audit Log and Webhooks each printed raw keys, and each
	 * humanised a different subset (card 10337184050). One map for all three:
	 * Free's email events, Free's listing actions, and whatever Pro adds
	 * through the filter; any other key is turned into words rather than
	 * shown raw.
	 *
	 * @since 1.9.0
	 *
	 * @param string $key Event or action key.
	 * @return string
	 */
	function wb_listora_event_label( $key ) {
		static $labels = null;
		if ( null === $labels ) {
			$labels = array(
				'listing_created'   => __( 'Listing created', 'wb-listora' ),
				'listing_updated'   => __( 'Listing updated', 'wb-listora' ),
				'listing_deleted'   => __( 'Listing deleted', 'wb-listora' ),
				'listing_published' => __( 'Listing published', 'wb-listora' ),
				'listing_claimed'   => __( 'Listing claimed', 'wb-listora' ),
				'listing_featured'  => __( 'Listing featured', 'wb-listora' ),
				'listing_paused'    => __( 'Listing paused', 'wb-listora' ),
				'review_posted'     => __( 'Review posted', 'wb-listora' ),
				'review_approved'   => __( 'Review approved', 'wb-listora' ),
				'review_rejected'   => __( 'Review rejected', 'wb-listora' ),
				'review_deleted'    => __( 'Review deleted', 'wb-listora' ),
				'claim_updated'     => __( 'Claim updated', 'wb-listora' ),
				'test'              => __( 'Test email', 'wb-listora' ),
			);
			foreach ( \WBListora\Admin\Email_Templates_Page::get_event_map() as $event => $def ) {
				$labels[ $event ] = (string) $def['label'];
			}

			/**
			 * Filter the readable names of event and action keys shown in the
			 * Email Log, Audit Log and Webhooks.
			 *
			 * @since 1.9.0
			 *
			 * @param array<string, string> $labels Key => label.
			 */
			$labels = (array) apply_filters( 'wb_listora_event_labels', $labels );
		}
		$key = (string) $key;
		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}
		return '' === $key ? __( 'Unknown', 'wb-listora' ) : ucfirst( str_replace( array( '_', '-', '.' ), ' ', $key ) );
	}
}

if ( ! function_exists( 'wb_listora_listing_is_interactable' ) ) {
	/**
	 * Whether a listing may be favorited, claimed, or reviewed by the given
	 * (or current) user.
	 *
	 * A published listing is open to everyone. A non-public one (draft,
	 * pending, rejected, deactivated, expired, awaiting payment) is only
	 * interactable by its own author or someone who can moderate — so a
	 * member cannot favorite/claim/review another member's unpublished
	 * listing before it has even been approved (card 10346159335).
	 *
	 * @since 1.9.0
	 *
	 * @param \WP_Post|int $post    Listing post or ID.
	 * @param int|null     $user_id User to check, or null for the current user.
	 * @return bool
	 */
	function wb_listora_listing_is_interactable( $post, $user_id = null ) {
		$post = get_post( $post );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return false;
		}

		if ( 'publish' === $post->post_status ) {
			return true;
		}

		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id && (int) $post->post_author === $user_id ) {
			return true;
		}

		return current_user_can( 'edit_others_posts' );
	}
}
