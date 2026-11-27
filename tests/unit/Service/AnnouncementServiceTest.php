<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Unit\Service;

use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Db\AnnouncementMapper;
use OCA\AnnouncementBanner\Db\DismissalMapper;
use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnnouncementServiceTest extends TestCase {
	private const NOW = 1_800_000_000;

	private AnnouncementMapper&MockObject $mapper;
	private DismissalMapper&MockObject $dismissals;
	private AnnouncementService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(AnnouncementMapper::class);
		$this->dismissals = $this->createMock(DismissalMapper::class);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(self::NOW);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $p = []): string => vsprintf($text, $p));
		$this->service = new AnnouncementService($this->mapper, $this->dismissals, $time, $l);
	}

	public function testCreateNormalisesAndStores(): void {
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$a = $this->service->create('  Updated  ', "Line one\r\nLine two\n", 'success', ' https://example.com/notes ', null, self::NOW + 60, false, 'admin');

		$this->assertSame('Updated', $a->getTitle());
		$this->assertSame("Line one\nLine two", $a->getMessage());
		$this->assertSame('success', $a->getLevel());
		$this->assertSame('https://example.com/notes', $a->getLinkUrl());
		$this->assertNull($a->getStartsAt());
		$this->assertSame(self::NOW + 60, $a->getEndsAt());
		$this->assertFalse($a->getDismissible());
		$this->assertSame('admin', $a->getCreatedBy());
		$this->assertSame(self::NOW, $a->getCreatedAt());
		$this->assertSame(self::NOW, $a->getUpdatedAt());
	}

	public function testEmptyOptionalFieldsAreStoredAsNull(): void {
		$this->mapper->method('insert')->willReturnArgument(0);

		$a = $this->service->create('Title', '   ', 'info', '');

		$this->assertNull($a->getMessage());
		$this->assertNull($a->getLinkUrl());
		$this->assertTrue($a->getDismissible());
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function invalidInput(): array {
		return [
			'empty title' => [['title' => '  '], 'A title is required'],
			'long title' => [['title' => str_repeat('x', 256)], 'The title must not be longer than 255 characters'],
			'long message' => [['message' => str_repeat('x', 4001)], 'The message must not be longer than 4000 characters'],
			'unknown level' => [['level' => 'party'], 'Unknown type "party"'],
			'javascript link' => [['linkUrl' => 'javascript:alert(1)'], 'The link must be'],
			'data link' => [['linkUrl' => 'data:text/html,<b>x</b>'], 'The link must be'],
			'protocol-relative link' => [['linkUrl' => '//evil.example/'], 'The link must be'],
			'backslash path' => [['linkUrl' => '/\\evil.example/'], 'The link must be'],
			'ftp link' => [['linkUrl' => 'ftp://example.com/'], 'The link must be'],
			'link with spaces' => [['linkUrl' => 'https://example.com/a b'], 'The link must be'],
			'negative start' => [['startsAt' => -1], 'Invalid date'],
			'end before start' => [['startsAt' => 200, 'endsAt' => 100], 'The end must be after the start'],
			'end equals start' => [['startsAt' => 200, 'endsAt' => 200], 'The end must be after the start'],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	#[DataProvider('invalidInput')]
	public function testCreateRejectsInvalidInput(array $input, string $expected): void {
		$input += ['title' => 'Title', 'message' => '', 'level' => 'info', 'linkUrl' => '', 'startsAt' => null, 'endsAt' => null];
		$this->mapper->expects($this->never())->method('insert');
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage($expected);

		$this->service->create($input['title'], $input['message'], $input['level'], $input['linkUrl'], $input['startsAt'], $input['endsAt']);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function safeLinks(): array {
		return [
			'https' => ['https://nextcloud.com/changelog/'],
			'http' => ['http://intranet.example/news'],
			'path on this server' => ['/index.php/apps/files/'],
		];
	}

	#[DataProvider('safeLinks')]
	public function testCreateAcceptsSafeLinks(string $link): void {
		$this->mapper->method('insert')->willReturnArgument(0);
		$this->assertSame($link, $this->service->create('Title', '', 'info', $link)->getLinkUrl());
	}

	public function testUpdateKeepsCreationData(): void {
		$existing = new Announcement();
		$existing->setTitle('Old');
		$existing->setCreatedBy('admin');
		$existing->setCreatedAt(100);
		$existing->setUpdatedAt(100);
		$this->mapper->method('find')->with(7)->willReturn($existing);
		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);

		$a = $this->service->update(7, 'New', '', 'error');

		$this->assertSame('New', $a->getTitle());
		$this->assertSame('error', $a->getLevel());
		$this->assertSame('admin', $a->getCreatedBy());
		$this->assertSame(100, $a->getCreatedAt());
		$this->assertSame(self::NOW, $a->getUpdatedAt());
	}

	public function testUpdateOfAMissingAnnouncementIsNotFound(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException('gone'));
		$this->expectException(NotFoundException::class);
		$this->service->update(404, 'Title');
	}

	public function testDeleteRemovesTheDismissalsToo(): void {
		$a = new Announcement();
		$this->mapper->method('find')->with(3)->willReturn($a);
		$this->dismissals->expects($this->once())->method('deleteByAnnouncement')->with(3);
		$this->mapper->expects($this->once())->method('delete')->with($a)->willReturn($a);

		$this->service->delete(3);
	}

	public function testDismissRecordsTheUser(): void {
		$a = new Announcement();
		$a->setDismissible(true);
		$this->mapper->method('find')->with(5)->willReturn($a);
		$this->dismissals->expects($this->once())->method('dismiss')->with(5, 'alice', self::NOW);

		$this->service->dismiss(5, 'alice');
	}

	public function testANonDismissibleAnnouncementCannotBeDismissed(): void {
		$a = new Announcement();
		$a->setDismissible(false);
		$this->mapper->method('find')->willReturn($a);
		$this->dismissals->expects($this->never())->method('dismiss');
		$this->expectException(ValidationException::class);

		$this->service->dismiss(5, 'alice');
	}

	public function testStatusFollowsTheSchedule(): void {
		$a = new Announcement();
		$this->assertSame('active', $this->service->statusOf($a, self::NOW));

		$a->setStartsAt(self::NOW + 1);
		$this->assertSame('scheduled', $this->service->statusOf($a, self::NOW));

		$a->setStartsAt(self::NOW - 10);
		$a->setEndsAt(self::NOW);
		$this->assertSame('expired', $this->service->statusOf($a, self::NOW));

		$a->setEndsAt(self::NOW + 1);
		$this->assertSame('active', $this->service->statusOf($a, self::NOW));
	}

	public function testAdminListAddsStatusAndDismissalCounts(): void {
		$a = new Announcement();
		$a->setId(1);
		$a->setTitle('Shown');
		$b = new Announcement();
		$b->setId(2);
		$b->setTitle('Later');
		$b->setStartsAt(self::NOW + 100);
		$this->mapper->method('findAll')->willReturn([$a, $b]);
		$this->dismissals->method('countPerAnnouncement')->willReturn([1 => 4]);

		$list = $this->service->listForAdmin();

		$this->assertSame(['active', 4], [$list[0]['status'], $list[0]['dismissals']]);
		$this->assertSame(['scheduled', 0], [$list[1]['status'], $list[1]['dismissals']]);
	}
}
