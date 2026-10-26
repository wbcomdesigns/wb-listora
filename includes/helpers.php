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
	 * The plugin's one bot check (analytics, anti-spam, and Pro). Conservative;
	 * an empty User-Agent counts as a bot. Filterable via
	 * `wb_listora_is_bot_request` and `wb_listora_bot_signatures`.
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
	 * esc_url_raw() strips the braces Leaflet needs ({z}, {x}, {y}, {s}, {r});
	 * sanitize_text_field() would keep a javascript: scheme. So the braces are
	 * percent-encoded, esc_url_raw() enforces http(s) and strips control
	 * characters, then the placeholders are restored.
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
	 * Shared by the submission block and the search facets. Types with an
	 * empty allowlist are omitted: empty means "not restricted", so a caller
	 * finding no entry should show everything.
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

/**
 * How many published listings of each type use each term of a taxonomy.
 *
 * Answers "which categories / features does this type actually use" for
 * types the owner did not restrict, and "which type does this category
 * belong to" when no type lists it. One grouped query, cached until any
 * post or term changes.
 *
 * @since 2.0.0
 *
 * @param string $taxonomy Listing taxonomy (listora_listing_cat, listora_listing_feature, ...).
 * @return array<int, array<string, int>> Term ID => type slug => listing count.
 */
function wb_listora_term_type_usage( string $taxonomy ): array {
	$key   = 'term_type_usage:' . $taxonomy . ':' . wp_cache_get_last_changed( 'terms' ) . ':' . wp_cache_get_last_changed( 'posts' );
	$usage = wp_cache_get( $key, 'wb_listora' );
	if ( is_array( $usage ) ) {
		return $usage;
	}

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- one grouped read over core term tables, cached above.
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id, t.slug AS type_slug, COUNT(*) AS n
			FROM {$wpdb->term_relationships} tr
			JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
			JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'listora_listing' AND p.post_status = 'publish'
			JOIN {$wpdb->term_relationships} trt ON trt.object_id = p.ID
			JOIN {$wpdb->term_taxonomy} ttt ON ttt.term_taxonomy_id = trt.term_taxonomy_id AND ttt.taxonomy = 'listora_listing_type'
			JOIN {$wpdb->terms} t ON t.term_id = ttt.term_id
			GROUP BY tt.term_id, t.slug",
			$taxonomy
		)
	);
	$usage = array();
	foreach ( (array) $rows as $row ) {
		$usage[ (int) $row->term_id ][ (string) $row->type_slug ] = (int) $row->n;
	}
	wp_cache_set( $key, $usage, 'wb_listora', DAY_IN_SECONDS );
	return $usage;
}

/**
 * Feature term IDs the Directory's Features filter offers per type.
 *
 * A type with "selected only" features offers those; an unrestricted type
 * offers the features its listings actually use, so choosing Restaurant does
 * not list Telemedicine. The submission form keeps every feature for
 * unrestricted types (wb_listora_get_feature_allowlist_map()).
 *
 * @since 2.0.0
 *
 * @return array<string, int[]> Type slug => feature term IDs.
 */
function wb_listora_get_feature_filter_map(): array {
	$restricted = wb_listora_get_feature_allowlist_map();
	$map        = $restricted;
	foreach ( wb_listora_term_type_usage( 'listora_listing_feature' ) as $term_id => $types ) {
		foreach ( array_keys( $types ) as $slug ) {
			if ( ! isset( $restricted[ $slug ] ) ) {
				$map[ $slug ][] = (int) $term_id;
			}
		}
	}
	return $map;
}

if ( ! function_exists( 'wb_listora_get_terms_for_listing_type' ) ) {
	/**
	 * Terms for a listing type allowlist, or the full taxonomy when none is set.
	 *
	 * Categories and features both have a per-type allowlist. An empty
	 * allowlist is "no restriction", so every term shows until the owner picks.
	 *
	 * @since 1.6.0
	 *
	 * @param string               $taxonomy  `listora_listing_cat` or `listora_listing_feature`.
	 * @param string               $type_slug Listing type slug. Empty = no type filter.
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
	 * Shared by the dashboard's server render and `GET /dashboard/listings` so
	 * web and app show the same listings. An unknown slug matches nothing
	 * rather than widening back to every listing the member owns.
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

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- direct core-table query no WP API covers
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listora_listing' AND post_author = %d AND post_status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- status IN list is generated %s placeholders.
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
	 * The one list for the block's badge and rows and `GET /dashboard/listings`.
	 * It must include `listora_payment`: that is the state a member acts on by
	 * buying credits, often in the app.
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

/**
 * The site's calendar date, today or N days ago (Y-m-d).
 *
 * Day buckets (analytics event_date, search terms) are written in the site's
 * calendar, so every window over them is cut with this, never gmdate() or SQL
 * NOW(), which follow UTC and the database server's own zone.
 *
 * @since 2.0.0
 *
 * @param int $days_ago Days before today.
 * @return string
 */
function wb_listora_site_date( int $days_ago = 0 ): string {
	return ( new \DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . max( 0, $days_ago ) . ' days' )->format( 'Y-m-d' );
}

/**
 * A member's public name: their display name, or '' while it is still the login
 * WordPress set (half of their credentials). Every public surface names members
 * through here, so the rule cannot differ between them.
 *
 * @since 2.0.0
 *
 * @param \WP_User|null|false $user Member.
 * @return string Public name, or '' when the member never chose one.
 */
function wb_listora_user_public_name( $user ): string {
	if ( ! $user instanceof \WP_User ) {
		return '';
	}
	$name = trim( (string) $user->display_name );
	if ( 0 === strcasecmp( $name, (string) $user->user_login ) ) {
		$name = '';
	}

	/**
	 * Filter: the public name of a member.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $name '' when the display name is still the login.
	 * @param \WP_User $user Member.
	 */
	return (string) apply_filters( 'wb_listora_user_public_name', $name, $user );
}

if ( ! function_exists( 'wb_listora_get_listing_owner_name' ) ) {
	/**
	 * The name to show publicly as the person behind a listing.
	 *
	 * The listing's own public contact name wins (the account may be an agency
	 * or "admin"); the account's display name is the fallback, never a login
	 * or email. Returns '' when the Owner Name feature is off, so every
	 * surface, REST included, goes dark together.
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
			$name   = wb_listora_user_public_name( $user );

			// A name the member never chose is not published: not the login and
			// not their private first/last name, unless a site opts in.
			if ( $user && '' === $name ) {
				/**
				 * Filter: show the account's first + last name when the member never chose a display name.
				 *
				 * @since 1.9.1
				 *
				 * @param bool     $use_real_name Default false (hide the row).
				 * @param \WP_User $user          Listing author.
				 * @param int      $post_id       Listing ID.
				 */
				$name = apply_filters( 'wb_listora_owner_name_use_real_name', false, $user, $post_id )
					? trim( $user->first_name . ' ' . $user->last_name )
					: '';
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
	 * The community profile when there is one, else the author archive when
	 * the owner has published posts. An owner with no posts gets plain text:
	 * their archive is a "Nothing Found" page. Filtered so BuddyNext or a
	 * membership plugin can link its own profile page.
	 *
	 * @since 1.8.0
	 *
	 * @param int $post_id Listing ID.
	 * @return string URL, or '' to render the name unlinked.
	 */
	function wb_listora_get_listing_owner_url( $post_id ) {
		$post_id = (int) $post_id;
		$author  = (int) get_post_field( 'post_author', $post_id );
		$url     = $author ? wb_listora_community_profile_url( $author ) : '';
		if ( '' === $url && $author && count_user_posts( $author, 'post', true ) > 0 ) {
			$url = (string) get_author_posts_url( $author );
		}

		/**
		 * Filter the URL the public owner name links to.
		 *
		 * @since 1.8.0
		 *
		 * @param string $url     Community profile or author archive URL, or '' for no link.
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
	 * Approved, pending and rejected; spam is left out, as WordPress never
	 * shows a comment author their spam. Every count and list of "reviews I
	 * have written", web and REST, uses this. Fixed literals, so it is safe to
	 * interpolate into a prepared query.
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
	 * and Pro's, so a switch on that screen always stops its email.
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
	 * the balance, shared by the member's credit history and the owner's
	 * Transactions screen. `hold_release` is its own type so an approved
	 * held charge does not read as a refund.
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
			'purchase'     => array(
				'label' => __( 'Purchase', 'wb-listora' ),
				'sign'  => 'positive',
			),
			'adjustment'   => array(
				'label' => __( 'Adjustment', 'wb-listora' ),
				'sign'  => '',
			),
			'expiry'       => array(
				'label' => __( 'Expired', 'wb-listora' ),
				'sign'  => 'negative',
			),
		);

		/**
		 * Filter the credit ledger entry types.
		 *
		 * @since 1.9.0
		 * @since 2.1.0 Keyed by wb_listora_credit_entry_kind(); adds purchase, adjustment and expiry.
		 *
		 * @param array $types kind => [ label, sign ]. An empty sign follows the amount.
		 */
		return (array) apply_filters( 'wb_listora_credit_entry_types', $types );
	}
}

/**
 * A member's community profile URL (BuddyPress or BuddyBoss), or '' when no
 * community plugin can give one.
 *
 * BuddyPress 12 added bp_members_get_user_url(); BuddyBoss and older
 * BuddyPress only have bp_core_get_user_domain().
 *
 * @since 2.1.0
 *
 * @param int $user_id Member.
 * @return string
 */
function wb_listora_community_profile_url( int $user_id ): string {
	if ( $user_id <= 0 || ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'members' ) ) {
		return '';
	}
	if ( function_exists( 'bp_members_get_user_url' ) ) {
		return (string) bp_members_get_user_url( $user_id );
	}
	if ( function_exists( 'bp_core_get_user_domain' ) ) {
		return (string) bp_core_get_user_domain( $user_id );
	}
	return '';
}

/**
 * The reason an old ledger row stands for, from the fixed note the SDK wrote
 * before it stored a reason; '' when the note is not one of those.
 *
 * @since 2.1.0
 *
 * @param string $note Ledger row note.
 * @return string
 */
function wb_listora_credit_legacy_reason( string $note ): string {
	$legacy = array(
		'Hold released'             => 'hold_release',
		'Hold released on approval' => 'hold_release',
		'Credits held'              => 'hold',
		'Credits spent'             => 'spend',
		'Credits refunded'          => 'refund',
		'Admin adjustment'          => 'admin_adjust',
	);
	return $legacy[ trim( $note ) ] ?? '';
}

/**
 * What a credit ledger row is, read from its reason and its type together.
 *
 * The type alone misleads: the SDK writes a gateway refund and an
 * administrator's removal as `deduction`, and a hold release as `refund`.
 * Returns a key of wb_listora_credit_entry_types().
 *
 * @since 2.1.0
 *
 * @param array<string,mixed>|object $entry Ledger row.
 * @return string
 */
function wb_listora_credit_entry_kind( $entry ): string {
	$entry  = (array) $entry;
	$type   = (string) ( $entry['entry_type'] ?? '' );
	$reason = (string) ( $entry['reason'] ?? '' );
	if ( '' === $reason ) {
		$reason = wb_listora_credit_legacy_reason( (string) ( $entry['note'] ?? '' ) );
	}

	switch ( $reason ) {
		case 'purchase':
		case 'topup':
		case 'hold':
		case 'hold_release':
		case 'expiry':
			return $reason;
		case 'spend':
			return 'deduction';
		case 'admin_adjust':
			return 'adjustment';
		case 'refund':
		case 'gateway_refund':
		case 'admin_refund':
		case 'hold_refund':
			return 'refund';
		case '':
			// Older rows: a spend always names its listing or need; a removal by an administrator does not.
			if ( 'deduction' === $type ) {
				return (int) ( $entry['item_id'] ?? 0 ) > 0 ? 'deduction' : 'adjustment';
			}
			if ( 'refund' === $type && (int) ( $entry['hold_id'] ?? 0 ) > 0 ) {
				return 'hold_release';
			}
	}

	return $type;
}

if ( ! function_exists( 'wb_listora_credit_gateway_label' ) ) {
	/**
	 * A gateway/adapter's display name, translated in this plugin's own
	 * text domain (the SDK's `get_label()` uses a text domain nothing loads).
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
			'stripe'            => __( 'Stripe', 'wb-listora' ),
			'paypal'            => __( 'PayPal', 'wb-listora' ),
			'woocommerce'       => __( 'WooCommerce', 'wb-listora' ),
			'pmpro'             => __( 'Paid Memberships Pro', 'wb-listora' ),
			'memberpress'       => __( 'MemberPress', 'wb-listora' ),
			'woo_memberships'   => __( 'WooCommerce Memberships', 'wb-listora' ),
			'woo_subscriptions' => __( 'WooCommerce Subscriptions', 'wb-listora' ),
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
	 * A ledger row's `reason` as a translated line; '' for a reason this does
	 * not know (the caller falls back to `note`).
	 *
	 * @since 1.10.0
	 * @since 2.0.0 Every SDK reason; $item_id names the listing or need.
	 *
	 * @param string $reason    Ledger row's `reason` column.
	 * @param string $reference Ledger row's `reference` column.
	 * @param int    $item_id   Ledger row's `item_id` (listing or need), 0 for none.
	 * @return string Translated line, or '' when $reason isn't recognized.
	 */
	function wb_listora_credit_reason_label( $reason, $reference, $item_id = 0 ) {
		$item = $item_id > 0 ? get_the_title( (int) $item_id ) : '';

		switch ( (string) $reason ) {
			case 'purchase':
				return __( 'Credits purchased', 'wb-listora' );
			case 'topup':
				return __( 'Credits added', 'wb-listora' );
			case 'admin_adjust':
				return __( 'Adjusted by an administrator', 'wb-listora' );
			case 'hold':
				/* translators: %s: listing or need title. */
				return '' !== $item ? sprintf( __( 'Held for %s', 'wb-listora' ), $item ) : __( 'Credits held', 'wb-listora' );
			case 'hold_release':
				return __( 'Hold released', 'wb-listora' );
			case 'spend':
				/* translators: %s: listing or need title. */
				return '' !== $item ? sprintf( __( 'Spent on %s', 'wb-listora' ), $item ) : __( 'Credits spent', 'wb-listora' );
			case 'expiry':
				return __( 'Unused credits expired', 'wb-listora' );
			case 'admin_refund':
				return __( 'Refunded by an administrator', 'wb-listora' );
			case 'refund':
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

/**
 * The line a person reads for one credit ledger row, wherever it is shown
 * (member dashboard, REST history, admin Transactions, receipts).
 *
 * SDK notes ("Credits spent", "gateway:stripe:cs_...", its adapter text)
 * show the translated reason; only the SDK writes purchase and
 * gateway_refund rows. A note Listora or an administrator wrote is kept.
 *
 * @since 2.0.0
 *
 * @param array<string,mixed>|object $entry Ledger row.
 * @return string
 */
function wb_listora_credit_note( $entry ): string {
	$entry  = (array) $entry;
	$note   = trim( (string) ( $entry['note'] ?? '' ) );
	$reason = (string) ( $entry['reason'] ?? '' );
	// Rows written before the SDK stored a reason carry only its fixed note.
	$legacy = wb_listora_credit_legacy_reason( $note );
	if ( '' === $reason ) {
		$reason = $legacy;
	}
	$label  = wb_listora_credit_reason_label( $reason, (string) ( $entry['reference'] ?? '' ), (int) ( $entry['item_id'] ?? 0 ) );
	$is_sdk = '' === $note
		|| in_array( $reason, array( 'purchase', 'gateway_refund' ), true )
		|| '' !== $legacy
		|| in_array( $note, array( 'Credits deducted', 'Credits returned' ), true )
		|| 1 === preg_match( '/^Credits expired \(lot #\d+\)$/', $note );

	return '' !== $label && $is_sdk ? $label : wb_listora_credit_note_names( $note );
}

/**
 * A transparent 1x1 image for an <img> that script fills in later.
 *
 * An img without src fails HTML validation and some stacks add src="", which
 * browsers answer by requesting the current page again. This is valid and
 * costs no request.
 *
 * @since 2.1.0
 *
 * @return string Data URI.
 */
function wb_listora_blank_image_src(): string {
	return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
}

/**
 * Name the listing or need a note points at, instead of its number.
 *
 * Listora wrote notes such as "Plan: Standard for listing #6066" and
 * "Response to need #12 - listing #6066". A member reads a title, not an id, and
 * resolving it when shown covers old rows and every producer at once, without
 * rewriting the ledger.
 *
 * @since 2.1.0
 *
 * @param string $note A ledger note.
 * @return string
 */
function wb_listora_credit_note_names( string $note ): string {
	if ( false === strpos( $note, '#' ) ) {
		return $note;
	}
	$note = (string) preg_replace_callback(
		'/\b(listing|need) #(\d+)/',
		static function ( $match ) {
			$title = get_the_title( (int) $match[2] );
			return '' === $title
				/* translators: %s: listing or need. */
				? sprintf( __( 'a removed %s', 'wb-listora' ), $match[1] )
				: $match[1] . ' "' . $title . '"';
		},
		$note
	);

	return str_replace( ' — ', ': ', $note );
}


if ( ! function_exists( 'wb_listora_event_label' ) ) {
	/**
	 * A readable name for an event or action key ("listing_submitted" ->
	 * "New listing submitted").
	 *
	 * One map for the Email Log, Audit Log and Webhooks: Free's events and
	 * actions plus whatever Pro adds through the filter; any other key is
	 * turned into words rather than shown raw.
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

if ( ! function_exists( 'wb_listora_most_favorited' ) ) {
	/**
	 * Listings favourited most since a date: totals only, for analytics.
	 *
	 * @since 2.0.0
	 *
	 * @param string $since_gmt UTC datetime or date.
	 * @param int    $limit     Rows to return.
	 * @return array<int, array{listing_id: int, title: string, count: int}>
	 */
	function wb_listora_most_favorited( string $since_gmt, int $limit = 10 ): array {
		return \WBListora\Core\Listing_Data::most_favorited( $since_gmt, $limit );
	}
}

/**
 * Favourites added since a date (the Analytics headline figure).
 *
 * @since 2.1.0
 *
 * @param string $since_gmt UTC datetime, 'Y-m-d H:i:s' or 'Y-m-d'.
 * @return int
 */
function wb_listora_count_favorites_since( string $since_gmt ): int {
	return \WBListora\Core\Listing_Data::count_favorites_since( $since_gmt );
}

if ( ! function_exists( 'wb_listora_submit_contact_message' ) ) {
	/**
	 * Handle a contact-form message: the one send path, for any route that accepts one.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Request with id, name, email, phone, message, hp.
	 * @return \WP_REST_Response|\WP_Error
	 */
	function wb_listora_submit_contact_message( \WP_REST_Request $request ) {
		return \WBListora\Contact_Form::handle_submit( $request );
	}
}

if ( ! function_exists( 'wb_listora_expiration_enabled' ) ) {
	/**
	 * Whether listings expire after their plan's or type's duration.
	 *
	 * Off on a free directory, so listings stay up until removed. An add-on
	 * that sells listing time turns it on through the filter while it is
	 * active; turning that off returns to the owner's setting and leaves
	 * expiry dates already written alone. Listings with an end date (events,
	 * job deadlines) end on that date either way.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	function wb_listora_expiration_enabled(): bool {
		/**
		 * Filter whether listing expiration is on.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enabled The Settings > General value.
		 */
		return (bool) apply_filters( 'wb_listora_expiration_enabled', (bool) wb_listora_get_setting( 'enable_expiration', false ) );
	}
}

if ( ! function_exists( 'wb_listora_brand_name' ) ) {
	/**
	 * The product name shown to site owners in notices.
	 *
	 * Pro's White Label fills it in; any other rebrand can too.
	 *
	 * @since 1.9.1
	 *
	 * @return string
	 */
	function wb_listora_brand_name(): string {
		/**
		 * Filter: the product name shown in admin notices.
		 *
		 * @since 1.9.1
		 *
		 * @param string $name Default 'Listora'.
		 */
		$name = trim( (string) apply_filters( 'wb_listora_brand_name', 'Listora' ) );
		return '' !== $name ? $name : 'Listora';
	}
}

if ( ! function_exists( 'wb_listora_listing_moderation' ) ) {
	/**
	 * Moderation for a listing or a listing type: 'manual' or 'auto'.
	 *
	 * The type's own choice when it has one, else the site setting. The one
	 * answer for submission, email verification and admin re-verification.
	 *
	 * @since 1.9.1
	 *
	 * @param int|string $listing_or_type Listing ID (int), or a listing type slug (string).
	 * @return string
	 */
	function wb_listora_listing_moderation( $listing_or_type ): string {
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		// Only a real int is a listing ID. A string is always a type slug, so a
		// submitted listing_type of "7825" cannot borrow listing 7825's type.
		$type = is_int( $listing_or_type ) ? $registry->get_for_post( $listing_or_type ) : $registry->get( (string) $listing_or_type );
		if ( $type ) {
			return $type->get_moderation();
		}
		return 'auto_approve' === wb_listora_get_setting( 'moderation', 'manual' ) ? 'auto' : 'manual';
	}
}

if ( ! function_exists( 'wb_listora_listing_reviews_enabled' ) ) {
	/**
	 * Whether a listing takes reviews: the site switch and the type's choice.
	 *
	 * @since 1.9.1
	 *
	 * @param int $listing_id Listing ID; 0 checks the site switch only.
	 * @return bool
	 */
	function wb_listora_listing_reviews_enabled( $listing_id = 0 ): bool {
		$type = $listing_id ? \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( (int) $listing_id ) : null;
		return $type ? $type->is_review_enabled() : wb_listora_feature_enabled( 'reviews' );
	}
}

/**
 * Count one hit against a rate-limited action (Free's limiter, for add-ons).
 *
 * Actions and their caps live in Rate_Limiter::DEFAULTS; tune them with
 * `wb_listora_rate_limit_config`. An unknown action is not limited.
 *
 * @since 2.0.0
 *
 * @param string $action Action key, e.g. 'review_photo'.
 * @return true|\WP_Error 429 listora_rate_limit with data.retry_after in seconds.
 */
function wb_listora_rate_limit( string $action ) {
	return \WBListora\Rate_Limiter::check( $action );
}

/**
 * Move a list past its last page back to the last page.
 *
 * A stale or shared ?paged=99 should show the last real page, not an empty
 * state while rows exist. Updates `paged` and `offset` in an Admin_Table state.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $state Admin_Table::request() state (paged, per_page, offset).
 * @param int                  $total Rows matching the current filters.
 * @return bool True when the page moved and the caller should query again.
 */
function wb_listora_clamp_paged( array &$state, int $total ): bool {
	$last = max( 1, (int) ceil( $total / max( 1, (int) $state['per_page'] ) ) );
	if ( $total < 1 || (int) $state['paged'] <= $last ) {
		return false;
	}
	$state['paged']  = $last;
	$state['offset'] = ( $last - 1 ) * (int) $state['per_page'];
	return true;
}

/**
 * Rows a WP_Query matches, for a page that came back empty.
 *
 * WP_Query skips its found-rows count when a page has no posts, so a page
 * past the end reports 0 total. One cheap ID-only query gets the real total.
 *
 * @since 2.0.0
 *
 * @param array<string, mixed> $args WP_Query args.
 * @return int
 */
function wb_listora_query_total( array $args ): int {
	$args['paged']          = 1;
	$args['posts_per_page'] = 1;
	$args['fields']         = 'ids';
	$args['no_found_rows']  = false;
	return (int) ( new \WP_Query( $args ) )->found_posts;
}

/**
 * A counter that resets a fixed time after its first hit.
 *
 * Unlike re-setting a transient on every hit (which restarts the window),
 * the window start is kept, so a limit answers with the seconds left.
 *
 * @since 2.0.0
 *
 * @param string $key    Transient key.
 * @param int    $window Window length in seconds.
 * @param bool   $add    Count one hit.
 * @return array{count: int, wait: int} Hits so far, seconds until the window ends.
 */
function wb_listora_window_counter( string $key, int $window, bool $add = false ): array {
	$hit   = get_transient( $key );
	$count = is_array( $hit ) ? (int) ( $hit['n'] ?? 0 ) : (int) $hit; // An integer is a counter stored before 2.0.0.
	$start = is_array( $hit ) ? (int) ( $hit['t'] ?? time() ) : time();
	$wait  = max( 1, $start + $window - time() );
	if ( $add ) {
		++$count;
		set_transient(
			$key,
			array(
				'n' => $count,
				't' => $start,
			),
			$wait
		);
	}
	return array(
		'count' => $count,
		'wait'  => $wait,
	);
}

/**
 * A Listora (Free or Pro) asset URL with `ver` set to the file's modified time.
 *
 * Hooked only on -dev builds; a release keeps the plugin version.
 *
 * @since 2.0.0
 *
 * @param string|false $src Asset URL.
 * @return string|false
 */
function wb_listora_dev_asset_version( $src ) {
	if ( ! is_string( $src ) || false === strpos( $src, '/plugins/wb-listora' ) ) {
		return $src;
	}
	$file = WP_PLUGIN_DIR . '/' . ltrim( (string) wp_parse_url( substr( $src, strpos( $src, '/plugins/' ) + 9 ), PHP_URL_PATH ), '/' );
	return is_readable( $file ) ? add_query_arg( 'ver', (string) filemtime( $file ), $src ) : $src;
}

/**
 * The one gate for every action on an existing review: it exists, and reviews
 * are on for its listing (site switch and type).
 *
 * Votes, reports and replies also need the review to be published, since
 * nobody but its author and moderators can see a pending one. Edits and photo
 * uploads by the author do not.
 *
 * @since 2.0.0
 *
 * @param int  $review_id     Review ID.
 * @param bool $approved_only Also require an approved review.
 * @return true|\WP_Error 404 listora_not_found, 403 listora_reviews_disabled or listora_review_not_approved.
 */
function wb_listora_review_gate( int $review_id, bool $approved_only = false ) {
	global $wpdb;
	$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one row by primary key; table name from the trusted prefix.
	$review = $wpdb->get_row( $wpdb->prepare( "SELECT listing_id, status FROM {$table} WHERE id = %d", $review_id ) );
	if ( ! $review ) {
		return new \WP_Error( 'listora_not_found', __( 'Review not found.', 'wb-listora' ), array( 'status' => 404 ) );
	}
	if ( ! wb_listora_listing_reviews_enabled( (int) $review->listing_id ) ) {
		return new \WP_Error( 'listora_reviews_disabled', __( 'Reviews are currently disabled on this site.', 'wb-listora' ), array( 'status' => 403 ) );
	}
	if ( $approved_only && 'approved' !== $review->status ) {
		return new \WP_Error( 'listora_review_not_approved', __( 'This review is not published yet.', 'wb-listora' ), array( 'status' => 403 ) );
	}
	return true;
}

if ( ! function_exists( 'wb_listora_user_can_edit_listing' ) ) {
	/**
	 * Whether a user may edit a listing from the frontend form or the submit API.
	 *
	 * The listing's author, or anyone who can edit others' posts. One rule for
	 * the edit form, POST /submit with listing_id and PUT /submit/{id}: the form
	 * let an admin open a member's listing while the save routes were author-only,
	 * so the admin's save fell through to "create" and hit the duplicate check
	 * against the listing itself.
	 *
	 * @since 1.9.1
	 *
	 * @param \WP_Post|int $post    Listing post or ID.
	 * @param int|null     $user_id User to check, or null for the current user.
	 * @return bool
	 */
	function wb_listora_user_can_edit_listing( $post, $user_id = null ) {
		$post    = get_post( $post );
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! $post || 'listora_listing' !== $post->post_type || ! $user_id ) {
			return false;
		}

		return (int) $post->post_author === $user_id || wb_listora_user_can_moderate_listings( $user_id );
	}
}

/**
 * Whether a user may act on any member's listing (staff: administrators,
 * editors, Listora Moderators).
 *
 * The plugin's own capability, not WordPress's `edit_others_posts`: that one
 * is held by roles that have nothing to do with the directory (a WooCommerce
 * Shop Manager) and missed Listora Moderators, who hold the listing capability
 * but not the core one.
 *
 * @since 2.1.0
 *
 * @param int|null $user_id User, or null for the current user.
 * @return bool
 */
function wb_listora_user_can_moderate_listings( $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;

	return $user_id > 0 && user_can( $user_id, 'edit_others_listora_listings' );
}

/**
 * Contact-form leads received since a moment, from the stored rows.
 *
 * The same rows the Leads screen lists, so a dashboard count and that screen agree.
 *
 * @since 2.1.0
 *
 * @param string $since_gmt Lower bound, 'Y-m-d H:i:s' in UTC.
 * @return int
 */
function wb_listora_count_leads_since( $since_gmt ) {
	$result = \WBListora\Core\Leads::query(
		array(
			'after' => (string) $since_gmt,
			'limit' => 1,
		)
	);

	return (int) $result['total'];
}

/**
 * Print a number field whose stored 0 means "no limit", with a labelled box for it.
 *
 * @since 2.1.0
 *
 * @param string $id        Input id.
 * @param string $name      Input name.
 * @param int    $value     Stored value (0 = the "off" meaning).
 * @param string $off_label Checkbox label, e.g. "Unlimited".
 * @param string $unit      Unit text after the number.
 * @param int    $fallback  Value restored when the box is unticked with nothing typed before.
 * @return void
 */
function wb_listora_render_optional_number( $id, $name, $value, $off_label, $unit = '', $fallback = 1 ) {
	$value = max( 0, (int) $value );
	?>
	<span class="listora-optional-number" data-listora-optional-number data-fallback="<?php echo esc_attr( (string) max( 1, (int) $fallback ) ); ?>">
		<label class="listora-optional-number__off">
			<input type="checkbox" data-listora-optional-off <?php checked( 0, $value ); ?> />
			<?php echo esc_html( $off_label ); ?>
		</label>
		<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" min="0" step="1" class="small-text" data-listora-optional-input />
		<?php if ( '' !== $unit ) : ?>
			<span><?php echo esc_html( $unit ); ?></span>
		<?php endif; ?>
	</span>
	<?php
}

if ( ! function_exists( 'wb_listora_listing_is_interactable' ) ) {
	/**
	 * Whether a listing may be favorited, claimed, or reviewed by the given
	 * (or current) user.
	 *
	 * A published listing is open to everyone; a non-public one only to its
	 * author or someone who can moderate.
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

		return wb_listora_user_can_moderate_listings();
	}
}

if ( ! function_exists( 'wb_listora_expand_category_ids_with_descendants' ) ) {
	/**
	 * A set of `listora_listing_cat` term IDs, plus every descendant of each.
	 *
	 * Allowing a parent category allows its children. The one place this
	 * expansion happens: every consumer of a type's allowed categories (REST,
	 * the submission form, Pro's plan matching) calls this, not the raw list.
	 *
	 * @since 1.10.0
	 *
	 * @param int[] $term_ids Category term IDs (typically a type's stored
	 *                        allowed-categories list).
	 * @return int[] $term_ids plus every descendant, deduplicated. Invalid or
	 *               already-covered IDs are silently dropped.
	 */
	function wb_listora_expand_category_ids_with_descendants( array $term_ids ) {
		$term_ids = array_values( array_unique( array_map( 'absint', $term_ids ) ) );
		$expanded = $term_ids;

		foreach ( $term_ids as $parent_id ) {
			$children = get_term_children( $parent_id, 'listora_listing_cat' );
			if ( ! is_wp_error( $children ) ) {
				$expanded = array_merge( $expanded, array_map( 'absint', $children ) );
			}
		}

		return array_values( array_unique( $expanded ) );
	}
}

/**
 * The colour a category shows: its own, else its listing type's.
 *
 * The one place this fallback happens: the category tile, the REST
 * categories endpoint and the calendar all call this, not the raw meta.
 *
 * @since 2.1.0
 *
 * @param int                               $term_id `listora_listing_cat` term ID.
 * @param \WBListora\Core\Listing_Type|null $type    The type the category is shown under.
 * @return string Hex colour, or '' when neither the category nor the type has one.
 */
function wb_listora_category_color( $term_id, $type = null ) {
	$color = (string) get_term_meta( (int) $term_id, '_listora_color', true );
	if ( '' === $color && $type ) {
		$color = (string) $type->get_color();
	}
	return $color;
}

if ( ! function_exists( 'wb_listora_sort_terms_hierarchically' ) ) {
	/**
	 * A flat set of `listora_listing_cat` terms, reordered parent-then-children
	 * with each term's depth, for rendering as indentation.
	 *
	 * Siblings keep their input order (pass them alphabetical). A term whose
	 * parent is not in $terms becomes its own root; every input term appears
	 * exactly once.
	 *
	 * @since 1.10.0
	 *
	 * @param \WP_Term[] $terms Flat term list, one taxonomy.
	 * @return array<int, array{term: \WP_Term, depth: int}>
	 */
	function wb_listora_sort_terms_hierarchically( array $terms ) {
		$by_id = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$by_id[ (int) $term->term_id ] = $term;
			}
		}

		$children_of = array();
		foreach ( $by_id as $id => $term ) {
			$parent_id = (int) $term->parent;
			// Treat an out-of-set parent as "no parent" so the term still
			// surfaces, at depth 0, instead of vanishing from the list.
			if ( 0 === $parent_id || ! isset( $by_id[ $parent_id ] ) ) {
				$parent_id = 0;
			}
			$children_of[ $parent_id ][] = $id;
		}

		$ordered = array();
		$walk    = static function ( $parent_id, $depth ) use ( &$walk, &$ordered, $children_of, $by_id ) {
			foreach ( (array) ( $children_of[ $parent_id ] ?? array() ) as $id ) {
				$ordered[] = array(
					'term'  => $by_id[ $id ],
					'depth' => $depth,
				);
				$walk( $id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		return $ordered;
	}
}

/**
 * A text cell made safe for a CSV export: a leading = + - @ tab or CR is
 * prefixed with a quote so a spreadsheet does not run it as a formula.
 * Use for text that members or visitors wrote, never for numbers.
 *
 * @since 2.0.0
 *
 * @param mixed $value Cell value.
 * @return string
 */
function wb_listora_csv_cell( $value ) {
	return \WBListora\ImportExport\CSV_Exporter::sanitize_csv_field( $value );
}
