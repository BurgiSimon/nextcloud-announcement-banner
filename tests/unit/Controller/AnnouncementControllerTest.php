<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Unit\Controller;

use OCA\AnnouncementBanner\Controller\AnnouncementController;
use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCA\AnnouncementBanner\Settings\AdminSettings;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnnouncementControllerTest extends TestCase {
	private AnnouncementService&MockObject $service;
	private AnnouncementController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(AnnouncementService::class);
		$this->controller = new AnnouncementController($this->createMock(IRequest::class), $this->service, 'admin');
	}

	/**
	 * The access rule lives in attributes, so check them: every route is admin-only
	 * (no NoAdminRequired) and open to delegated admins of this app's settings.
	 */
	public function testEveryRouteIsAdminOrDelegatedOnly(): void {
		foreach (['index', 'create', 'update', 'destroy'] as $method) {
			$reflection = new \ReflectionMethod(AnnouncementController::class, $method);
			$this->assertSame([], $reflection->getAttributes(NoAdminRequired::class), $method);
			$authorized = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $authorized, $method);
			$this->assertSame(AdminSettings::class, $authorized[0]->getArguments()['settings'], $method);
		}
	}

	public function testCreateReturns201WithTheCreator(): void {
		$created = new Announcement();
		$created->setTitle('Hi');
		$this->service->expects($this->once())
			->method('create')
			->with('Hi', '', 'info', '', null, 123, true, 'admin')
			->willReturn($created);

		$response = $this->controller->create('Hi', null, 'info', null, null, 123, true);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($created, $response->getData());
	}

	public function testCreateTurnsValidationErrorsInto400(): void {
		$this->service->method('create')->willThrowException(new ValidationException('A title is required'));

		$response = $this->controller->create('');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'A title is required'], $response->getData());
	}

	public function testUpdateOfAMissingAnnouncementIs404(): void {
		$this->service->method('update')->willThrowException(new NotFoundException('Announcement not found'));
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->update(99, 'x')->getStatus());
	}

	public function testDestroyOfAMissingAnnouncementIs404(): void {
		$this->service->method('delete')->willThrowException(new NotFoundException('Announcement not found'));
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->destroy(99)->getStatus());
	}
}
