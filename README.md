# wp-mux-livestream

Provides a Mux Livestream block to embed a livestream on your Wordpress site. The block displays the livestream if you are live, or the last recording of the livestream if the livestream is not active.

This plugin requires a https://www.mux.com account.

## Installation

1. Download the latest plugin zip file from [releases](https://github.com/steff-mueller/wp-mux-livestream/releases) and install the plugin.
2. In Wordpress, go to "Settings > Mux Livestream" and note the webhook url (should be something like `<your-site-url>/?rest_route=/wp-mux-livestream/v1/webhooks/mux`).
3. Login into Mux and create a webhook pointing to the url noted above. See https://www.mux.com/docs/core/listen-for-webhooks#configuring-endpoints how to create a webhook.
4. Copy the webhook secret from Mux into "Settings > Mux Livestream > Mux Webhook Signing Secret" in Wordpress. Click on "Save Settings".

Now you can create a "Mux Livestream" block in the Wordpress editor. Enter your Mux live stream id in the editor sidebar.

### Caveats

Currently, after you install the plugin, it won't be able to find any of your past recordings. The plugin will only detect active livestreams and recordings which have been created after the webhook registration.

## Development

Requirements:

- Docker
- Node.js
- git

For local development, create a tunnel to your local environment with a tool like https://ngrok.com/.
See https://ngrok.com/docs/integrations/mux/webhooks/ for further details.

Start development environment:

```bash
$ npm run env start
$ npm run composer install
```

Rebuild the project after changes with `$ npm run build`.

This project is using [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).
