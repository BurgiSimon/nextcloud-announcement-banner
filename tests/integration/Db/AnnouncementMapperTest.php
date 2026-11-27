<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Integration\Db;

use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Db\AnnouncementMapper;
use OCA\AnnouncementBanner\Db\DismissalMapper;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Real database, real mappers: proves that the migration, the entities and the queries
 * agree, including the schedule and dismissal filtering the banner depends on. Needs a
 * booted Nextcloud (see tests/bootstrap.php). Everything created is removed again.
 */
#[Group('DB')]
class AnnouncementMapperTest extends TestCase {
	private const NOW = 1_800_000_000;

	private AnnouncementMapper $mapper;
	private DismissalMapper $dismissals;
	/** @var Announcement[] */
	private array $created = [];
	private string $user;

	protected function setUp(): void {
		parent::setUp();
		if (!class_exists(\OC::class, false)) {
			$this->markTestSkipped('needs a Nextcloud server, see tests/bootstrap.php');
		}
		$this->mapper = Server::get(AnnouncementMapper::class);
		$this->dismissals = Server::get(DismissalMapper::class);
		$this->user = 'test-user-' . uniqid();
	}

	protected function tearDown(): void {
		if (!isset($this->mapper)) {
			parent::tearDown();
			return;
		}
		foreach ($this->created as $a) {
			$this->dismissals->deleteByAnnouncement($a->getId());
			$this->mapper->delete($a);
		}
		$this->dismissals->deleteByUser($this->user);
		parent::tearDown();
	}

	private function insert(string $title, ?int $startsAt = null, ?int $endsAt = null, ?bool $dismissible = true): Announcement {
		$a = new Announcement();
		$a->setTitle($title);
		$a->setLevel('warning');
		$a->setStartsAt($startsAt);
		$a->setEndsAt($endsAt);
		$a->setDismissible($dismissible);
		$a->setCreatedAt(self::NOW);
		$a->setUpdatedAt(self::NOW);
		return $this->created[] = $this->mapper->insert($a);
	}

	/**
	 * @param Announcement[] $list
	 * @return string[]
	 */
	private function titles(array $list): array {
		return array_values(array_filter(
			array_map(static fn (Announcement $a): string => $a->getTitle(), $list),
			fn (string $t): bool => str_starts_with($t, $this->user),
		));
	}

	public function testTypesSurviveTheRoundTrip(): void {
		$a = $this->insert($this->user . ' typed', self::NOW, self::NOW + 10, false);

		$found = $this->mapper->find($a->getId());

		$this->assertSame(self::NOW, $found->getStartsAt());
		$this->assertSame(self::NOW + 10, $found->getEndsAt());
		$this->assertFalse($found->getDismissible());
		$this->assertNull($found->getMessage());
		$this->assertSame('warning', $found->getLevel());
	}

	public function testActiveMeansInsideTheScheduleAndNotDismissed(): void {
		$u = $this->user;
		$this->insert("$u always");
		$this->insert("$u started", self::NOW - 10);
		$this->insert("$u starts exactly now", self::NOW);
		$this->insert("$u future", self::NOW + 1);
		$this->insert("$u ended", self::NOW - 100, self::NOW);
		$this->insert("$u ending later", null, self::NOW + 1);
		$dismissed = $this->insert("$u dismissed");

		$this->dismissals->dismiss($dismissed->getId(), $u, self::NOW);
		// A second dismissal hits the unique index and is swallowed.
		$this->dismissals->dismiss($dismissed->getId(), $u, self::NOW);

		$this->assertEqualsCanonicalizing(
			["$u always", "$u started", "$u starts exactly now", "$u ending later"],
			$this->titles($this->mapper->findActiveForUser($u, self::NOW, 100)),
		);
		// Another user still sees the dismissed one.
		$this->assertContains("$u dismissed", $this->titles($this->mapper->findActiveForUser($u . '-other', self::NOW, 100)));
		$this->assertSame(1, $this->dismissals->countPerAnnouncement()[$dismissed->getId()] ?? 0);
	}

	public function testDeletingDismissalsByUserAndByAnnouncement(): void {
		$a = $this->insert($this->user . ' a');
		$this->dismissals->dismiss($a->getId(), $this->user, self::NOW);
		$this->dismissals->dismiss($a->getId(), $this->user . '-other', self::NOW);

		$this->dismissals->deleteByUser($this->user);
		$this->assertSame(1, $this->dismissals->countPerAnnouncement()[$a->getId()] ?? 0);

		$this->dismissals->deleteByAnnouncement($a->getId());
		$this->assertArrayNotHasKey($a->getId(), $this->dismissals->countPerAnnouncement());
	}
}
