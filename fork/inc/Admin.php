<?php
/**
 * Admin class.
 *
 * @author Themeisle
 * @package fork
 * @since 1.0.0
 */

namespace Fork;

/**
 * Admin class.
 */
class Admin {

	const OTTER_REF = 'otter_reference_key';

	/**
	 * Admin constructor.
	 */
	public function __construct() {
		$this->setup_admin_hooks();
	}


	/**
	 * Setup admin hooks.
	 *
	 * @return void
	 */
	public function setup_admin_hooks() {
		add_action( 'admin_notices', array( $this, 'render_welcome_notice' ), 0 );
		add_action( 'wp_ajax_fork_dismiss_welcome_notice', array( $this, 'remove_welcome_notice' ) );
		add_action( 'wp_ajax_fork_set_otter_ref', array( $this, 'set_otter_ref' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_internal_page' ) );

		add_action( 'enqueue_block_editor_assets', array( $this, 'add_fse_design_pack_notice' ) );
		add_action( 'wp_ajax_fork_dismiss_design_pack_notice', array( $this, 'remove_design_pack_notice' ) );
		add_filter( 'themeisle_sdk_blackfriday_data', array( $this, 'add_black_friday_data' ) );
	}

	/**
	 * Render design pack notice.
	 *
	 * @return void
	 */
	public function add_fse_design_pack_notice() {
		if ( ! $this->should_render_design_pack_notice() ) {
			return;
		}

		Assets_Manager::enqueue_style( Assets_Manager::ASSETS_SLUGS['design-pack-notice'], 'design-pack-notice' );
		Assets_Manager::enqueue_script(
			Assets_Manager::ASSETS_SLUGS['design-pack-notice'],
			'design-pack-notice',
			true,
			array(),
			array(
				'nonce'      => wp_create_nonce( 'fork-dismiss-design-pack-notice' ),
				'ajaxUrl'    => esc_url( admin_url( 'admin-ajax.php' ) ),
				'ajaxAction' => 'fork_dismiss_design_pack_notice',
				'buttonLink' => tsdk_utmify( 'https://themeisle.com/plugins/fse-design-pack', 'editor', 'fork' ),
				'strings'    => array(
					'dismiss'    => __( 'Dismiss', 'fork' ),
					'recommends' => __( 'Fork recommends', 'fork' ),
					'learnMore'  => __( 'Learn More', 'fork' ),
					'noticeHtml' => sprintf(
					/* translators: %s: FSE Design Pack: */
						__( '%s Access a collection of 40+ layout patterns ready to import to your website', 'fork' ),
						'<strong>FSE Design Pack:</strong>'
					),
				),
			),
			'designPackNoticeData'
		);
	}

	/**
	 * Should we show the design pack notice?
	 *
	 * @return bool
	 */
	private function should_render_design_pack_notice() {
		// Already using.
		if ( is_plugin_active( 'fse-design-pack/fse-design-pack.php' ) ) {
			return false;
		}

		// Notice was dismissed.
		if ( get_option( Constants::CACHE_KEYS['dismissed-fse-design-pack-notice'], 'no' ) === 'yes' ) {
			return false;
		}

		return true;
	}

	/**
	 * Dismiss the design pack notice.
	 *
	 * @return void
	 */
	public function remove_design_pack_notice() {
		if ( ! isset( $_POST['nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'fork-dismiss-design-pack-notice' ) ) {
			return;
		}
		update_option( Constants::CACHE_KEYS['dismissed-fse-design-pack-notice'], 'yes' );
		wp_die();
	}

	/**
	 * Render the welcome notice.
	 *
	 * @return void
	 */
	public function render_welcome_notice() {
		if ( ! $this->should_show_welcome_notice() ) {
			return;
		}

		$otter_status = $this->get_otter_status();

		Assets_Manager::enqueue_style( Assets_Manager::ASSETS_SLUGS['welcome-notice'], 'welcome-notice' );
		Assets_Manager::enqueue_script(
			Assets_Manager::ASSETS_SLUGS['welcome-notice'],
			'welcome-notice',
			true,
			array(),
			array(
				'nonce'         => wp_create_nonce( 'fork-dismiss-welcome-notice' ),
				'otterRefNonce' => wp_create_nonce( 'fork-set-otter-ref' ),
				'ajaxUrl'       => esc_url( admin_url( 'admin-ajax.php' ) ),
				'otterStatus'   => $otter_status,
				'activationUrl' => esc_url(
					add_query_arg(
						array(
							'plugin_status' => 'all',
							'paged'         => '1',
							'action'        => 'activate',
							'plugin'        => rawurlencode( 'otter-blocks/otter-blocks.php' ),
							'_wpnonce'      => wp_create_nonce( 'activate-plugin_otter-blocks/otter-blocks.php' ),
						),
						admin_url( 'plugins.php' ) 
					) 
				),
				'activating'    => __( 'Activating', 'fork' ) . '&hellip;',
				'installing'    => __( 'Installing', 'fork' ) . '&hellip;',
				'done'          => __( 'Done', 'fork' ),
			) 
		);

		$notice_html  = '<div class="notice notice-info fork-welcome-notice">';
		$notice_html .= '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
		$notice_html .= '<div class="notice-content">';

		$notice_html .= '<img class="otter-preview" src="' . esc_url( Assets_Manager::get_image_url( 'welcome-notice.png' ) ) . '" alt="' . esc_attr__( 'Otter Blocks preview', 'fork' ) . '"/>';

		$notice_html .= '<div class="notice-copy">';

		$notice_html .= '<h1 class="notice-title">';
		/* translators: %s: Otter Blocks */
		$notice_html .= sprintf( __( 'Power up your website building experience with %s!', 'fork' ), '<span>Otter Blocks</span>' );

		$notice_html .= '</h1>';

		$notice_html .= '<p class="description">' . __( 'Otter is a Gutenberg Blocks page builder plugin that adds extra functionality to the WordPress Block Editor (also known as Gutenberg) for a better page building experience without the need for traditional page builders.', 'fork' ) . '</p>';

		$notice_html .= '<div class="actions">';

		/* translators: %s: Otter Blocks */
		$notice_html .= '<button id="fork-install-otter" class="button button-primary button-hero">';
		$notice_html .= '<span class="dashicons dashicons-update hidden"></span>';
		$notice_html .= '<span class="text">';
		$notice_html .= 'installed' === $otter_status ?
			/* translators: %s: Otter Blocks */
			sprintf( __( 'Activate %s', 'fork' ), 'Otter Blocks' ) :
			/* translators: %s: Otter Blocks */
			sprintf( __( 'Install & Activate %s', 'fork' ), 'Otter Blocks' );
		$notice_html .= '</span>';
		$notice_html .= '</button>';

		$notice_html .= '<a href="https://wordpress.org/plugins/otter-blocks/" target="_blank" class="button button-secondary button-hero">';
		$notice_html .= '<span>' . __( 'Learn More', 'fork' ) . '</span>';
		$notice_html .= '<span class="dashicons dashicons-external"></span>';
		$notice_html .= '</a>';

		$notice_html .= '</div>';

		$notice_html .= '</div>';
		$notice_html .= '</div>';
		$notice_html .= '</div>';

		echo wp_kses_post( $notice_html );

	}

	/**
	 * Dismiss the welcome notice.
	 *
	 * @return void
	 */
	public function remove_welcome_notice() {
		if ( ! isset( $_POST['nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'fork-dismiss-welcome-notice' ) ) {
			return;
		}
		update_option( Constants::CACHE_KEYS['dismissed-welcome-notice'], 'yes' );
		wp_die();
	}

	/**
	 * Update Otter reference key.
	 *
	 * @return void
	 */
	public function set_otter_ref() {
		if ( empty( $_POST['nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'fork-set-otter-ref' ) ) {
			return;
		}

		update_option( self::OTTER_REF, 'fork' );

		wp_send_json_success();
	}

	/**
	 * Should we show the welcome notice?
	 *
	 * @return bool
	 */
	private function should_show_welcome_notice(): bool {
		// Already using Otter.
		if ( is_plugin_active( 'otter-blocks/otter-blocks.php' ) ) {
			return false;
		}

		// Notice was dismissed.
		if ( get_option( Constants::CACHE_KEYS['dismissed-welcome-notice'], 'no' ) === 'yes' ) {
			return false;
		}

		$screen = get_current_screen();

		// Only show in dashboard/themes.
		if ( ! in_array( $screen->id, array( 'dashboard', 'themes' ) ) ) {
			return false;
		}

		// AJAX actions.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		// Don't show in network admin.
		if ( is_network_admin() ) {
			return false;
		}

		// User can't dismiss. We don't show it.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// User can't install plugins. We don't show it.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return false;
		}

		// Block editor context.
		if ( $screen->is_block_editor() ) {
			return false;
		}

		// Dismiss after one week from activation.
		$activated_time = get_option( 'fork_install' );

		if ( ! empty( $activated_time ) && time() - intval( $activated_time ) > WEEK_IN_SECONDS ) {
			update_option( Constants::CACHE_KEYS['dismissed-welcome-notice'], 'yes' );

			return false;
		}

		return true;
	}

	/**
	 * Get the Otter Blocks plugin status.
	 *
	 * @return string
	 */
	private function get_otter_status(): string {
		$status = 'not-installed';

		if ( file_exists( ABSPATH . 'wp-content/plugins/otter-blocks/otter-blocks.php' ) ) {
			return 'installed';
		}

		return $status;
	}

	/**
	 * Register internal pages.
	 *
	 * @return void
	 */
	public function register_internal_page() {
		$screen = get_current_screen();
		
		if ( ! current_user_can( 'manage_options' ) || ( 'dashboard' !== $screen->id && 'themes' !== $screen->id ) ) {
			return;
		}
		
		add_filter(
			'themeisle-sdk/survey/' . FORK_PRODUCT_SLUG,
			function( $data, $page_slug ) {
				$install_days_number = intval( ( time() - get_option( 'fork_install', time() ) ) / DAY_IN_SECONDS );

				$data = array(
					'environmentId' => 'clr7hsvmjeu0u8up04ydg210b',
					'attributes'    => array(
						'install_days_number' => $install_days_number,
						'version'             => FORK_VERSION,
					),
				);

				return $data;
			},
			10,
			2 
		);
		do_action( 'themeisle_internal_page', FORK_PRODUCT_SLUG, $screen->id );
	}

	/**
	 * Add Black Friday data.
	 *
	 * @param array $configs The configuration array for the loaded products.
	 *
	 * @return array
	 */
	public function add_black_friday_data( $configs ) {
		$config = $configs['default'];

		// translators: %1$s - plugin name, %2$s - HTML tag, %3$s - discount.
		$message_template = __( 'Enhance %1$s with %2$s– up to %3$s OFF in our biggest sale of the year. Limited time only.', 'fork' );

		$config['dismiss']  = true; // Note: Allow dismiss since it appears on `/wp-admin`.
		$config['message']  = sprintf( $message_template, 'Fork', 'Otter Blocks Pro', '70%' );
		$config['sale_url'] = add_query_arg(
			array(
				'utm_term' => 'free',
			),
			tsdk_translate_link( tsdk_utmify( 'https://themeisle.link/otter-bf', 'bfcm', 'fork' ) )
		);

		$configs[ FORK_PRODUCT_SLUG ] = $config;

		return $configs;
	}
}
