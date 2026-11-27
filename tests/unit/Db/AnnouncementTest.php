<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Unit\Db;

use OCA\AnnouncementBanner\Db\Announcement;
use PHPUnit\Framework\TestCase;

class AnnouncementTest extends TestCase {
	public function testTheBannerShapeLeavesOutAdminFields(): void {
		$a = new Announcement();
		$a->setId(9);
		$a->setTitle('Hello');
		$a->setLevel('warning');
		$a->setCreatedBy('admin');
		$a->setStartsAt(100);

		$this->assertSame([
			'id' => 9,
			'title' => 'Hello',
			'message' => '',
			'level' => 'warning',
			'linkUrl' => '',
			'dismissible' => true,
		], $a->toBanner());
		$this->assertSame('admin', $a->jsonSerialize()['createdBy']);
	}

	public function testANullDismissibleColumnMeansDismissible(): void {
		$a = new Announcement();
		$a->setDismissible(null);
		$this->assertTrue($a->isDismissibleByUsers());
		$a->setDismissible(false);
		$this->assertFalse($a->isDismissibleByUsers());
	}

	public function testTheScheduleBoundsAreStartInclusiveEndExclusive(): void {
		$a = new Announcement();
		$a->setStartsAt(100);
		$a->setEndsAt(200);
		$this->assertFalse($a->isActiveAt(99));
		$this->assertTrue($a->isActiveAt(100));
		$this->assertTrue($a->isActiveAt(199));
		$this->assertFalse($a->isActiveAt(200));
	}
}
