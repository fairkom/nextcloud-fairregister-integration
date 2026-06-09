<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\AppInfo;

use OCA\Fairregister\Listener\LoadFilesScriptListener;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {

	public const APP_ID = 'fairregister';

	/**
	 * Only AppConfig the plugin needs: where to send the user after the
	 * share-link is created. No OIDC client config — the plugin does not
	 * authenticate against fairregister directly; the frontend does, on
	 * behalf of the user, using whatever auth it already has.
	 */
	public const CONFIG_FRONTEND_URL = 'frontend_url';

	private ContainerInterface $container;
	private IConfig $config;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
		$this->container = $this->getContainer();
		$this->config = $this->container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadFilesScriptListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(\Closure::fromCallable([$this, 'registerNavigation']));
	}

	private function registerNavigation(INavigationManager $navigationManager, IURLGenerator $urlGenerator, IL10N $l10n): void {
		$frontendUrl = rtrim($this->config->getAppValue(self::APP_ID, self::CONFIG_FRONTEND_URL, ''), '/');
		if ($frontendUrl === '') {
			return;
		}
		$navigationManager->add(static function () use ($frontendUrl, $urlGenerator, $l10n) {
			return [
				'id' => self::APP_ID,
				'order' => 10,
				'href' => $frontendUrl . '/myworks',
				'target' => '_blank',
				'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
				'name' => $l10n->t('fairregister'),
			];
		});
	}
}
