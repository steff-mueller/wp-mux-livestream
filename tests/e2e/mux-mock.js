/**
 * Mocks for the Mux integration.
 *
 * The plugin never calls out to the Mux API — it is driven entirely by inbound
 * webhooks from Mux, and the rendered block then pulls two assets from Mux's
 * CDNs. Both boundaries are faked here so the suite needs no Mux account and
 * makes no external requests.
 *
 * External dependencies
 */
const crypto = require( 'node:crypto' );

/**
 * Signing secret seeded into the test site by the global setup.
 *
 * @see tests/e2e/global-setup.js
 */
const WEBHOOK_SECRET = 'e2e-mux-webhook-secret';

/**
 * REST route of the plugin's webhook receiver, in the `?rest_route=` form the
 * README tells users to register with Mux.
 */
const WEBHOOK_PATH = '/?rest_route=/wp-mux-livestream/v1/webhooks/mux';

/** Smallest possible transparent PNG, stands in for a Mux thumbnail. */
const ONE_PIXEL_PNG = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
	'base64'
);

/**
 * Build a `Mux-Signature` header value for a raw request body.
 *
 * Mirrors `WpMuxLivestream\validate_mux_signature()`: HMAC-SHA256 over
 * `"<timestamp>.<raw body>"`, formatted as `t=<timestamp>,v1=<hex digest>`.
 * PHP's `hash_hmac()` returns lowercase hex, which is what `digest('hex')` gives.
 *
 * @param {string} body      Raw request body, byte-for-byte as it will be sent.
 * @param {string} secret    Signing secret.
 * @param {number} timestamp Unix timestamp in seconds.
 * @return {string} Signature header value.
 */
function signMuxPayload(
	body,
	secret = WEBHOOK_SECRET,
	timestamp = Math.floor( Date.now() / 1000 )
) {
	const digest = crypto
		.createHmac( 'sha256', secret )
		.update( `${ timestamp }.${ body }` )
		.digest( 'hex' );

	return `t=${ timestamp },v1=${ digest }`;
}

/**
 * A `video.live_stream.connected` webhook — the live stream just went live.
 *
 * The plugin reads the stream ID from `data.id` on this event.
 *
 * @param {string} streamId
 * @param {string} playbackId
 * @return {Object} Webhook payload.
 */
function liveStreamConnectedPayload( streamId, playbackId ) {
	return {
		type: 'video.live_stream.connected',
		id: crypto.randomUUID(),
		created_at: new Date().toISOString(),
		object: { type: 'live', id: streamId },
		data: {
			id: streamId,
			status: 'connected',
			stream_key: 'e2e-stream-key',
			playback_ids: [ { id: playbackId, policy: 'public' } ],
		},
	};
}

/**
 * A `video.asset.live_stream_completed` webhook — the stream ended and the
 * recording is ready.
 *
 * Note the different shape: the stream ID lives in `data.live_stream_id` here,
 * because `data.id` is the *asset* ID. The playback ID is the asset's, which
 * differs from the live playback ID.
 *
 * @param {string} streamId
 * @param {string} assetPlaybackId
 * @return {Object} Webhook payload.
 */
function liveStreamCompletedPayload( streamId, assetPlaybackId ) {
	return {
		type: 'video.asset.live_stream_completed',
		id: crypto.randomUUID(),
		created_at: new Date().toISOString(),
		object: { type: 'asset', id: `asset-${ streamId }` },
		data: {
			id: `asset-${ streamId }`,
			live_stream_id: streamId,
			status: 'ready',
			duration: 42.5,
			playback_ids: [ { id: assetPlaybackId, policy: 'public' } ],
		},
	};
}

/**
 * POST a webhook to the plugin the way Mux would.
 *
 * The body is sent as a raw string rather than an object: the signature covers
 * the exact bytes that `WP_REST_Request::get_body()` will see, so any
 * re-serialisation in between would invalidate it.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @param {Object}                                       payload             Webhook payload, serialised as-is.
 * @param {Object}                                       [options]
 * @param {string}                                       [options.secret]    Secret to sign with.
 * @param {string|null}                                  [options.signature] Override the header outright; `null` omits it.
 * @return {Promise<import('@playwright/test').APIResponse>} The response.
 */
async function sendMuxWebhook( request, payload, options = {} ) {
	const { secret = WEBHOOK_SECRET, signature } = options;
	const body = JSON.stringify( payload );

	const headers = { 'content-type': 'application/json' };

	// `undefined` means "sign it normally"; `null` means "send no signature".
	const value =
		signature === undefined ? signMuxPayload( body, secret ) : signature;
	if ( value !== null ) {
		headers[ 'Mux-Signature' ] = value;
	}

	return request.post( WEBHOOK_PATH, { headers, data: body } );
}

/**
 * Intercept the two Mux CDN requests the rendered block makes.
 *
 * Keeps the suite hermetic (no jsDelivr outage can break it) and gives us a
 * signal that the block's inline `<script>` tag actually loaded and ran: the
 * stub sets `data-stub-upgraded` on the custom element when it upgrades.
 *
 * @param {import('@playwright/test').Page} page
 */
async function stubMuxNetwork( page ) {
	await page.route(
		'https://cdn.jsdelivr.net/npm/@mux/mux-player**',
		( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/javascript',
				body: `
				customElements.define(
					'mux-player',
					class extends HTMLElement {
						connectedCallback() {
							this.setAttribute( 'data-stub-upgraded', 'true' );
						}
					}
				);
			`,
			} )
	);

	await page.route( '**image.mux.com/**', ( route ) =>
		route.fulfill( {
			status: 200,
			contentType: 'image/png',
			body: ONE_PIXEL_PNG,
		} )
	);
}

module.exports = {
	WEBHOOK_SECRET,
	WEBHOOK_PATH,
	signMuxPayload,
	liveStreamConnectedPayload,
	liveStreamCompletedPayload,
	sendMuxWebhook,
	stubMuxNetwork,
};
