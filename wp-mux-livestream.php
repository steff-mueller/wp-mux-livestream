<?php
/**
 * Plugin Name:       Wp Mux Livestream
 * Description:       Example block scaffolded with Create Block tool.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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
function set_playback_id($stream_id, $playback_id) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'mux_livestreams';
	$wpdb->replace(
		$table_name,
		array(
			'stream_id' => $stream_id,
			'playback_id' => $playback_id,
		)
	);
}

function handle_mux_webhook( \WP_REST_Request $request ) {
	$payload = $request->get_json_params();
	$event = $payload['type'];

	if ( 'video.live_stream.connected' == $event ) {
		$stream_id = $payload['data']['id'];
		$playback_id = $payload['data']['playback_ids'][0]['id'];
		set_playback_id($stream_id, $playback_id);
	}
	else if ( 'video.asset.live_stream_completed' == $event ) {
		$stream_id = $payload['data']['live_stream_id'];
		$playback_id = $payload['data']['playback_ids'][0]['id'];
		set_playback_id($stream_id, $playback_id);
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
