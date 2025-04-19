# wp-mux-livestream

## Installation

Register a Mux webhook with destination URL `<your-site-url>/?rest_route=/wp-mux-livestream/v1/webhooks/mux`
(see https://www.mux.com/docs/core/listen-for-webhooks#configuring-endpoints).

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
