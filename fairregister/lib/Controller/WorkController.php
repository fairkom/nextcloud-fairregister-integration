<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Controller;

use OCA\Fairregister\AppInfo\Application;
use OCA\Fairregister\Service\TransferService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class WorkController extends Controller {

	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
		private TransferService $transferService,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create a short-lived public-link share for the NC file and return the
	 * URL the user should open in the fairregister frontend to finish
	 * registering it. The frontend fetches the bytes from that share URL,
	 * uploads them with its own credentials and walks the user through the
	 * metadata form.
	 *
	 * @NoAdminRequired
	 */
	public function register(int $ncFileId): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		$frontendUrl = rtrim($this->config->getAppValue(Application::APP_ID, Application::CONFIG_FRONTEND_URL, ''), '/');
		if ($frontendUrl === '') {
			return new DataResponse(
				['error' => 'frontend_url not configured', 'code' => 'frontend_not_configured'],
				Http::STATUS_PRECONDITION_FAILED,
			);
		}

		try {
			$transfer = $this->transferService->createTransfer($user->getUID(), $ncFileId);
		} catch (Throwable $e) {
			$this->logger->error('transfer-token creation failed', ['exception' => $e]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$continueUrl = $frontendUrl . '/registerwork?' . http_build_query([
			'fromUrl'  => $transfer['fromUrl'],
			'filename' => $transfer['filename'],
		]);

		return new DataResponse(['continueUrl' => $continueUrl], Http::STATUS_CREATED);
	}
}
