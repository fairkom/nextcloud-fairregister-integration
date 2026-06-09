<!--
SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
SPDX-License-Identifier: CC0-1.0
-->

# fairregister Nextcloud App — Implementation Plan

## Goal
Nextcloud app as UI layer for the faircommons `api-service`. From within Nextcloud the user can register a file as a Work in fairregister without leaving NC. The file stays in NC; a second copy is stored in fairregister via the api-service (which uploads to MinIO and persists metadata).

## Current State (as of plan)
Scaffold under `Plugin nextcloud/fairregister/` exists but is broken:
- `FairregisterAPIController::__construct` references `$objectStore` and `$fairregisterAPIService` without declaring them as DI parameters.
- `$this->nextcloudUserId = $this->storage = $storage` — double assignment bug.
- API base URL hardcoded as `http://49.12.218.124:8080`.
- `appinfo/info.xml` pins `min-version=24 max-version=24` (Nextcloud is at 30+).
- `<bugs>http://test.com</bugs>` placeholder.
- No `Service/` directory yet, only `Controller/`.
- Only one method (`sendFile`); no register/list/status/certificate endpoints.

## Backend Surface (faircommons api-service)
Endpoints the plugin will call:

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/works/upload/presigned-url?filename&userId` | obtain presigned PUT URL for MinIO |
| `PUT` | `<presigned URL>` | upload file bytes |
| `POST` | `/works/register` | register Work (metadata + prefix) |
| `GET`  | `/works/{workId}` | fetch Work details |
| `GET`  | `/works/{workId}/certificate` | download registration certificate |
| `GET`  | `/works/{workId}/download` | download original file |
| `GET`  | `/users/{userId}/works` | paged list of user Works |
| `GET`  | `/works/public/filter` | public search (optional) |
| `GET`  | `/works/public/{grid}` | public Work view (optional) |

## Auth Strategy — OIDC

### Happy path
- Nextcloud is configured with the `user_oidc` app pointing at the same Keycloak realm as fairregister.
- User logs into NC via OIDC → plugin reads the OIDC access token from the user_oidc session and forwards it as `Authorization: Bearer …` to api-service.
- Token source: `OCA\UserOidc\Service\TokenService` (public API since user_oidc 4.x). Falls that breaks: listen on `OCP\Authentication\Token\IProvider` to capture token on login.

### Fallback (no user_oidc, or different IdP)
- Plugin runs its own Authorization Code + PKCE flow against the configured Keycloak.
- Routes: `GET /apps/fairregister/oauth/start`, `GET /apps/fairregister/oauth/callback`.
- Access + refresh tokens stored per-user in `oc_preferences` (encrypted via `OCP\Security\ICrypto`).
- Refresh handled transparently in `FairregisterApiClient` before each call.

### Identity mapping
- `user_oidc` defaults to setting NC UID = OIDC `sub`. Verify in deployment; otherwise add a `nc_uid → fairregister_user_id` mapping in settings.

### api-service prerequisite
- api-service must accept Keycloak-issued JWTs (Spring Security `oauth2ResourceServer().jwt()`). If not yet wired, that work is upstream of this plugin.

## Storage Behavior
- The NC file is **not** moved. It stays in the user's NC home folder.
- Plugin streams bytes from NC straight to the presigned MinIO URL (no local temp copy).
- A row in plugin-local table `work_mapping` links NC file → fairregister Work:
  `nc_user_id, nc_file_id, work_id, grid, status, created_at, updated_at`.
- No certificate is auto-downloaded. The app page exposes a "Download certificate" action that hits `/works/{id}/certificate` on demand.

## Triggers
1. **File Action** in Files app: "Register with fairregister" → opens modal (title, description, license, public flag) → submits.
2. **App Page** `/apps/fairregister/`: Vue SPA showing the user's Works (joined from `work_mapping` + `GET /users/{userId}/works`) with status, certificate download, and link to public view.

## Nextcloud Version Target
- `min-version=28 max-version=31` (LTS 28 through current).
- CI matrix builds against 28 / 29 / 30 / 31.
- Compat shims under `lib/Compat/` if any breaking PHP API surfaces.

## Module Layout

```
appinfo/
  info.xml              # version range, navigations, settings, repo links
  routes.php            # plugin HTTP routes
lib/
  AppInfo/Application.php          # DI container registration
  Settings/
    Admin.php                      # api_url, oidc_issuer, client_id, secret, auth_mode
    Personal.php                   # show connection status / re-auth
  Controller/
    PageController.php             # SPA entry
    WorkController.php             # /register, /list, /status/{id}, /certificate/{id}
    LoginController.php            # /oauth/start, /oauth/callback (fallback)
  Service/
    FairregisterApiClient.php      # HTTP wrapper, retry, error mapping, logging
    OidcTokenService.php           # read access_token from user_oidc session
    OAuthFallbackService.php       # PKCE code flow, refresh, encrypted storage
    WorkService.php                # presigned → PUT → register → persist mapping
  Db/
    WorkMapping.php                # entity
    WorkMappingMapper.php          # QBMapper
  BackgroundJob/
    PollWorkStatusJob.php          # TimedJob; updates pending rows
  Listener/
    FileActionListener.php         # registers file action menu entry
  Migration/
    Version000000Date20260519.php  # creates work_mapping table
src/                               # Vue 3 SPA
  App.vue
  components/
    WorkList.vue
    RegisterDialog.vue
    CertificateButton.vue
  store/works.js
  main.js
templates/
  main.php
```

## Register Flow (end-to-end)
1. User clicks "Register with fairregister" on a file.
2. Plugin asks `OidcTokenService` for a token.
   - Token present → continue.
   - Missing → modal "Connect to fairregister" → OAuth popup → callback stores token → continue.
3. Modal collects: title, description, license, public flag.
4. `WorkController::register`:
   1. `GET /works/upload/presigned-url?filename&userId` → `{ prefix, url, filename }`
   2. Open NC file as a stream (`IRootFolder → getById → fopen('r')`); `PUT` directly to presigned URL.
   3. `POST /works/register` with metadata + prefix.
   4. Insert `work_mapping` row with `status=pending`.
5. `PollWorkStatusJob` (interval 60s) polls `GET /works/{id}` for `status=pending` rows until `registered` or `failed`.
6. App page renders the list, joining local mapping with remote data.

## Phases & Estimate

| # | Phase | Output | Est. |
|---|---|---|---|
| 1 | Scaffold fix + version range | sane DI, AppConfig URL, info.xml current, REUSE headers consistent | 0.5d |
| 2 | OIDC token service + settings UI | reads user_oidc access token; admin settings page | 1–2d |
| 3 | OAuth fallback flow | LoginController + PKCE + encrypted token storage + refresh | 1–2d |
| 4 | ApiClient + work_mapping DB | typed HTTP client; migration; mapper | 1d |
| 5 | Register flow + File Action | end-to-end upload + register from Files app | 2d |
| 6 | App page Vue work list | SPA, list, certificate download | 2d |
| 7 | TimedJob status polling | background sync of pending rows | 0.5d |
| 8 | NC version matrix tests + AppStore release | CI green on 28–31; `make appstore` artifact | 1d |

**Total: ~10–12 person-days.**

## Open Questions
- Does `api-service` currently validate Keycloak JWTs? If not, that work is a blocker upstream of this plugin (`WebSecurityConfig` with `oauth2ResourceServer().jwt()` against the realm).
- Is NC UID equal to Keycloak `sub` in production deployments, or is a mapping table needed?
- Public OAuth client with PKCE vs confidential client with secret? Public+PKCE is safer for community NC installs; confidential is fine for fairkom-controlled hosting only.
- Should the file action be wrapped in a single-file flow or also support multi-select / folder registration as a future step?

## Out of Scope (for v0.1)
- Public Works browser inside Nextcloud (covered later via `/works/public/filter`).
- Re-uploading a new version of an already-registered Work.
- Sharing fairregister Works via NC share dialog.
- Quota management (`PUT /minio/{bucket}/quota`) — admin-side, not user-facing.
