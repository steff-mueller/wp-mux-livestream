/**
 * External dependencies
 */
const path = require( 'path' );
const { defineConfig } = require( '@playwright/test' );

/**
 * WordPress dependencies
 */
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

/**
 * Extends the config shipped with @wordpress/scripts, which already points at
 * the wp-env tests instance (http://localhost:8889), logs in as admin and
 * stores artifacts under `artifacts/`.
 */
module.exports = defineConfig( {
	...baseConfig,
	// The base config's `./specs` resolves relative to its own location inside
	// node_modules, so it has to be overridden.
	testDir: path.join( __dirname, 'tests', 'e2e' ),
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	webServer: {
		...baseConfig.webServer,
		// The base config runs `npm run wp-env start`; this project names the
		// script `env`. `reuseExistingServer` is inherited, so an already
		// running wp-env is picked up rather than restarted.
		command: 'npm run env start',
	},
} );
