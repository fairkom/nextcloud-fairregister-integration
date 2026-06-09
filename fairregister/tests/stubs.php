<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Minimal stubs of OCP interfaces touched by the lib classes under test.
// Real Nextcloud is not booted during unit tests.

namespace OCP {
	interface IConfig {
		public function getAppValue(string $app, string $key, string $default = ''): string;
		public function setAppValue(string $app, string $key, mixed $value): void;
		public function getUserValue(string $userId, string $app, string $key, string $default = ''): string;
		public function setUserValue(string $userId, string $app, string $key, mixed $value, ?string $preCondition = null): void;
		public function deleteUserValue(string $userId, string $app, string $key): void;
	}
	interface IRequest {
		public function getHeader(string $name): string;
	}
	interface ISession {
		public function set(string $key, mixed $value): void;
		public function get(string $key): mixed;
		public function remove(string $key): void;
	}
	interface IUserSession {
		public function getUser(): ?object;
	}
	interface IURLGenerator {
		public function linkToRouteAbsolute(string $routeName, array $arguments = []): string;
		public function linkToRoute(string $routeName, array $arguments = []): string;
		public function getAbsoluteURL(string $url): string;
	}
	interface IL10N {
		public function t(string $text, $parameters = []): string;
	}
	interface INavigationManager {
		public function add($entry): void;
	}
}

namespace OCP\Security {
	interface ICrypto {
		public function encrypt(string $message, string $password = ''): string;
		public function decrypt(string $authenticatedCiphertext, string $password = ''): string;
	}
	interface ISecureRandom {
		public const CHAR_ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		public const CHAR_DIGITS = '0123456789';
		public const CHAR_LOWER = 'abcdefghijklmnopqrstuvwxyz';
		public const CHAR_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		public function generate(int $length, string $characters = ''): string;
	}
}

namespace OCP\Http\Client {
	interface IClient {
		public function get(string $uri, array $options = []);
		public function post(string $uri, array $options = []);
		public function put(string $uri, array $options = []);
	}
	interface IClientService {
		public function newClient();
	}
	interface IResponse {
		public function getBody();
		public function getStatusCode();
		public function getHeader(string $name);
	}
}

namespace OCP\AppFramework {
	class App {
		public function __construct(string $appName, array $urlParams = []) {}
		public function getContainer() { return null; }
	}
}

namespace OCP\AppFramework\Bootstrap {
	interface IBootstrap {
		public function register(IRegistrationContext $context): void;
		public function boot(IBootContext $context): void;
	}
	interface IBootContext {
		public function injectFn(\Closure $fn);
	}
	interface IRegistrationContext {
		public function registerEventListener(string $event, string $listener, int $priority = 0): void;
	}
}

namespace OCA\Files\Event {
	class LoadAdditionalScriptsEvent {}
}

namespace OCP\EventDispatcher {
	class Event {}
	interface IEventListener {
		public function handle(Event $event): void;
	}
}

namespace Psr\Container {
	interface ContainerInterface {
		public function get(string $id);
		public function has(string $id): bool;
	}
}
