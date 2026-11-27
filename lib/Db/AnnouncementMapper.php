<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Announcement>
 */
class AnnouncementMapper extends QBMapper {
	public const TABLE = 'announcement_banner_ann';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, Announcement::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Announcement {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Every announcement, newest first: what the admin settings list.
	 *
	 * @return Announcement[]
	 */
	public function findAll(int $limit = 200): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * The announcements a user should see right now: inside their schedule and not
	 * dismissed by this user. Newest first.
	 *
	 * @return Announcement[]
	 */
	public function findActiveForUser(string $userId, int $now, int $limit = 10): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('a.*')
			->from($this->getTableName(), 'a')
			// Anti-join: keep only announcements without a dismissal row for this user.
			->leftJoin('a', DismissalMapper::TABLE, 'd', $qb->expr()->andX(
				$qb->expr()->eq('d.announcement_id', 'a.id'),
				$qb->expr()->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			))
			->where($qb->expr()->isNull('d.id'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('a.starts_at'),
				$qb->expr()->lte('a.starts_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)),
			))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('a.ends_at'),
				$qb->expr()->gt('a.ends_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)),
			))
			->orderBy('a.created_at', 'DESC')
			->addOrderBy('a.id', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}
}
