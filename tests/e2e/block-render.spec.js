/**
 * Integration tests for the Mux Livestream block.
 *
 * Each test drives the plugin the way Mux does in production — a signed
 * webhook — and then checks what a visitor actually sees on the published
 * post. Mux itself is never contacted; see ./mux-mock.js.
 *
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const {
	WEBHOOK_SECRET,
	signMuxPayload,
	liveStreamConnectedPayload,
	liveStreamCompletedPayload,
	sendMuxWebhook,
	stubMuxNetwork,
} = require( './mux-mock' );

/**
 * Publish a post containing a single Mux Livestream block and open it.
 *
 * The block is dynamic — index.js registers no `save` — so the serialized form
 * is a self-closing block comment and every byte of the frontend output comes
 * from render.php.
 *
 * @param {Object} utils
 * @param {Object} utils.requestUtils
 * @param {Object} utils.page
 * @param {string} streamId           Value for the block's `streamId` attribute.
 * @param {string} title              Post title.
 */
async function publishAndVisit( { requestUtils, page }, streamId, title ) {
	const post = await requestUtils.createPost( {
		title,
		content: `<!-- wp:create-block/wp-mux-livestream {"streamId":"${ streamId }"} /-->`,
		status: 'publish',
	} );

	await stubMuxNetwork( page );
	await page.goto( post.link );

	return post;
}

const posterUrl = ( playbackId, isLive ) =>
	`https://image.mux.com/${ playbackId }/thumbnail.jpg${
		isLive ? '?latest=true' : ''
	}`;

test.describe( 'Mux Livestream block', () => {
	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'renders the player for a stream that is live', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		const streamId = 'e2e-live';
		const playbackId = 'e2e-live-playback';

		const response = await sendMuxWebhook(
			request,
			liveStreamConnectedPayload( streamId, playbackId )
		);
		expect( response.status() ).toBe( 200 );

		await publishAndVisit(
			{ requestUtils, page },
			streamId,
			'Live stream'
		);

		const player = page.locator( 'mux-player' );

		// Note: no toBeVisible() — the stubbed custom element is unstyled and
		// therefore has a zero-sized box.
		await expect( player ).toHaveCount( 1 );
		await expect( player ).toHaveAttribute( 'playback-id', playbackId );
		await expect( player ).toHaveAttribute(
			'poster',
			posterUrl( playbackId, true )
		);

		// Set by the stub in its connectedCallback, so this only passes if the
		// block's <script> tag really did load and execute the player bundle.
		await expect( player ).toHaveAttribute( 'data-stub-upgraded', 'true' );
	} );

	test( 'renders the recording without the live poster hint once the stream has ended', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		const streamId = 'e2e-completed';
		const playbackId = 'e2e-completed-playback';

		const response = await sendMuxWebhook(
			request,
			liveStreamCompletedPayload( streamId, playbackId )
		);
		expect( response.status() ).toBe( 200 );

		await publishAndVisit(
			{ requestUtils, page },
			streamId,
			'Completed stream'
		);

		const player = page.locator( 'mux-player' );

		await expect( player ).toHaveCount( 1 );
		await expect( player ).toHaveAttribute( 'playback-id', playbackId );
		await expect( player ).toHaveAttribute(
			'poster',
			posterUrl( playbackId, false )
		);
	} );

	test( 'switches from the live stream to the recording on the same block', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		const streamId = 'e2e-transition';
		const livePlaybackId = 'e2e-transition-live-playback';
		const assetPlaybackId = 'e2e-transition-asset-playback';

		await sendMuxWebhook(
			request,
			liveStreamConnectedPayload( streamId, livePlaybackId )
		);

		const post = await publishAndVisit(
			{ requestUtils, page },
			streamId,
			'Transitioning stream'
		);

		await expect( page.locator( 'mux-player' ) ).toHaveAttribute(
			'poster',
			posterUrl( livePlaybackId, true )
		);

		// The stream ends: same stream_id, different playback ID.
		await sendMuxWebhook(
			request,
			liveStreamCompletedPayload( streamId, assetPlaybackId )
		);

		await page.goto( post.link );

		const player = page.locator( 'mux-player' );

		// Exactly one row survived — set_playback_id() upserts on the primary
		// key rather than inserting a second row for the same stream.
		await expect( player ).toHaveCount( 1 );
		await expect( player ).toHaveAttribute(
			'playback-id',
			assetPlaybackId
		);
		await expect( player ).toHaveAttribute(
			'poster',
			posterUrl( assetPlaybackId, false )
		);
	} );

	test( 'falls back to a notice for a stream no webhook has reported yet', async ( {
		page,
		requestUtils,
	} ) => {
		await publishAndVisit(
			{ requestUtils, page },
			'e2e-never-reported',
			'Unknown stream'
		);

		await expect(
			page.getByText( 'No playback ID available for this stream.' )
		).toBeVisible();
		await expect( page.locator( 'mux-player' ) ).toHaveCount( 0 );
	} );

	test( 'rejects a webhook signed with the wrong secret and stores nothing', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		const streamId = 'e2e-bad-signature';

		const response = await sendMuxWebhook(
			request,
			liveStreamConnectedPayload(
				streamId,
				'e2e-bad-signature-playback'
			),
			{ secret: `not-${ WEBHOOK_SECRET }` }
		);
		expect( response.status() ).toBe( 401 );

		await publishAndVisit(
			{ requestUtils, page },
			streamId,
			'Forged webhook'
		);

		await expect(
			page.getByText( 'No playback ID available for this stream.' )
		).toBeVisible();
		await expect( page.locator( 'mux-player' ) ).toHaveCount( 0 );
	} );

	test( 'rejects a webhook with no signature header', async ( {
		request,
	} ) => {
		const response = await sendMuxWebhook(
			request,
			liveStreamConnectedPayload(
				'e2e-no-signature',
				'e2e-no-signature-playback'
			),
			{ signature: null }
		);

		expect( response.status() ).toBe( 401 );
	} );

	test( 'rejects a webhook whose body was tampered with after signing', async ( {
		request,
	} ) => {
		const original = liveStreamConnectedPayload(
			'e2e-tampered',
			'e2e-tampered-playback'
		);
		const signature = signMuxPayload( JSON.stringify( original ) );

		// Same signature, different body — the HMAC covers the raw bytes.
		const tampered = liveStreamConnectedPayload(
			'e2e-tampered',
			'attacker-controlled-playback'
		);
		const response = await sendMuxWebhook( request, tampered, {
			signature,
		} );

		expect( response.status() ).toBe( 401 );
	} );
} );
