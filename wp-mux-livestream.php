<?php
/**
 * Plugin Name:       WP Mux Livestream
 * Description:       Mux Livestream Block for WordPress.
 * Version:           0.1.2
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Steffen Müller
 * License:           MIT
 * Text Domain:       wp-mux-livestream
 *
 * @package CreateBlock
 */

namespace WpMuxLivestream;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function block_init() {
	register_block_type( __DIR__ . '/build/wp-mux-livestream' );
}
add_action( 'init', __NAMESPACE__ . '\block_init' );

/**
 * Insert or update the playback ID for a live stream.
 */
function set_playback_id($stream_id, $playback_id, $is_live) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'mux_livestreams';
	$wpdb->replace(
		$table_name,
		array(
			'stream_id' => $stream_id,
			'playback_id' => $playback_id,
            'is_live' => $is_live,
		)
	);
}

function validate_mux_signature( \WP_REST_Request $request ) {
	// Read headers from incoming request
	// and get the Mux-Signature header
	$signature = $request->get_header('mux_signature');
	if ( empty($signature) ) {
		return false;
	}

	// Split the signature based on ','.
    // Format is 't=[timestamp],v1=[hash]'
	$signature_parts = explode(',', $signature);

	if( empty($signature_parts)
		|| empty($signature_parts[0])
		|| empty($signature_parts[1])
	) {
        return false;
    }

	// Strip the first occurence of 't=' and 'v1=' from both strings
	$timestamp = str_replace('t=', '', $signature_parts[0]);
	$mux_hash = str_replace('v1=', '', $signature_parts[1]);

	// Create a payload of the timestamp from the Mux signature
	// and the request body with a '.' in-between
	$payload = $timestamp . '.' . $request->get_body();

	$options = \get_option( 'wp_mux_livestream_options' );
    $webhook_secret = $options['webhook_secret'] ?? '';

	// Build a HMAC hash using SHA256 algo, using our webhook secret
    $our_hash = hash_hmac('sha256', $payload, $webhook_secret);

    // `hash_equals` performs a timing-safe crypto comparison
    return hash_equals($our_hash, $mux_hash);
}

function handle_mux_webhook( \WP_REST_Request $request ) {
	$payload = $request->get_json_params();

	if ( ! validate_mux_signature( $request ) ) {
		return new \WP_REST_Response( 'Invalid signature', 401 );
	}

	$event = $payload['type'];

	if ( 'video.live_stream.connected' == $event ) {
		$stream_id = $payload['data']['id'];
		$playback_id = $payload['data']['playback_ids'][0]['id'];
		set_playback_id($stream_id, $playback_id, true);
	}
	else if ( 'video.asset.live_stream_completed' == $event ) {
		$stream_id = $payload['data']['live_stream_id'];
		$playback_id = $payload['data']['playback_ids'][0]['id'];
		set_playback_id($stream_id, $playback_id, false);
	}

	return new \WP_REST_Response( 'ok' );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'wp-mux-livestream/v1', '/webhooks/mux', array(
		'methods' => 'POST',
		'callback' => __NAMESPACE__ . '\handle_mux_webhook',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Create the database table for the plugin.
 */
function create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mux_livestreams';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        stream_id varchar(255) NOT NULL,
        playback_id varchar(255) NOT NULL,
        is_live boolean DEFAULT false NOT NULL,
        PRIMARY KEY  (stream_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\create_db_table' );

/**
 * Delete the database table for the plugin.
 */
function delete_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mux_livestreams';
    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query( $sql );
}

register_uninstall_hook( __FILE__, __NAMESPACE__ . '\delete_db_table' );

// --- Settings Page ---

/**
 * Add the settings page to the admin menu.
 */
function add_settings_page() {
    \add_options_page(
        \__( 'Mux Livestream Settings', 'wp-mux-livestream' ),
        \__( 'Mux Livestream', 'wp-mux-livestream' ),
        'manage_options',
        'wp-mux-livestream',
        __NAMESPACE__ . '\render_settings_page'
    );
}
\add_action( 'admin_menu', __NAMESPACE__ . '\add_settings_page' );

/**
 * Register settings, sections, and fields.
 */
function settings_init() {
    \register_setting( 'wp_mux_livestream', 'wp_mux_livestream_options', array(
        'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
    ) );

    \add_settings_section(
        'wp_mux_livestream_section_webhook',
        \__( 'Webhook Settings', 'wp-mux-livestream' ),
        __NAMESPACE__ . '\section_webhook_callback',
        'wp_mux_livestream'
    );

    \add_settings_field(
        'webhook_secret',
        \__( 'Mux Webhook Signing Secret', 'wp-mux-livestream' ),
        __NAMESPACE__ . '\field_webhook_secret_callback',
        'wp_mux_livestream',
        'wp_mux_livestream_section_webhook'
    );
}
\add_action( 'admin_init', __NAMESPACE__ . '\settings_init' );

/**
 * Sanitize settings before saving.
 */
function sanitize_settings( $input ) {
    $new_input = array();
    if ( isset( $input['webhook_secret'] ) ) {
        $new_input['webhook_secret'] = \sanitize_text_field( $input['webhook_secret'] );
    }
    return $new_input;
}

/**
 * Callback for the webhook settings section.
 */
function section_webhook_callback() {
    echo '<p>' . \__( 'Configure the settings for receiving Mux webhooks.', 'wp-mux-livestream' ) . '</p>';
    $webhook_url = \get_rest_url( null, 'wp-mux-livestream/v1/webhooks/mux' );
    echo '<p>' . \__( 'Your webhook URL is:', 'wp-mux-livestream' ) . ' <code>' . \esc_url( $webhook_url ) . '</code></p>';
}

/**
 * Callback for the webhook secret field.
 */
function field_webhook_secret_callback() {
    $options = \get_option( 'wp_mux_livestream_options' );
    $secret = $options['webhook_secret'] ?? '';
    echo '<input type="password" id="webhook_secret" name="wp_mux_livestream_options[webhook_secret]" value="' . \esc_attr( $secret ) . '" size="50" />';
    echo '<p class="description">' . \__( 'Enter the signing secret provided by Mux for webhook verification.', 'wp-mux-livestream' ) . '</p>';
}

/**
 * Render the HTML for the settings page.
 */
function render_settings_page() {
    if ( ! \current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            \settings_fields( 'wp_mux_livestream' ); // Output nonce, action, and option_page fields for a settings page.
            \do_settings_sections( 'wp_mux_livestream' ); // Print out all settings sections added to a particular settings page.
            \submit_button( \__( 'Save Settings', 'wp-mux-livestream' ) );
            ?>
        </form>
    </div>
    <?php
}
