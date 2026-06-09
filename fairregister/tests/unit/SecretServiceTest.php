<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Tests\Unit;

use OCA\Fairregister\AppInfo\Application;
use OCA\Fairregister\Service\SecretService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

final class SecretServiceTest extends TestCase {

	public function testRoundtripUserValueIsEncryptedAndDecrypted(): void {
		$config = $this->createMock(IConfig::class);
		$crypto = $this->createMock(ICrypto::class);

		// Setting a non-empty value MUST go through ICrypto::encrypt.
		$crypto->expects(self::once())
			->method('encrypt')
			->with('secret-token')
			->willReturn('ENC(secret-token)');
		$config->expects(self::once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'access_token', 'ENC(secret-token)');

		$svc = new SecretService($config, $crypto);
		$svc->setEncryptedUserValue('alice', 'access_token', 'secret-token');

		// Reading must reverse the path.
		$config->method('getUserValue')->willReturn('ENC(secret-token)');
		$crypto->expects(self::once())
			->method('decrypt')
			->with('ENC(secret-token)')
			->willReturn('secret-token');
		self::assertSame('secret-token', $svc->getEncryptedUserValue('alice', 'access_token'));
	}

	public function testEmptyUserValueSkipsCrypto(): void {
		$config = $this->createMock(IConfig::class);
		$crypto = $this->createMock(ICrypto::class);
		// Empty input means clear the value — must not invoke encrypt.
		$crypto->expects(self::never())->method('encrypt');
		$config->expects(self::once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'k', '');

		(new SecretService($config, $crypto))->setEncryptedUserValue('alice', 'k', '');
	}

	public function testEmptyStoredAppValueDoesNotCallDecrypt(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');
		$crypto = $this->createMock(ICrypto::class);
		$crypto->expects(self::never())->method('decrypt');

		$svc = new SecretService($config, $crypto);
		self::assertSame('', $svc->getEncryptedAppValue('client_secret'));
	}
}
