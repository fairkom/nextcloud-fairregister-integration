<!--
SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
SPDX-License-Identifier: CC0-1.0
-->

# Deploy the fairregister plugin to a Nextcloud dev instance

The plugin is intentionally minimal: it only needs the URL of the
fairregister frontend. No OIDC client setup, no Keycloak admin work — the
plugin issues one-time download tokens server-side and the frontend
handles all user authentication on its own.

## Prerequisites on the target side

| Thing | Why |
|---|---|
| A Nextcloud instance reachable over the network, ideally via HTTPS | Plugin endpoints are called by the browser; frontend's CORS check requires the configured frontend origin |
| SSH access to the host running the NC docker container | `deploy-to-dev.sh` uses `scp` + `docker cp` |
| Container running Nextcloud (e.g. `nextcloud-aio-nextcloud`) | Used as deploy target |
| Network reachability between user's browser ↔ NC ↔ fairregister frontend ↔ api-service | One-time download is browser-to-NC, then frontend-to-api-service |
| The fairregister stack (`api.dev.fairregister.net`, `dev.fairregister.net`, or prod equivalents) is up | Frontend uploads via api-service, user must be able to log into fairlogin |

## One-shot deployment

```bash
cd "Plugin nextcloud/fairregister"

# Defaults: SSH_HOST=nx-dev2, REMOTE_CONTAINER=nextcloud-aio-nextcloud
./deploy-to-dev.sh

# Override per call:
SSH_HOST=nc-test.example.org \
  REMOTE_CONTAINER=nextcloud-aio-nextcloud \
  ./deploy-to-dev.sh

# Skip composer/npm rebuild when iterating fast and the build/ is fresh:
SKIP_BUILD=1 ./deploy-to-dev.sh
```

What the script does:

1. `composer install --no-dev` + `npm install` + `npm run build` locally
2. Tar the app (excluding `tests/`, `_reference/`, dev assets) →
   `fairregister-deploy-vX.Y.Z.tar.gz`
3. `scp` to `$SSH_HOST:~/`
4. `docker cp` into `$REMOTE_CONTAINER`'s `/var/www/html/custom_apps/`
5. Untar over existing install (`fairregister.bak` keeps the previous
   version), `chown www-data:www-data`
6. `occ upgrade` + `occ app:enable fairregister`

## Post-deploy admin steps

1. Log in to the remote NC as admin
2. Settings → Administration → fairregister
3. Click **Development** preset (or **Production**) → fields fill in
4. Click **Save**

Plugin admin settings have a single required field — `Frontend base URL` —
populated by the preset:

| Preset | Frontend base URL |
|---|---|
| Production | https://fairregister.net |
| Development | https://dev.fairregister.net |

That's it. No OIDC client, no client secret, no audience mapper, no
realm config — the plugin doesn't authenticate to fairregister itself.

## Test as an end user

1. Log in to the NC as any user (`alice` via OIDC if you have user_oidc
   configured, or any local user)
2. Files → right-click a file → **Register with fairregister** (stamp
   icon, in the three-dots menu)
3. A new tab opens at `<frontend>/registerwork?fromUrl=…&filename=…`
4. If you're not already authenticated at fairlogin, the frontend
   redirects to Keycloak; log in with your fairlogin account
5. The "uploaded externally" banner shows your filename and a check
   icon; below it the metadata form
6. Fill in title, type, license, optional fields → submit
7. You should land on the certificate view; the Work appears at
   `<frontend>/myworks`

## CORS

The plugin's download endpoint serves
`Access-Control-Allow-Origin: <configured frontend_url>` for matching
Origin requests only. If you point the plugin at a different frontend
than the one the user opens, the browser will block the fetch — set
both to match.

## Rollback

```bash
# On the remote host (via ssh + docker exec):
docker exec <NC_CONTAINER> bash -c '
  cd /var/www/html/custom_apps &&
  rm -rf fairregister &&
  mv fairregister.bak fairregister &&
  chown -R www-data:www-data fairregister'
docker exec -u www-data <NC_CONTAINER> php /var/www/html/occ upgrade
```

## Troubleshooting

- **Plugin file action does nothing**: open browser DevTools → Network
  → trigger the action → check if `POST /apps/fairregister/works/register`
  returns 201. If 412 with code `frontend_not_configured` → Admin
  Settings missing.
- **Browser shows CORS error on the `/dl/<token>` request**: the plugin's
  `frontend_url` does not match the origin the user opened. Update Admin
  Settings to match.
- **Frontend says "Token invalid / 404" on the `/dl/<token>` fetch**: the
  token already got consumed (single-use) or it's older than 5 min.
  Trigger the file action again to mint a new one.
- **MinIO PUT fails with CORS**: api-service-side issue, not the plugin's.
  In dev, ensure `api.dev.fairregister.net` serves `Access-Control-Allow-
  Origin` for `https://dev.fairregister.net`.
