<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
	'routes' => [
		// Admin config: just the frontend URL — no OIDC client to configure.
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'POST'],

		// Files file-action target: create a one-time download token for
		// the selected file and return the URL the user should open in the
		// fairregister frontend to finish registering it.
		['name' => 'work#register', 'url' => '/works/register', 'verb' => 'POST'],

		// One-time public download. The frontend dereferences this URL
		// exactly once to fetch the file bytes; replays return 404.
		['name' => 'download#stream', 'url' => '/dl/{token}', 'verb' => 'GET'],
	],
];
