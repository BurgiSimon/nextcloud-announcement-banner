<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Unit\Controller;

use OCA\AnnouncementBanner\Controller\BannerController;
use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BannerControllerTest extends TestCase {
	private AnnouncementService&MockObject $service;
	private BannerController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(AnnouncementService::class);
		$this->controller = new BannerController($this->createMock(IRequest::class), $this->service, 'alice');
	}

	public function testRoutesAreForEveryUserAndKeepCsrfProtection(): void {
		foreach (['index', 'dismiss'] as $method) {
			$reflection = new \ReflectionMethod(BannerController::class, $method);
			$this->assertCount(1, $reflection->getAttributes(NoAdminRequired::class), $method);
			$this->assertSame([], $reflection->getAttributes(NoCSRFRequired::class), $method);
		}
	}

	public function testIndexReturnsTheBannerShapeForTheCurrentUser(): void {
		$a = new Announcement();
		$a->setId(1);
		$a->setTitle('Hello');
		$a->setCreatedBy('admin');
		$this->service->expects($this->once())->method('findActiveForUser')->with('alice')->willReturn([$a]);

		$data = $this->controller->index()->getData();

		$this->assertSame([$a->toBanner()], $data);
		$this->assertArrayNotHasKey('createdBy', $data[0]);
	}

	public function testDismiss(): void {
		$this->service->expects($this->once())->method('dismiss')->with(4, 'alice');
		$response = $this->controller->dismiss(4);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['dismissed' => 4], $response->getData());
	}

	public function testDismissErrors(): void {
		$this->service->method('dismiss')->willReturnCallback(static function (int $id): void {
			throw $id === 1 ? new NotFoundException('gone') : new ValidationException('locked');
		});
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->dismiss(1)->getStatus());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->dismiss(2)->getStatus());
	}
}
