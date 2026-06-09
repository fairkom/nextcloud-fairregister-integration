<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Controller;

use OCA\Fairregister\AppInfo\Application;
use OCA\Fairregister\Service\TransferService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\Response;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class DownloadController extends Controller {

	public function __construct(
		IRequest $request,
		private IConfig $config,
		private TransferService $transferService,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * The plugin endpoint is hit cross-origin by the fairregister frontend
	 * (different host/port). Allow exactly the configured frontend URL as
	 * Access-Control origin — not "*" — so an attacker on some other site
	 * cannot fish tokens out of victim browsers.
	 */
	private function applyCors(Response $resp): Response {
		$frontend = rtrim($this->config->getAppValue(Application::APP_ID, Application::CONFIG_FRONTEND_URL, ''), '/');
		$origin = $this->request->getHeader('Origin');
		if ($frontend !== '' && $origin !== '' && rtrim($origin, '/') === $frontend) {
			$resp->addHeader('Access-Control-Allow-Origin', $origin);
			$resp->addHeader('Vary', 'Origin');
		}
		return $resp;
	}

	/**
	 * One-time download endpoint for the fairregister frontend. The token
	 * was issued by WorkController::register; this handler atomically
	 * consumes it and streams the file bytes. Replays return 404.
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=fairregister_download)
	 */
	public function stream(string $token): Response {
		try {
			$file = $this->transferService->consume($token);
		} catch (Throwable $e) {
			$this->logger->warning('fairregister download error', ['exception' => $e]);
			return $this->applyCors(new NotFoundResponse());
		}
		if ($file === null) {
			$this->logger->info('fairregister download: token unknown/used/expired');
			return $this->applyCors(new NotFoundResponse());
		}

		$stream = $file->fopen('r');
		if ($stream === false) {
			$this->logger->error('fairregister download: cannot open file stream', [
				'file' => $file->getName(),
			]);
			return $this->applyCors(new NotFoundResponse());
		}
		$resp = new StreamResponse($stream);
		$resp->setStatus(Http::STATUS_OK);
		$resp->addHeader('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
		$resp->addHeader('Content-Length', (string)$file->getSize());
		$resp->addHeader('Content-Disposition',
			'attachment; filename="' . addslashes($file->getName()) . '"');
		// Don't let intermediate caches keep this — the URL is single-use anyway.
		$resp->cacheFor(0);
		return $this->applyCors($resp);
	}
}
