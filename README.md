<!--
SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# fairregister — Nextcloud integration

Nextcloud app that registers files from a user's Nextcloud as **Works** in
a [fairregister](https://fairregister.net) instance, proving ownership at
a specific point in time.

## How it works

1. A "Register with fairregister" action appears in the three-dots menu
   of every file in the Files app.
2. One click creates a short-lived single-use download token on the
   Nextcloud side and opens a new tab at
   `https://<fairregister-frontend>/registerwork?fromUrl=…&filename=…`.
3. The fairregister frontend (authenticated against fairlogin by the
   user separately) fetches the file via the token, uploads it to
   fairregister storage, and walks the user through the metadata form.
4. The plugin keeps no local state and needs no OAuth / OIDC client
   setup. Admin only configures one field: the fairregister frontend
   URL (Quick presets for production / development included).

## Repo layout

```
nextcloud_fairregister/
├── fairregister/          ← the NC app, what ends up in custom_apps/
│   ├── appinfo/
│   ├── lib/
│   ├── src/
│   ├── js/                ← built bundles (committed for app store)
│   └── …
├── deploy-to-dev.sh       ← scp + docker cp + occ upgrade
├── create-app-store-release.sh
└── releases/              ← signed tarballs produced by the above
```

Mirrors the layout of `nextcloud_fairmeeting`.

## Development

```bash
cd fairregister
composer install
npm install
npm run build
```

The plugin's source is bind-mounted into the Nextcloud container by the
local stack in the [faircommons](https://git.fairkom.net/hosting/faircommons)
repo, which pulls this repo in as a git submodule under
`faircommons/nextcloud_fairregister/`:

```yaml
# faircommons/deployments/local/docker-compose.oidc.yml
nextcloud:
  volumes:
    - ../../nextcloud_fairregister/fairregister:/var/www/html/custom_apps/fairregister
```

After cloning faircommons, initialize the submodule once:

```bash
git -C path/to/faircommons submodule update --init nextcloud_fairregister
```

PHP edits go live immediately; JS edits need `npm run build` (or
`npm run watch`).

## Deployment to a remote dev / staging NC

```bash
./deploy-to-dev.sh                 # default: SSH_HOST=nx-dev2
SSH_HOST=other.example.org ./deploy-to-dev.sh
```

See [DEPLOY-TO-DEV.md](DEPLOY-TO-DEV.md) for the full guide.

## App Store release

```bash
# 1. bump version in fairregister/appinfo/info.xml
# 2. tag
git tag v0.6.0 && git push --tags

# 3. produce signed tarball
./create-app-store-release.sh

# 4. upload releases/fairregister_v0.6.0.tar.gz + .sig to
#    https://apps.nextcloud.com (first release requires Nextcloud Security
#    team approval; subsequent releases under the same certificate are
#    automatic)
```

## License

AGPL-3.0-or-later. See `fairregister/LICENSES/`.
