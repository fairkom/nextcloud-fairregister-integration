<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Service;

use OCA\Fairregister\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use RuntimeException;

/**
 * Issues short-lived single-use download tokens for handing a Nextcloud
 * file off to the fairregister frontend. The frontend dereferences the
 * token URL exactly once to fetch the bytes; replays are rejected.
 *
 * No NC public share link is created, so the file does not become
 * browse-able via /s/<token> and does not appear in the user's shares
 * sidebar.
 */
class TransferService {

	private const TTL_SECONDS = 300; // 5 minutes
	private const CACHE_NS = 'fairregister-dl';

	private ICache $cache;

	public function __construct(
		ICacheFactory $cacheFactory,
		private IRootFolder $rootFolder,
		private IURLGenerator $urlGenerator,
		private ISecureRandom $random,
	) {
		$this->cache = $cacheFactory->createDistributed(self::CACHE_NS);
	}

	/**
	 * Create a one-time token bound to (ncUserId, ncFileId).
	 *
	 * @return array{fromUrl: string, filename: string, expiresAt: int}
	 */
	public function createTransfer(string $ncUserId, int $ncFileId): array {
		$file = $this->resolveFile($ncUserId, $ncFileId);
		$token = $this->random->generate(64, ISecureRandom::CHAR_HUMAN_READABLE);
		$expiresAt = time() + self::TTL_SECONDS;

		$this->cache->set($token, json_encode([
			'owner'    => $ncUserId,
			'file_id'  => $ncFileId,
			'filename' => $file->getName(),
			'expires'  => $expiresAt,
		]), self::TTL_SECONDS);

		// linkToRouteAbsolute resolves to https://<nc>/index.php/apps/fairregister/dl/<token>
		$fromUrl = $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.download.stream',
			['token' => $token],
		);

		return [
			'fromUrl'   => $fromUrl,
			'filename'  => $file->getName(),
			'expiresAt' => $expiresAt,
		];
	}

	/**
	 * Atomically consume a token. Returns the file the token references,
	 * or null if the token is unknown, expired or already used.
	 *
	 * Single-use is enforced by `remove`: only one caller can succeed in
	 * removing the cache entry — that caller gets to stream the bytes.
	 */
	public function consume(string $token): ?File {
		$raw = $this->cache->get($token);
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		// Race-safe: whoever removes wins. Subsequent get() returns null.
		if (!$this->cache->remove($token)) {
			return null;
		}
		$data = json_decode($raw, true);
		if (!is_array($data) || !isset($data['owner'], $data['file_id'], $data['expires'])) {
			return null;
		}
		if ((int)$data['expires'] < time()) {
			return null;
		}
		try {
			return $this->resolveFile((string)$data['owner'], (int)$data['file_id']);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function resolveFile(string $ncUserId, int $ncFileId): File {
		$userFolder = $this->rootFolder->getUserFolder($ncUserId);
		$nodes = $userFolder->getById($ncFileId);
		if (empty($nodes)) {
			throw new NotFoundException('NC file not found: ' . $ncFileId);
		}
		$file = $nodes[0];
		if (!$file instanceof File) {
			throw new RuntimeException('Target node is not a file');
		}
		return $file;
	}
}
