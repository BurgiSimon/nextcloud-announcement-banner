<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Dismissal>
 */
class DismissalMapper extends QBMapper {
	public const TABLE = 'announcement_banner_dis';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, Dismissal::class);
	}

	/**
	 * Record that a user closed a banner. Dismissing twice (a double click, two open
	 * tabs) hits the unique index and is treated as success.
	 *
	 * @throws Exception for any database error other than the duplicate
	 */
	public function dismiss(int $announcementId, string $userId, int $now): void {
		$dismissal = new Dismissal();
		$dismissal->setAnnouncementId($announcementId);
		$dismissal->setUserId($userId);
		$dismissal->setDismissedAt($now);
		try {
			$this->insert($dismissal);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}
	}

	public function deleteByAnnouncement(int $announcementId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('announcement_id', $qb->createNamedParameter($announcementId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteByUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}

	/**
	 * How many users dismissed each announcement, for the admin list.
	 *
	 * @return array<int, int> announcement id => number of users
	 */
	public function countPerAnnouncement(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('announcement_id')
			->selectAlias($qb->func()->count('id'), 'dismissals')
			->from($this->getTableName())
			->groupBy('announcement_id');
		$result = $qb->executeQuery();
		$counts = [];
		while ($row = $result->fetch()) {
			$counts[(int)$row['announcement_id']] = (int)$row['dismissals'];
		}
		$result->closeCursor();
		return $counts;
	}
}
