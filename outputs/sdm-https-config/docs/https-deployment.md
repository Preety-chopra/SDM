# SDM HTTPS on port 5252

Target URL: `https://sdm.dmrc.org:5252`.

This opt-in configuration changes only the SDM Compose project. It does not edit,
restart, or reload `mch_nginx`, the MCH Compose project, or MCH certificate files.
SDM briefly stops while its existing HTTP port 5252 is replaced with HTTPS.

The new `https` service shares the SDM application's network namespace. Nginx
listens on container port 443 and proxies to PHP on `127.0.0.1:80`. Only TLS is
published on host port 5252; the HTTP backend and Vite port are not published.
Laravel trusts forwarded headers only from this loopback proxy. The proxy sets
the external HTTPS scheme, hostname, port, and client IP itself.

## Prerequisites

- Docker Compose 2.24.4 or later (`!override` replaces the existing port mappings).
- SDM is already running from `/home/administrator/SDM/SDM/compose.yaml`.
- DNS `sdm.dmrc.org` points to the same server IP used to reach MCH. Add this DNS
  record without changing the existing `mch.dmrc.org` record. If clients use IPv6,
  its AAAA record must also point to the correct server.
- Client access to TCP port 5252 is allowed by the existing network/firewall.
- `/etc/nginx/ssl/mch/fullchain.pem` contains `dmrc.crt` followed by
  `dmrcDigiCertCA.crt`, and `/etc/nginx/ssl/mch/dmrc.key` contains the matching
  private key. These are the separate host files prepared for SDM earlier;
  despite the directory name, they are not mounted by the existing MCH proxy.
- Keep private keys outside the project and never upload them to Git.

## 1. Back up the current SDM settings

Run on the Linux server in one SSH session:

```bash
cd /home/administrator/SDM/SDM
sudo docker compose version

backup_dir="$HOME/sdm-before-https-$(date +%Y%m%d-%H%M%S)"
mkdir -m 700 "$backup_dir"
cp .env "$backup_dir/env"
chmod 600 "$backup_dir/env"
cp bootstrap/app.php "$backup_dir/bootstrap-app.php"

sudo docker inspect mch_nginx --format '{{.Id}} {{.State.StartedAt}}'
curl -I https://mch.dmrc.org
```

Save the backup directory path and MCH inspection output. The `.env` backup
contains secrets; keep it private. If the HTTPS override or Nginx configuration
already exists, back it up before replacing it too.

## 2. Upload the configuration files

Using WinSCP, copy these files from the prepared Windows checkout into the same
relative paths under `/home/administrator/SDM/SDM`:

```text
compose.https.yaml
docker/nginx/sdm-https.conf
config/trustedproxy.php
bootstrap/app.php
```

Create `docker/nginx` if necessary. Alternatively, upload the provided deployment
ZIP to your home directory, then extract it from the project directory:

```bash
unzip -o "$HOME/sdm-https-config.zip" -d /home/administrator/SDM/SDM
```

The ZIP contains configuration and this guide, without certificates, keys,
credentials, or `.env`. It intentionally updates `bootstrap/app.php`; the supplied
server version matched the checkout when this package was prepared. Reconcile
any newer server changes before replacing that file.

Verify the separate SDM certificate files exist without displaying their contents:

```bash
sudo ls -l /etc/nginx/ssl/mch/fullchain.pem /etc/nginx/ssl/mch/dmrc.key
```

If they have not yet been installed, use the files already uploaded to your home
directory:

```bash
chmod 600 "$HOME/ssl-upload/dmrc key"
sudo mkdir -p /etc/nginx/ssl/mch
sudo install -o root -g root -m 600 "$HOME/ssl-upload/dmrc key" /etc/nginx/ssl/mch/dmrc.key
cat "$HOME/ssl-upload/dmrc.crt" "$HOME/ssl-upload/dmrcDigiCertCA.crt" | sudo tee /etc/nginx/ssl/mch/fullchain.pem >/dev/null
sudo chmod 644 /etc/nginx/ssl/mch/fullchain.pem
```

The provided certificate expires at 2027-02-28 23:59:59 UTC (1 March 2027,
05:29:59 IST). Renew the SDM files before that time.

## 3. Update only SDM's environment

Edit the existing project `.env` using `nano .env`. Update existing entries or add
missing entries so each key appears once. Keep the existing application key,
database credentials, session driver, and other settings.

```dotenv
APP_URL=https://sdm.dmrc.org:5252
COMPOSE_FILE=compose.yaml:compose.https.yaml
SSL_CERTIFICATE_PATH=/etc/nginx/ssl/mch/fullchain.pem
SSL_PRIVATE_KEY_PATH=/etc/nginx/ssl/mch/dmrc.key
TRUSTED_PROXIES=127.0.0.1
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
SESSION_COOKIE=sdm_session
```

`SESSION_DOMAIN=null` creates cookies for SDM's hostname only. Do not set it to
`.dmrc.org`. The Compose file also injects the trusted loopback address into the
PHP container so proxy trust survives configuration caching. `COMPOSE_FILE`
ensures future Compose commands in this project retain the HTTPS override.
The colon separator here is for the Linux server.

Keep `APP_PORT` at its existing value for rollback; the HTTPS override replaces
the original HTTP mapping. This configuration is specifically for external port
5252; changing that port also requires updating the Nginx forwarded headers and
`APP_URL`.

## 4. Validate before changing the running SDM containers

Run from the SDM project directory:

```bash
sudo docker compose config --quiet
sudo docker compose pull https
sudo docker compose run --rm --no-deps https nginx -t
```

The one-off Nginx command validates the certificates and configuration, without
opening a listener or changing either running application. If any check fails,
resolve it before continuing. Do not restart the Docker daemon, run `compose down`,
or issue commands from the MCH project.

Build the deployed assets and clear SDM's configuration cache using the existing
PHP container:

```bash
sudo docker compose exec laravel.test npm run build
sudo docker compose exec laravel.test php artisan config:clear
```

If `public/hot` exists, move it into the backup directory so Blade uses the built
assets rather than the HTTP Vite development server:

```bash
if [ -f public/hot ]; then
    mv public/hot "$backup_dir/public-hot"
fi
```

## 5. Switch only SDM to HTTPS

```bash
sudo docker compose up -d --no-deps --force-recreate laravel.test https
sudo docker compose exec laravel.test php artisan config:cache
sudo docker compose ps
```

These commands recreate only SDM's PHP container and its new HTTPS proxy. They
reuse existing SDM data volumes, leave MySQL/Redis running, and do not touch MCH.
SDM's existing sessions may require users to sign in again after the cookie name
or hostname changes.

## 6. Verify both applications

Check the certificate and application locally on the server, even before DNS
propagation. Do not use `-k`; the checks should validate certificate trust:

```bash
curl --resolve sdm.dmrc.org:5252:127.0.0.1 -I https://sdm.dmrc.org:5252/login
curl --resolve sdm.dmrc.org:5252:127.0.0.1 -I https://sdm.dmrc.org:5252/dashboard
```

Expect a successful login page and a dashboard redirect to
`https://sdm.dmrc.org:5252/login`. A response such as 502 means the proxy is up but
PHP is not ready; inspect the SDM logs:

```bash
sudo docker compose logs --tail=80 https laravel.test
```

Confirm MCH's container ID/start time still match the values recorded earlier,
and its existing HTTPS page still responds:

```bash
sudo docker inspect mch_nginx --format '{{.Id}} {{.State.StartedAt}}'
curl -I https://mch.dmrc.org
```

Then open `https://sdm.dmrc.org:5252` from the client browser. Verify sign-in,
navigation, styles, forms, and uploads. HTTPS port 5252 accepts TLS only; the old
`http://...:5252` URL will no longer serve the app. Use the HTTPS hostname URL,
not an IP address, because the certificate identifies `*.dmrc.org`.

For later SDM application container recreation, include both `laravel.test` and
`https` in the Compose command, as above, so the proxy joins the new application
network namespace. For certificate renewal, update the separate SDM files and
recreate only the `https` service so file bind mounts see the renewed files.

## SDM-only rollback

Use the same SSH session and backup directory variable from step 1:

```bash
cd /home/administrator/SDM/SDM
sudo docker compose stop https
sudo docker compose rm -f https
cp "$backup_dir/env" .env
sudo docker compose -f compose.yaml exec laravel.test php artisan config:clear
sudo docker compose -f compose.yaml up -d --no-deps --force-recreate laravel.test
```

This restores SDM's original HTTP port mapping and environment. If you opened a
new terminal, set `backup_dir` to the saved backup directory first. If you need to
restore the original PHP bootstrap, copy `$backup_dir/bootstrap-app.php` back to
`bootstrap/app.php` too. Do not restore `public/hot` unless intentionally running
the Vite development server. MCH is not involved in rollback.
