<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Controller;

use OCA\Fairregister\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class ConfigController extends Controller {

	public function __construct(
		IRequest $request,
		private IConfig $config,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function setAdminConfig(?string $frontend_url = null): DataResponse {
		if ($frontend_url !== null) {
			$this->config->setAppValue(Application::APP_ID, Application::CONFIG_FRONTEND_URL, rtrim($frontend_url, '/'));
		}
		return new DataResponse(['status' => 'ok']);
	}
}
