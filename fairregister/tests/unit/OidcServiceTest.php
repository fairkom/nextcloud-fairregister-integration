<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Tests\Unit;

use OCA\Fairregister\AppInfo\Application;
use OCA\Fairregister\Exception\NotConnectedException;
use OCA\Fairregister\Service\OidcService;
use OCA\Fairregister\Service\SecretService;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OidcServiceTest extends TestCase {

	private function makeService(IConfig $config, SecretService $secrets, ISecureRandom $random): OidcService {
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->createMock(\OCP\Http\Client\IClient::class));
		return new OidcService($clientService, $config, $secrets, $random, new NullLogger());
	}

	public function testCodeChallengeIsRFC7636Compliant(): void {
		$svc = $this->makeService(
			$this->createMock(IConfig::class),
			$this->createMock(SecretService::class),
			$this->createMock(ISecureRandom::class),
		);
		// RFC 7636 Appendix B test vector
		$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
		$expected = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
		self::assertSame($expected, $svc->codeChallenge($verifier));
	}

	public function testGenerateCodeVerifierLengthAndCharset(): void {
		$random = $this->createMock(ISecureRandom::class);
		$random->expects(self::once())
			->method('generate')
			->with(64, ISecureRandom::CHAR_ALPHANUMERIC)
			->willReturn(str_repeat('A', 64));
		$svc = $this->makeService(
			$this->createMock(IConfig::class),
			$this->createMock(SecretService::class),
			$random,
		);
		self::assertSame(str_repeat('A', 64), $svc->generateCodeVerifier());
	}

	public function testGetFairregisterUserIdThrowsWhenNoTokens(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');
		$secrets = $this->createMock(SecretService::class);
		$secrets->method('getEncryptedUserValue')->willReturn('');

		$svc = $this->makeService($config, $secrets, $this->createMock(ISecureRandom::class));

		$this->expectException(NotConnectedException::class);
		$svc->getFairregisterUserId('alice');
	}

	public function testGetFairregisterUserIdReturnsCachedSub(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')
			->with('alice', Application::APP_ID, Application::USER_FAIRREGISTER_SUB, '')
			->willReturn('ff96-uuid');

		$svc = $this->makeService(
			$config,
			$this->createMock(SecretService::class),
			$this->createMock(ISecureRandom::class),
		);
		self::assertSame('ff96-uuid', $svc->getFairregisterUserId('alice'));
	}

	public function testBuildAuthorizeUrlIncludesPkceAndOfflineAccess(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			[Application::APP_ID, Application::CONFIG_OIDC_ISSUER, '', 'https://kc.example/realms/test'],
			[Application::APP_ID, Application::CONFIG_OIDC_CLIENT_ID, '', 'plugin-client'],
		]);
		$svc = $this->makeService(
			$config,
			$this->createMock(SecretService::class),
			$this->createMock(ISecureRandom::class),
		);
		$url = $svc->buildAuthorizeUrl('https://nc.example/callback', 'STATE123', 'CHALLENGE456');
		self::assertStringContainsString('https://kc.example/realms/test/protocol/openid-connect/auth?', $url);
		self::assertStringContainsString('client_id=plugin-client', $url);
		self::assertStringContainsString('state=STATE123', $url);
		self::assertStringContainsString('code_challenge=CHALLENGE456', $url);
		self::assertStringContainsString('code_challenge_method=S256', $url);
		self::assertStringContainsString('scope=openid+profile+email+offline_access', $url);
	}
}
