<?php
/**
 * Plugin activator.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		// Check environment.
		if ( ! self::check_environment() ) {
			return;
		}

		// Create or update database tables.
		self::create_tables();

		// Register default listing types and categories.
		self::create_defaults();

		// Add capabilities to roles.
		$caps = new Core\Capabilities();
		$caps->add_caps();

		// Set default options.
		self::set_default_options();

		// Create default frontend pages (submission, dashboard) if missing.
		self::maybe_create_pages();

		// Flush rewrite rules.
		$post_types = new Core\Post_Types();
		$post_types->register();

		$taxonomies = new Core\Taxonomies();
		$taxonomies->register();

		flush_rewrite_rules();

		// Set activation redirect for setup wizard.
		set_transient( 'wb_listora_activation_redirect', true, 60 );
	}

	/**
	 * Check the environment for required extensions.
	 *
	 * @return bool
	 */
	private static function check_environment() {
		$required_extensions = array( 'json', 'mbstring' );
		$missing             = array();

		foreach ( $required_extensions as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = $ext;
			}
		}

		if ( ! empty( $missing ) ) {
			wp_die(
				sprintf(
					/* translators: %s: comma-separated list of extensions */
					esc_html__( 'WB Listora requires the following PHP extensions: %s', 'wb-listora' ),
					esc_html( implode( ', ', $missing ) )
				),
				esc_html__( 'Plugin Activation Error', 'wb-listora' ),
				array( 'back_link' => true )
			);
			return false;
		}

		return true;
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// 1. Geolocation index.
		dbDelta(
			"CREATE TABLE {$prefix}geo (
			listing_id   bigint(20) unsigned NOT NULL,
			lat          decimal(10,7) NOT NULL DEFAULT 0,
			lng          decimal(10,7) NOT NULL DEFAULT 0,
			address      varchar(500) NOT NULL DEFAULT '',
			city         varchar(200) NOT NULL DEFAULT '',
			state        varchar(200) NOT NULL DEFAULT '',
			country      varchar(100) NOT NULL DEFAULT '',
			postal_code  varchar(20) NOT NULL DEFAULT '',
			geohash      varchar(12) NOT NULL DEFAULT '',
			timezone     varchar(50) NOT NULL DEFAULT '',
			PRIMARY KEY  (listing_id),
			KEY idx_lat_lng (lat, lng),
			KEY idx_city (city),
			KEY idx_country_state (country, state),
			KEY idx_geohash (geohash),
			KEY idx_postal (postal_code)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 2. Denormalized search index.
		dbDelta(
			"CREATE TABLE {$prefix}search_index (
			listing_id    bigint(20) unsigned NOT NULL,
			listing_type  varchar(50) NOT NULL DEFAULT '',
			status        varchar(20) NOT NULL DEFAULT 'publish',
			title         varchar(500) NOT NULL DEFAULT '',
			content_text  text NOT NULL,
			meta_text     text NOT NULL,
			avg_rating    decimal(3,2) NOT NULL DEFAULT 0.00,
			review_count  int(11) NOT NULL DEFAULT 0,
			is_featured   tinyint(1) NOT NULL DEFAULT 0,
			is_verified   tinyint(1) NOT NULL DEFAULT 0,
			is_claimed    tinyint(1) NOT NULL DEFAULT 0,
			author_id     bigint(20) unsigned NOT NULL DEFAULT 0,
			lat           decimal(10,7) NOT NULL DEFAULT 0,
			lng           decimal(10,7) NOT NULL DEFAULT 0,
			city          varchar(200) NOT NULL DEFAULT '',
			country       varchar(100) NOT NULL DEFAULT '',
			price_value   decimal(15,2) NOT NULL DEFAULT 0,
			created_at    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (listing_id),
			KEY idx_type_status (listing_type, status),
			KEY idx_featured_rating (is_featured, avg_rating),
			KEY idx_rating (avg_rating),
			KEY idx_created (created_at),
			KEY idx_price (price_value),
			KEY idx_author (author_id),
			KEY idx_lat_lng (lat, lng),
			FULLTEXT idx_search (title, content_text, meta_text)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 3. Custom field filter index.
		dbDelta(
			"CREATE TABLE {$prefix}field_index (
			listing_id    bigint(20) unsigned NOT NULL,
			field_key     varchar(100) NOT NULL DEFAULT '',
			field_value   varchar(500) NOT NULL DEFAULT '',
			numeric_value decimal(15,2) DEFAULT NULL,
			listing_type  varchar(50) NOT NULL DEFAULT '',
			PRIMARY KEY  (listing_id, field_key, field_value),
			KEY idx_field_value (field_key, field_value),
			KEY idx_field_numeric (field_key, numeric_value),
			KEY idx_type_field (listing_type, field_key, field_value)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 4. Reviews.
		dbDelta(
			"CREATE TABLE {$prefix}reviews (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id      bigint(20) unsigned NOT NULL,
			user_id         bigint(20) unsigned NOT NULL,
			overall_rating  tinyint(1) unsigned NOT NULL DEFAULT 0,
			criteria_ratings text DEFAULT NULL,
			title           varchar(500) NOT NULL DEFAULT '',
			content         text NOT NULL,
			status          varchar(20) NOT NULL DEFAULT 'pending',
			photos          text DEFAULT NULL,
			helpful_count   int(11) NOT NULL DEFAULT 0,
			owner_reply     text DEFAULT NULL,
			owner_reply_at  datetime DEFAULT NULL,
			ip_address      varchar(45) NOT NULL DEFAULT '',
			created_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_listing_status (listing_id, status),
			KEY idx_user (user_id),
			KEY idx_rating (overall_rating),
			KEY idx_created (created_at),
			UNIQUE KEY idx_user_listing (user_id, listing_id)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 5. Review votes.
		dbDelta(
			"CREATE TABLE {$prefix}review_votes (
			user_id      bigint(20) unsigned NOT NULL,
			review_id    bigint(20) unsigned NOT NULL,
			created_at   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (user_id, review_id),
			KEY idx_review (review_id)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 6. Favorites.
		dbDelta(
			"CREATE TABLE {$prefix}favorites (
			user_id      bigint(20) unsigned NOT NULL,
			listing_id   bigint(20) unsigned NOT NULL,
			collection   varchar(100) NOT NULL DEFAULT 'default',
			created_at   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (user_id, listing_id),
			KEY idx_listing (listing_id),
			KEY idx_user_collection (user_id, collection)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 7. Claims.
		dbDelta(
			"CREATE TABLE {$prefix}claims (
			id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id   bigint(20) unsigned NOT NULL,
			user_id      bigint(20) unsigned NOT NULL,
			status       varchar(20) NOT NULL DEFAULT 'pending',
			proof_text   text NOT NULL,
			proof_files  text DEFAULT NULL,
			admin_notes  text DEFAULT NULL,
			reviewed_by  bigint(20) unsigned DEFAULT NULL,
			created_at   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_listing (listing_id),
			KEY idx_user (user_id),
			KEY idx_status (status)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 8. Business hours (denormalized for "open now" queries).
		dbDelta(
			"CREATE TABLE {$prefix}hours (
			listing_id   bigint(20) unsigned NOT NULL,
			day_of_week  tinyint(1) unsigned NOT NULL,
			open_time    time DEFAULT NULL,
			close_time   time DEFAULT NULL,
			is_closed    tinyint(1) NOT NULL DEFAULT 0,
			is_24h       tinyint(1) NOT NULL DEFAULT 0,
			timezone     varchar(50) NOT NULL DEFAULT 'UTC',
			PRIMARY KEY  (listing_id, day_of_week),
			KEY idx_open (day_of_week, open_time, close_time, is_closed)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 9. Analytics (Pro — table created now, populated by Pro plugin).
		dbDelta(
			"CREATE TABLE {$prefix}analytics (
			id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id   bigint(20) unsigned NOT NULL,
			event_type   varchar(30) NOT NULL,
			event_date   date NOT NULL,
			count        int(11) NOT NULL DEFAULT 1,
			meta         text DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_listing_event_date (listing_id, event_type, event_date),
			KEY idx_date (event_date),
			KEY idx_listing (listing_id)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 10. Payments (Pro — table created now, populated by Pro plugin).
		dbDelta(
			"CREATE TABLE {$prefix}payments (
			id                    bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id               bigint(20) unsigned NOT NULL,
			listing_id            bigint(20) unsigned DEFAULT NULL,
			plan_id               bigint(20) unsigned DEFAULT NULL,
			gateway               varchar(30) NOT NULL DEFAULT '',
			gateway_payment_id    varchar(255) NOT NULL DEFAULT '',
			gateway_subscription_id varchar(255) DEFAULT NULL,
			amount                decimal(10,2) NOT NULL DEFAULT 0,
			currency              varchar(3) NOT NULL DEFAULT 'USD',
			tax_amount            decimal(10,2) NOT NULL DEFAULT 0,
			coupon_code           varchar(50) DEFAULT NULL,
			discount_amount       decimal(10,2) NOT NULL DEFAULT 0,
			status                varchar(20) NOT NULL DEFAULT 'pending',
			payment_type          varchar(30) NOT NULL DEFAULT 'one_time',
			invoice_number        varchar(50) DEFAULT NULL,
			billing_name          varchar(200) DEFAULT NULL,
			billing_email         varchar(200) DEFAULT NULL,
			refund_amount         decimal(10,2) NOT NULL DEFAULT 0,
			refund_reason         text DEFAULT NULL,
			refunded_at           datetime DEFAULT NULL,
			created_at            datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at            datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_user (user_id),
			KEY idx_listing (listing_id),
			KEY idx_status (status),
			KEY idx_gateway_payment (gateway, gateway_payment_id),
			KEY idx_invoice (invoice_number),
			KEY idx_created (created_at)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// 11. Listing services.
		dbDelta(
			"CREATE TABLE {$prefix}services (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id      bigint(20) unsigned NOT NULL,
			title           varchar(500) NOT NULL DEFAULT '',
			description     text NOT NULL,
			price           decimal(15,2) DEFAULT NULL,
			price_type      varchar(20) NOT NULL DEFAULT 'fixed',
			duration_minutes int(11) DEFAULT NULL,
			image_id        bigint(20) unsigned DEFAULT NULL,
			video_url       varchar(500) NOT NULL DEFAULT '',
			gallery         text DEFAULT NULL,
			sort_order      int(11) NOT NULL DEFAULT 0,
			status          varchar(20) NOT NULL DEFAULT 'active',
			created_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_listing (listing_id),
			KEY idx_status (listing_id, status),
			KEY idx_sort (listing_id, sort_order)
		) ENGINE=InnoDB {$charset_collate};"
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Store the DB version.
		update_option( 'wb_listora_db_version', WB_LISTORA_DB_VERSION );
	}

	/**
	 * Create default listing types, categories, and features.
	 */
	private static function create_defaults() {
		// Only run on first activation.
		if ( get_option( 'wb_listora_defaults_created' ) ) {
			return;
		}

		// Default listing types will be created by Listing_Type_Registry on init.
		// Set flag so we know to run the defaults creation on next init.
		update_option( 'wb_listora_needs_defaults', true );
		update_option( 'wb_listora_defaults_created', true );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		if ( false === get_option( 'wb_listora_settings' ) ) {
			update_option( 'wb_listora_settings', wb_listora_get_default_settings() );
		}
	}

	/**
	 * Create default frontend pages if they don't exist and link them in
	 * settings so the user-dashboard / listing-submission blocks have a
	 * canonical home.
	 *
	 * Without this, the "Submit a listing" / "My account" CTAs on cards and
	 * in the admin point at post ID 0 unless the site owner walks through
	 * the setup wizard. Every dismissed-wizard install would silently ship
	 * broken links.
	 *
	 * Runs on activation and is idempotent — if a page is already configured
	 * in wb_listora_settings OR a page with the stored block already exists,
	 * nothing is created.
	 */
	private static function maybe_create_pages(): void {
		$settings = get_option( 'wb_listora_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$pages = array(
			'submission_page' => array(
				'slug'    => 'add-listing',
				'title'   => __( 'Add Listing', 'wb-listora' ),
				'block'   => 'listora/listing-submission',
				'content' => '<!-- wp:listora/listing-submission /-->',
			),
			'dashboard_page'  => array(
				'slug'    => 'my-listings',
				'title'   => __( 'My Listings', 'wb-listora' ),
				'block'   => 'listora/user-dashboard',
				'content' => '<!-- wp:listora/user-dashboard /-->',
			),
		);

		$changed = false;

		foreach ( $pages as $option_key => $page ) {
			// Already configured and still present? Nothing to do.
			$configured_id = isset( $settings[ $option_key ] ) ? (int) $settings[ $option_key ] : 0;
			if ( $configured_id > 0 && 'page' === get_post_type( $configured_id ) ) {
				continue;
			}

			// See if a user-created page with the right block already exists.
			$existing = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					's'              => $page['block'],
				)
			);

			if ( ! empty( $existing ) ) {
				$settings[ $option_key ] = (int) $existing[0];
				$changed                 = true;
				continue;
			}

			// Create a new page.
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_name'    => $page['slug'],
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
					'post_status'  => 'publish',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ $option_key ] = (int) $page_id;
				$changed                 = true;
			}
		}

		if ( $changed ) {
			update_option( 'wb_listora_settings', $settings );
		}
	}
}
