<?php
/**
 * Settings page for Rewrites plugin.
 *
 * @package Rewrites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rewrites_Settings
 *
 * Settings page and checklist configuration for the Rewrites plugin.
 */
class Rewrites_Settings {

	/**
	 * Option name for checklist items.
	 */
	const OPTION_CHECKLIST = 'rewrites_checklist_items';

	/**
	 * Option name for feature toggle.
	 */
	const OPTION_ENABLED = 'rewrites_checklist_enabled';

	/**
	 * Singleton instance.
	 *
	 * @var Rewrites_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Rewrites_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_scripts' ) );
		add_action( 'wp_ajax_rewrites_save_checklist', array( $this, 'ajax_save_checklist' ) );
	}

	/**
	 * Add settings submenu page.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'rewrites',
			__( 'Rewrites Settings', 'rewrites' ),
			__( 'Settings', 'rewrites' ),
			'manage_options',
			'rewrites-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'rewrites_settings', self::OPTION_CHECKLIST );
		register_setting( 'rewrites_settings', self::OPTION_ENABLED );
	}

	/**
	 * Enqueue settings page scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_settings_scripts( $hook ) {
		if ( 'rewrites_page_rewrites-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'rewrites-settings',
			REWRITES_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			REWRITES_VERSION
		);

		wp_enqueue_script(
			'rewrites-settings',
			REWRITES_PLUGIN_URL . 'assets/js/settings.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			REWRITES_VERSION,
			true
		);

		wp_localize_script(
			'rewrites-settings',
			'rewritesSettings',
			array(
				'nonce'   => wp_create_nonce( 'rewrites_save_checklist' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'confirmDelete' => __( 'Are you sure you want to delete this checklist item?', 'rewrites' ),
					'saved'         => __( 'Settings saved.', 'rewrites' ),
					'error'         => __( 'Failed to save settings.', 'rewrites' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for saving checklist items.
	 */
	public function ajax_save_checklist() {
		check_ajax_referer( 'rewrites_save_checklist', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rewrites' ) ) );
		}

		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		$items   = isset( $_POST['items'] ) ? map_deep( wp_unslash( $_POST['items'] ), 'sanitize_text_field' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Sanitize items.
		$sanitized_items = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['label'] ) ) {
				$sanitized_items[] = array(
					'label'    => sanitize_text_field( $item['label'] ),
					'required' => ! empty( $item['required'] ),
				);
			}
		}

		update_option( self::OPTION_ENABLED, $enabled );
		update_option( self::OPTION_CHECKLIST, $sanitized_items );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'rewrites' ) ) );
	}

	/**
	 * Get checklist items.
	 *
	 * @return array
	 */
	public static function get_checklist_items() {
		$items = get_option( self::OPTION_CHECKLIST, null );

		// Default items if none configured.
		if ( null === $items ) {
			$items = array(
				array(
					'label'    => __( 'I have reviewed all changes', 'rewrites' ),
					'required' => true,
				),
				array(
					'label'    => __( 'Content has been proofread for errors', 'rewrites' ),
					'required' => true,
				),
				array(
					'label'    => __( 'Links have been verified', 'rewrites' ),
					'required' => false,
				),
			);
		}

		return $items;
	}

	/**
	 * Check if checklist feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, true );
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$enabled = self::is_enabled();
		$items   = self::get_checklist_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rewrites Settings', 'rewrites' ); ?></h1>

			<form id="rewrites-settings-form" method="post">
				<h2><?php esc_html_e( 'Publication Checklist', 'rewrites' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'When enabled, authors will see a checklist before publishing changes to published posts. They can choose to save as a rewrite (for review) or confirm the checklist and publish immediately.', 'rewrites' ); ?>
				</p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Checklist', 'rewrites' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="rewrites_checklist_enabled" id="rewrites-checklist-enabled" value="1" <?php checked( $enabled ); ?>>
								<?php esc_html_e( 'Show checklist when updating published posts', 'rewrites' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Checklist Items', 'rewrites' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Configure the items that appear in the publication checklist. Required items must be checked before publishing immediately.', 'rewrites' ); ?>
				</p>

				<div id="rewrites-checklist-items" class="rewrites-checklist-editor">
					<?php foreach ( $items as $index => $item ) : ?>
						<div class="rewrites-checklist-item" data-index="<?php echo esc_attr( $index ); ?>">
							<span class="rewrites-checklist-handle dashicons dashicons-menu"></span>
							<input type="text" class="rewrites-checklist-label regular-text" value="<?php echo esc_attr( $item['label'] ); ?>" placeholder="<?php esc_attr_e( 'Checklist item text...', 'rewrites' ); ?>">
							<label class="rewrites-checklist-required">
								<input type="checkbox" <?php checked( $item['required'] ); ?>>
								<?php esc_html_e( 'Required', 'rewrites' ); ?>
							</label>
							<button type="button" class="button rewrites-checklist-delete" title="<?php esc_attr_e( 'Delete', 'rewrites' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button" id="rewrites-add-checklist-item">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Item', 'rewrites' ); ?>
					</button>
				</p>

				<p class="submit">
					<button type="submit" class="button button-primary" id="rewrites-save-settings">
						<?php esc_html_e( 'Save Settings', 'rewrites' ); ?>
					</button>
					<span id="rewrites-settings-status"></span>
				</p>
			</form>
		</div>

		<style>
			.rewrites-checklist-editor {
				max-width: 600px;
			}
			.rewrites-checklist-item {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px;
				margin-bottom: 4px;
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 4px;
			}
			.rewrites-checklist-handle {
				cursor: move;
				color: #999;
			}
			.rewrites-checklist-label {
				flex: 1;
			}
			.rewrites-checklist-required {
				white-space: nowrap;
			}
			.rewrites-checklist-delete .dashicons {
				width: 16px;
				height: 16px;
				font-size: 16px;
			}
			#rewrites-settings-status {
				margin-left: 10px;
				color: #00a32a;
			}
			#rewrites-settings-status.error {
				color: #d63638;
			}
			#rewrites-add-checklist-item .dashicons {
				vertical-align: middle;
				margin-top: -2px;
			}
		</style>
		<?php
	}
}
