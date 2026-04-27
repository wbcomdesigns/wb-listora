<?php
/**
 * Health Check — admin trust signal page.
 *
 * Renders a card grid of pass/fail/warning checks so site owners can
 * confirm WB Listora is "100% working" at a glance. Each check returns
 * one of three states (`pass`, `fail`, `warn`) plus an explanation and
 * optional fix link.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Self-diagnostic page for WB Listora.
 */
class Health_Check {

	const STATE_PASS = 'pass';
	const STATE_FAIL = 'fail';
	const STATE_WARN = 'warn';

	/**
	 * 10 listora_* DB tables created on activation.
	 *
	 * @var array<int, string>
	 */
	private const REQUIRED_TABLES = array(
		'geo',
		'search_index',
		'field_index',
		'reviews',
		'review_votes',
		'favorites',
		'claims',
		'hours',
		'analytics',
		'payments',
		'services',
	);

	/**
	 * Run all checks and render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wb-listora' ) );
		}

		$checks = $this->run_checks();

		$summary = array(
			self::STATE_PASS => 0,
			self::STATE_FAIL => 0,
			self::STATE_WARN => 0,
		);
		foreach ( $checks as $check ) {
			++$summary[ $check['state'] ];
		}

		?>
		<div class="wrap wb-listora-admin listora-health-page">
			<div class="listora-page-header">
				<div class="listora-page-header__left">
					<h1 class="listora-page-header__title">
						<i data-lucide="activity" class="listora-icon--sm"></i>
						<?php esc_html_e( 'Health Check', 'wb-listora' ); ?>
					</h1>
					<p class="listora-page-header__desc">
						<?php esc_html_e( 'Verify that WB Listora is wired up correctly. Each card runs a live check on activation, cron, pages, and the server environment.', 'wb-listora' ); ?>
					</p>
				</div>
			</div>

			<?php $this->render_summary( $summary ); ?>

			<div class="listora-health-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;margin-top:1rem;">
				<?php foreach ( $checks as $check ) : ?>
					<?php $this->render_card( $check ); ?>
				<?php endforeach; ?>
			</div>
		</div>

		<style>
		.listora-health-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem 1.25rem;display:flex;gap:0.75rem;align-items:flex-start;}
		.listora-health-card__icon{flex:0 0 28px;height:28px;width:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;line-height:1;color:#fff;}
		.listora-health-card__icon--pass{background:#16a34a;}
		.listora-health-card__icon--fail{background:#dc2626;}
		.listora-health-card__icon--warn{background:#d97706;}
		.listora-health-card__body{flex:1;min-width:0;}
		.listora-health-card__title{font-weight:600;margin:0 0 0.25rem;font-size:14px;}
		.listora-health-card__desc{margin:0;color:#475569;font-size:13px;line-height:1.5;}
		.listora-health-card__fix{display:inline-block;margin-top:0.5rem;font-size:12px;}
		.listora-health-summary{display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.75rem;}
		.listora-health-summary span{padding:0.35rem 0.75rem;border-radius:999px;font-size:12px;font-weight:600;}
		.listora-health-summary .is-pass{background:#dcfce7;color:#15803d;}
		.listora-health-summary .is-fail{background:#fee2e2;color:#b91c1c;}
		.listora-health-summary .is-warn{background:#fef3c7;color:#92400e;}
		</style>
		<?php
	}

	/**
	 * Render the pass/warn/fail summary pills above the grid.
	 *
	 * @param array<string,int> $summary State => count map.
	 * @return void
	 */
	private function render_summary( array $summary ): void {
		?>
		<div class="listora-health-summary">
			<span class="is-pass">
				<?php
				printf(
					/* translators: %d: count of passing checks */
					esc_html( _n( '%d passing', '%d passing', $summary[ self::STATE_PASS ], 'wb-listora' ) ),
					(int) $summary[ self::STATE_PASS ]
				);
				?>
			</span>
			<?php if ( $summary[ self::STATE_WARN ] > 0 ) : ?>
				<span class="is-warn">
					<?php
					printf(
						/* translators: %d: count of warning checks */
						esc_html( _n( '%d warning', '%d warnings', $summary[ self::STATE_WARN ], 'wb-listora' ) ),
						(int) $summary[ self::STATE_WARN ]
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( $summary[ self::STATE_FAIL ] > 0 ) : ?>
				<span class="is-fail">
					<?php
					printf(
						/* translators: %d: count of failed checks */
						esc_html( _n( '%d failing', '%d failing', $summary[ self::STATE_FAIL ], 'wb-listora' ) ),
						(int) $summary[ self::STATE_FAIL ]
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single check card.
	 *
	 * @param array{label:string,state:string,description:string,fix_url?:string,fix_label?:string} $check Single check result.
	 * @return void
	 */
	private function render_card( array $check ): void {
		$state  = $check['state'];
		$icon   = self::STATE_PASS === $state ? '✓' : ( self::STATE_FAIL === $state ? '✗' : '!' );
		$class  = 'listora-health-card__icon listora-health-card__icon--' . $state;
		$fix    = isset( $check['fix_url'] ) ? (string) $check['fix_url'] : '';
		$flabel = isset( $check['fix_label'] ) ? (string) $check['fix_label'] : __( 'Fix this →', 'wb-listora' );
		?>
		<div class="listora-health-card">
			<div class="<?php echo esc_attr( $class ); ?>" aria-hidden="true"><?php echo esc_html( $icon ); ?></div>
			<div class="listora-health-card__body">
				<p class="listora-health-card__title"><?php echo esc_html( $check['label'] ); ?></p>
				<p class="listora-health-card__desc"><?php echo esc_html( $check['description'] ); ?></p>
				<?php if ( '' !== $fix ) : ?>
					<a class="listora-health-card__fix" href="<?php echo esc_url( $fix ); ?>"><?php echo esc_html( $flabel ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build the list of checks. Keep each one cheap (no remote calls).
	 *
	 * @return array<int, array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}>
	 */
	private function run_checks() {
		$checks = array();

		// 1. DB tables.
		$checks[] = $this->check_database_tables();

		// 2-3. Cron events.
		$checks[] = $this->check_cron_event(
			'wb_listora_check_expirations',
			__( 'Listing expiration cron', 'wb-listora' ),
			__( 'Runs twice daily to flip expired listings to the `listora_expired` status.', 'wb-listora' )
		);
		$checks[] = $this->check_cron_event(
			'wb_listora_cleanup_unverified_listings',
			__( 'Unverified listings cron', 'wb-listora' ),
			__( 'Runs daily to clean up listings whose author never confirmed their email.', 'wb-listora' )
		);

		// 4. Essential pages exist with the right blocks.
		$checks[] = $this->check_essential_pages();

		// 5. Permalinks not Plain.
		$checks[] = $this->check_permalinks();

		// 6. PHP version.
		$checks[] = $this->check_php_version();

		// 7. Memory limit.
		$checks[] = $this->check_memory_limit();

		return $checks;
	}

	/**
	 * Verify all 10 custom DB tables exist.
	 *
	 * @return array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}
	 */
	private function check_database_tables() {
		global $wpdb;

		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$missing = array();

		foreach ( self::REQUIRED_TABLES as $name ) {
			$table = $prefix . $name;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			// phpcs:enable
			if ( $table !== $found ) {
				$missing[] = $name;
			}
		}

		if ( empty( $missing ) ) {
			return array(
				'label'       => __( 'Database tables', 'wb-listora' ),
				'state'       => self::STATE_PASS,
				'description' => sprintf(
					/* translators: %d: number of tables */
					_n( '%d Listora table is present.', 'All %d Listora tables are present.', count( self::REQUIRED_TABLES ), 'wb-listora' ),
					count( self::REQUIRED_TABLES )
				),
			);
		}

		return array(
			'label'       => __( 'Database tables', 'wb-listora' ),
			'state'       => self::STATE_FAIL,
			'description' => sprintf(
				/* translators: %s: comma-separated table list */
				__( 'Missing %s. Re-activate WB Listora to recreate them.', 'wb-listora' ),
				implode( ', ', $missing )
			),
			'fix_url'     => admin_url( 'plugins.php' ),
			'fix_label'   => __( 'Open Plugins page →', 'wb-listora' ),
		);
	}

	/**
	 * Verify a cron hook is registered.
	 *
	 * @param string $hook        Cron hook name.
	 * @param string $label       Card label.
	 * @param string $description Card description on success.
	 * @return array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}
	 */
	private function check_cron_event( $hook, $label, $description ) {
		if ( wp_next_scheduled( $hook ) ) {
			return array(
				'label'       => $label,
				'state'       => self::STATE_PASS,
				'description' => $description,
			);
		}

		return array(
			'label'       => $label,
			'state'       => self::STATE_FAIL,
			'description' => sprintf(
				/* translators: %s: cron hook name */
				__( 'Cron hook %s is not scheduled. Deactivate and reactivate WB Listora to re-register it.', 'wb-listora' ),
				$hook
			),
			'fix_url'     => admin_url( 'plugins.php' ),
			'fix_label'   => __( 'Open Plugins page →', 'wb-listora' ),
		);
	}

	/**
	 * Verify the 3 essential pages exist with the correct block.
	 *
	 * @return array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}
	 */
	private function check_essential_pages() {
		$expected = array(
			'wb_listora_directory_page_id'  => array(
				'name'  => __( 'Directory', 'wb-listora' ),
				'block' => 'listora/listing-grid',
			),
			'wb_listora_submission_page_id' => array(
				'name'  => __( 'Add Listing', 'wb-listora' ),
				'block' => 'listora/listing-submission',
			),
			'wb_listora_dashboard_page_id'  => array(
				'name'  => __( 'My Dashboard', 'wb-listora' ),
				'block' => 'listora/user-dashboard',
			),
		);

		$missing = array();
		foreach ( $expected as $option_key => $info ) {
			$page_id = (int) get_option( $option_key, 0 );
			if ( $page_id <= 0 || 'page' !== get_post_type( $page_id ) ) {
				$missing[] = $info['name'];
				continue;
			}
			$post = get_post( $page_id );
			if ( ! $post || ! has_block( $info['block'], $post ) ) {
				$missing[] = $info['name'];
			}
		}

		if ( empty( $missing ) ) {
			return array(
				'label'       => __( 'Essential pages', 'wb-listora' ),
				'state'       => self::STATE_PASS,
				'description' => __( 'Directory, Add Listing and My Dashboard pages exist with the correct blocks.', 'wb-listora' ),
			);
		}

		return array(
			'label'       => __( 'Essential pages', 'wb-listora' ),
			'state'       => self::STATE_FAIL,
			'description' => sprintf(
				/* translators: %s: comma-separated page list */
				__( 'Missing or misconfigured: %s. Reactivate WB Listora to auto-create them.', 'wb-listora' ),
				implode( ', ', $missing )
			),
			'fix_url'     => admin_url( 'plugins.php' ),
			'fix_label'   => __( 'Open Plugins page →', 'wb-listora' ),
		);
	}

	/**
	 * Pretty permalinks check — Plain permalinks break listing single URLs.
	 *
	 * @return array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}
	 */
	private function check_permalinks() {
		$structure = (string) get_option( 'permalink_structure', '' );

		if ( '' === $structure ) {
			return array(
				'label'       => __( 'Permalinks', 'wb-listora' ),
				'state'       => self::STATE_FAIL,
				'description' => __( 'Permalinks are set to Plain. Listing URLs and pretty taxonomy links will not work.', 'wb-listora' ),
				'fix_url'     => admin_url( 'options-permalink.php' ),
				'fix_label'   => __( 'Open Permalink settings →', 'wb-listora' ),
			);
		}

		return array(
			'label'       => __( 'Permalinks', 'wb-listora' ),
			'state'       => self::STATE_PASS,
			'description' => sprintf(
				/* translators: %s: permalink structure */
				__( 'Pretty permalinks are enabled (%s).', 'wb-listora' ),
				$structure
			),
		);
	}

	/**
	 * PHP ≥ 7.4 check.
	 *
	 * @return array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}
	 */
	private function check_php_version() {
		$version = PHP_VERSION;

		if ( version_compare( $version, '7.4', '>=' ) ) {
			return array(
				'label'       => __( 'PHP version', 'wb-listora' ),
				'state'       => self::STATE_PASS,
				'description' => sprintf(
					/* translators: %s: PHP version */
					__( 'Running PHP %s — meets the 7.4 minimum.', 'wb-listora' ),
					$version
				),
			);
		}

		return array(
			'label'       => __( 'PHP version', 'wb-listora' ),
			'state'       => self::STATE_FAIL,
			'description' => sprintf(
				/* translators: %s: PHP version */
				__( 'PHP %s detected — WB Listora requires 7.4 or newer. Ask your host to upgrade.', 'wb-listora' ),
				$version
			),
		);
	}

	/**
	 * Memory limit ≥ 128M.
	 *
	 * @return array{label:string,state:string,description:string,fix_url?:string,fix_label?:string}
	 */
	private function check_memory_limit() {
		$raw   = ini_get( 'memory_limit' );
		$bytes = wp_convert_hr_to_bytes( $raw );
		$min   = 128 * MB_IN_BYTES;

		if ( $bytes >= $min ) {
			return array(
				'label'       => __( 'Memory limit', 'wb-listora' ),
				'state'       => self::STATE_PASS,
				'description' => sprintf(
					/* translators: %s: memory limit */
					__( 'PHP memory_limit = %s — comfortably above the 128M minimum.', 'wb-listora' ),
					$raw
				),
			);
		}

		return array(
			'label'       => __( 'Memory limit', 'wb-listora' ),
			'state'       => self::STATE_WARN,
			'description' => sprintf(
				/* translators: %s: memory limit */
				__( 'PHP memory_limit = %s. Increase to at least 128M in wp-config.php or php.ini for reliable image uploads and CSV imports.', 'wb-listora' ),
				$raw
			),
		);
	}
}
