This is a first aplha version.
This is not a complete README.

Notice that this project needs PHPMailer for working. See composer files.
Also needs HugeRTE (look language installation)

The participation request form is protected with [ALTCHA](https://altcha.org/), a
proof of work captcha that needs no external service: the widget is served from
`js/altcha/` and the challenges are generated and checked by `utils/altcha.php`.

## Running the application with Docker Compose

The repository ships a `Dockerfile` (PHP 8.3 + Apache) and a `docker-compose.yml` that
brings up two services: `web` (the application) and `db` (MariaDB 11.4). The image
installs the dependencies mentioned above (PHPMailer and HugeRTE) on its own, so there is
no need to run `composer` by hand.

### Requirements

* Docker Engine with the Compose v2 plugin (`docker compose`, no hyphen).

### Getting started

1. Copy the sample environment file and adjust it:

   ```sh
   cp .env.dev .env
   ```

   Change at least `DB_PASSWORD`, `DB_ROOT_PASSWORD` and `ADMIN_PASSWORD`.
   Docker Compose reads `.env` automatically; if you skip this step, the defaults declared
   in `docker-compose.yml` are used.

2. Build the image and start the containers:

   ```sh
   docker compose up -d --build
   ```

3. Open `http://localhost:8080` (or whichever port you set in `WEB_PORT`) and log in with
   the administrator account defined by `ADMIN_USER` / `ADMIN_PASSWORD`.

The first start takes a while: MariaDB loads the schema and the `web` container waits for
the database to be ready before serving requests.

### Environment variables

| Variable | Default | Description |
| --- | --- | --- |
| `WEB_PORT` | `8080` | Host port the application is published on. |
| `DB_NAME` | `mlsurvey` | Database name. |
| `DB_USER` | `mlsurvey` | Database user. |
| `DB_PASSWORD` | `mlsurvey` | Password for that user. |
| `DB_ROOT_PASSWORD` | `mlsurvey-root` | MariaDB `root` password. |
| `DB_PREFIX` | *(empty)* | Optional prefix for table names. |
| `PROXY_PATH`, `PROXY_PORT` | *(empty)* | Only needed when the application sits behind a reverse proxy on a subdirectory or a different port. |
| `LOG_LEVEL` | `0` | Log level: `0` error, `1` warning, `2` info, `3` debug. Use `0` in production. |
| `ALTCHA_ENABLED` | `true` | ALTCHA captcha on the participation request form. Set it to `false` when the site is served over plain HTTP: the proof of work needs Web Crypto, only available on secure contexts (HTTPS or `localhost`). |
| `ALTCHA_HMAC_KEY` | *(empty)* | Key used to sign the captcha challenges. When empty, a key is generated for each session. |
| `ADMIN_USER`, `ADMIN_PASSWORD` | `admin` / `admin` | Initial administrator; created on startup if it does not exist yet. |

The entrypoint generates `config/config.php` from these variables on every start. If you
would rather manage that file yourself, mount it into the container: when the entrypoint
finds a `config/config.php` it did not generate, it leaves it untouched.

### Initial data

`modelo_datos.sql` (schema) and `test-data.sql` (sample data) are loaded **only the first
time**, while the `db_data` volume is still empty. `test-data.sql` is example material:
for a real deployment, comment out that volume line in `docker-compose.yml` before the
first start.

To start from scratch and reload the `.sql` files, the volume has to be removed:

```sh
docker compose down -v
docker compose up -d --build
```

Careful: `-v` also removes the `uploads` volume, that is, the files attached to surveys.

### Everyday commands

```sh
docker compose logs -f web     # PHP errors and the Apache log
docker compose exec web bash   # shell inside the container
docker compose restart web     # restart just the application
docker compose down            # stop the containers, keeping the data
```
