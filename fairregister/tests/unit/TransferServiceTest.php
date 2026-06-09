<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Tests\Unit;

use OCA\Fairregister\Service\TransferService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

final class TransferServiceTest extends TestCase {

	private function makeService(ICache $cache, ?File $file = null): TransferService {
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$rootFolder = $this->createMock(IRootFolder::class);
		if ($file !== null) {
			$userFolder = $this->createMock(Folder::class);
			$userFolder->method('getById')->willReturn([$file]);
			$rootFolder->method('getUserFolder')->willReturn($userFolder);
		}
		$urlGen = $this->createMock(IURLGenerator::class);
		$urlGen->method('linkToRouteAbsolute')->willReturn('https://nc.example/index.php/apps/fairregister/dl/TOKEN');
		$random = $this->createMock(ISecureRandom::class);
		$random->method('generate')->willReturn('TOKEN');
		return new TransferService($cacheFactory, $rootFolder, $urlGen, $random);
	}

	public function testCreateTransferPersistsTokenWithFiveMinuteTtl(): void {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('foo.pdf');

		$cache = $this->createMock(ICache::class);
		$cache->expects(self::once())
			->method('set')
			->with(
				'TOKEN',
				self::callback(static function (string $payload): bool {
					$d = json_decode($payload, true);
					return is_array($d)
						&& $d['owner'] === 'alice'
						&& $d['file_id'] === 42
						&& $d['filename'] === 'foo.pdf';
				}),
				300, // 5 min TTL
			);

		$svc = $this->makeService($cache, $file);
		$result = $svc->createTransfer('alice', 42);

		self::assertSame('foo.pdf', $result['filename']);
		self::assertStringContainsString('/dl/', $result['fromUrl']);
		self::assertGreaterThan(time(), $result['expiresAt']);
	}

	public function testConsumeReturnsNullForUnknownToken(): void {
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn(null);

		$svc = $this->makeService($cache);
		self::assertNull($svc->consume('does-not-exist'));
	}

	public function testConsumeIsSingleUseViaAtomicRemove(): void {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('foo.pdf');
		$payload = json_encode([
			'owner' => 'alice',
			'file_id' => 42,
			'filename' => 'foo.pdf',
			'expires' => time() + 100,
		]);
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn($payload);
		// First caller to remove wins
		$cache->method('remove')->willReturnOnConsecutiveCalls(true, false);

		$svc = $this->makeService($cache, $file);
		self::assertInstanceOf(File::class, $svc->consume('TOKEN'));
		self::assertNull($svc->consume('TOKEN'));
	}

	public function testConsumeRejectsExpiredToken(): void {
		$payload = json_encode([
			'owner' => 'alice',
			'file_id' => 42,
			'filename' => 'foo.pdf',
			'expires' => time() - 1,
		]);
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn($payload);
		$cache->method('remove')->willReturn(true);

		$svc = $this->makeService($cache);
		self::assertNull($svc->consume('TOKEN'));
	}
}
