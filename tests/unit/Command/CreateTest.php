<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Unit\Command;

use OCA\AnnouncementBanner\Command\Create;
use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateTest extends TestCase {
	public function testParseTime(): void {
		$this->assertNull(Create::parseTime(null));
		$this->assertNull(Create::parseTime(''));
		$this->assertSame(1795773600, Create::parseTime('2026-11-27 10:00 UTC'));
		$this->assertSame(1795773600, Create::parseTime('2026-11-27 11:00 Europe/Berlin'));
	}

	public function testParseTimeRejectsNonsense(): void {
		$this->expectException(ValidationException::class);
		Create::parseTime('not a date');
	}

	public function testOptionsReachTheService(): void {
		$service = $this->createMock(AnnouncementService::class);
		$created = new Announcement();
		$created->setId(12);
		$service->expects($this->once())
			->method('create')
			->with('Updated', 'Details', 'success', 'https://example.com', null, 1795773600, false)
			->willReturn($created);

		$tester = new CommandTester(new Create($service));
		$exit = $tester->execute([
			'title' => 'Updated',
			'--message' => 'Details',
			'--level' => 'success',
			'--link' => 'https://example.com',
			'--ends' => '2026-11-27 10:00 UTC',
			'--not-dismissible' => true,
		]);

		$this->assertSame(0, $exit);
		$this->assertStringContainsString('Created announcement 12', $tester->getDisplay());
	}
}
