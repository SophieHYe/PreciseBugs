# pengu

**pengu** is lightweight web chat built with HTML 5 resembling Club Penguin.

## Prerequisites

The project uses npm to install dependencies and Node.js to run. Download them by running [Nix](https://nixos.org/download.html)’s `nix-shell` command, using your system’s package manager or from [their website](https://nodejs.org/en/).

Optionally, you will also need [PostgreSQL](https://www.postgresql.org/) database server if you want Pengu to remember player inventories across restarts.

## Installation

1. Create the following table in PostgreSQL database and set `DATABASE_URL` to correct connection string.

```
CREATE TABLE "penguin" (
	"name" character varying NOT NULL PRIMARY KEY,
	"closet" json DEFAULT '{}' NOT NULL,
	"clothing" json DEFAULT '[]' NOT NULL,
	"registered" timestamptz DEFAULT current_timestamp NOT NULL,
	"banned" boolean DEFAULT false NOT NULL,
	"group" character varying DEFAULT 'basic' NOT NULL
);
```

2. Run `npm install`.
3. Compile assets using `npm run build`.
4. Start with `npm start`, the game will run on port set by `PORT` env variable.

## Configuration

pengu uses the following environment variables for configuration:

* `PORT` – The port a web server will be running on. Of not specified it defaults to `8080`.
* `DATABASE_URL` – [Connection string](https://node-postgres.com/features/connecting/#connection-uri) for the PostgreSQL database. If omitted, persistence will be missing.
* `NODE_ENV` – Can be set to `production` for less verbose logs. Defaults to `development`.
* `OPENID_PROVIDER` – If this is set, pengu will use OpenID to log-in. Though it only supports using a hardcoded identity specified by this variable. User will be redirected to the provider, where they will confirm their credentials, and then be redirected back to Pengu with an access code. Pengu will then verify the access code against the OpenID verification URL and realm specified by `OPENID_VERIFY`, `OPENID_REALM` environment variables.
* `ACCEPTED_ORIGINS` – comma-separated list of domain names that are allowed to access the WebSockets server. This is necessary to prevent [cross-site request forgery](https://en.wikipedia.org/wiki/WebSocket#Security_considerations). When the environment variable is not set, only `localhost` and `127.0.0.1` will be allowed to connect.

## Development

The front-end of Pengu is stored in the `assets/` directory and needs to be rebuilt when it is changed. You can use `npm run build` command to do that, or `npm run dev` if you want it to rebuild automatically on changes. Its build artefacts end up in the `client/` directory, from where the server will serve them.

## License

pengu source code is made available under MIT license.
