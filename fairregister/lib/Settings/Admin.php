<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Settings;

use OCA\Fairregister\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	private const PROD_FRONTEND_URL = 'https://fairregister.net';
	private const DEV_FRONTEND_URL  = 'https://dev.fairregister.net';

	public function __construct(
		private IConfig $config,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$state = [
			'frontend_url' => $this->config->getAppValue(
				Application::APP_ID,
				Application::CONFIG_FRONTEND_URL,
				self::PROD_FRONTEND_URL,
			),
			'presets' => [
				'prod' => self::PROD_FRONTEND_URL,
				'dev'  => self::DEV_FRONTEND_URL,
			],
		];
		$this->initialState->provideInitialState('admin-config', $state);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
