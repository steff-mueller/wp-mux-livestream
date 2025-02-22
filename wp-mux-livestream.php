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
function create_block_wp_mux_livestream_block_init() {
	register_block_type( __DIR__ . '/build/wp-mux-livestream' );
}
add_action( 'init', 'create_block_wp_mux_livestream_block_init' );

function handle_mux_webhook( WP_REST_Request $request ) {
	$payload = $request->get_json_params();
	$event = $payload['type'];

	if ( 'video.live_stream.connected' == $event ) {
		$stream_id = $payload['data']['id'];
		$playback_id = $payload['data']['playback_ids'][0]['id'];
		echo "video.live_stream.connected\n";
		echo "Stream ID: $stream_id\n";
		echo "Playback ID: $playback_id\n";
	}
	else if ( 'video.asset.live_stream_completed' == $event ) {
		$stream_id = $payload['data']['live_stream_id'];
		$playback_id = $payload['data']['playback_ids'][0]['id'];
		echo "video.asset.live_stream_completed\n";
		echo "Stream ID: $stream_id\n";
		echo "Playback ID: $playback_id\n";
	}

	return new WP_REST_Response( 'ok' );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'wp-mux-livestream/v1', '/webhooks/mux', array(
		'methods' => 'POST',
		'callback' => 'handle_mux_webhook',
		'permission_callback' => '__return_true',
	) );
} );
