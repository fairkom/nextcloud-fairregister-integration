<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Bootstrap for the plugin's unit tests. We don't boot Nextcloud — the
// tests target plain classes that don't touch the OCP container. Manual
// PSR-4 autoloading is enough.

spl_autoload_register(static function (string $class): void {
	$prefix = 'OCA\\Fairregister\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}
	$relative = substr($class, strlen($prefix));
	$path = __DIR__ . '/../lib/' . str_replace('\\', '/', $relative) . '.php';
	if (file_exists($path)) {
		require_once $path;
	}
});

if (!class_exists(\OCP\AppFramework\Db\Entity::class, false)) {
	// Stub minimal OCP types the lib classes hint at but our unit tests
	// never construct. Keeps autoload from blowing up.
	require __DIR__ . '/stubs.php';
}
