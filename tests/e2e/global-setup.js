/**
 * External dependencies
 */
const { spawnSync } = require( 'node:child_process' );

/**
 * WordPress dependencies
 */
const baseGlobalSetup = require( '@wordpress/scripts/config/playwright/global-setup' );

/**
 * Internal dependencies
 */
const { WEBHOOK_SECRET } = require( './mux-mock' );

/**
 * Run a WP-CLI command against the *tests* WordPress instance (port 8889).
 *
 * Args are passed as an array so nothing goes through a host shell. Anything
 * after `--` is forwarded to WP-CLI verbatim instead of being swallowed by
 * wp-env's own option parser.
 *
 * @param {string[]} args WP-CLI arguments.
 */
function testsCli( args ) {
	const result = spawnSync(
		process.execPath,
		[
			require.resolve( '@wordpress/env/bin/wp-env' ),
			'run',
			'tests-cli',
			'wp',
			...args,
		],
		{ stdio: 'inherit' }
	);

	if ( result.status !== 0 ) {
		throw new Error(
			`wp-env run tests-cli wp ${ args.join( ' ' ) } failed with status ${
				result.status
			}`
		);
	}
}

/**
 * @param {import('@playwright/test').FullConfig} config
 */
module.exports = async function globalSetup( config ) {
	// Authenticate over REST and save the admin storage state to disk.
	await baseGlobalSetup( config );

	// Seed the Mux webhook signing secret. This is normally entered by hand at
	// Settings -> Mux Livestream; the option is not exposed over REST, so
	// WP-CLI is the only way in. It is the suite's single piece of global
	// state — everything else is namespaced per test by stream ID.
	testsCli( [
		'option',
		'update',
		'wp_mux_livestream_options',
		JSON.stringify( { webhook_secret: WEBHOOK_SECRET } ),
		'--',
		'--format=json',
	] );
};
