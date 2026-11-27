<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates the two tables of the app:
 *
 * - announcement_banner_ann: what admins publish (title, message, level, optional link,
 *   optional schedule, whether users may dismiss it).
 * - announcement_banner_dis: which user dismissed which announcement, so a dismissed
 *   banner stays hidden for that user only.
 *
 * Every step is guarded with hasTable(), so running it again is harmless.
 */
class Version1000Date20261127135317 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('announcement_banner_ann')) {
			$table = $schema->createTable('announcement_banner_ann');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('message', Types::TEXT, [
				'notnull' => false,
			]);
			// info | success | warning | error: decides the banner colour and icon.
			$table->addColumn('level', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => 'info',
			]);
			$table->addColumn('link_url', Types::STRING, [
				'notnull' => false,
				'length' => 1024,
			]);
			// Unix timestamps; null means "no limit" on that side of the schedule.
			$table->addColumn('starts_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('ends_at', Types::BIGINT, [
				'notnull' => false,
			]);
			// Booleans must be nullable: Oracle cannot store false in a NOT NULL boolean.
			$table->addColumn('dismissible', Types::BOOLEAN, [
				'notnull' => false,
				'default' => true,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			// The banner query filters on the schedule.
			$table->addIndex(['starts_at', 'ends_at'], 'announcement_banner_ann_sched');
			$changed = true;
		}

		if (!$schema->hasTable('announcement_banner_dis')) {
			$table = $schema->createTable('announcement_banner_dis');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('announcement_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('dismissed_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			// One dismissal per user and announcement; the unique index makes a double
			// click (or two tabs) harmless instead of a duplicate row.
			$table->addUniqueIndex(['announcement_id', 'user_id'], 'announcement_banner_dis_au');
			$table->addIndex(['user_id'], 'announcement_banner_dis_uid');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
