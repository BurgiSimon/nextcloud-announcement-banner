<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Command;

use OCA\AnnouncementBanner\Service\AnnouncementService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ announcement_banner:list [--output=json]
 */
class ListAnnouncements extends Command {
	public function __construct(
		private AnnouncementService $service,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('announcement_banner:list')
			->setDescription('List all announcements with their status')
			->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output format: table or json', 'table');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$list = $this->service->listForAdmin();
		if ($input->getOption('output') === 'json') {
			$output->writeln(json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
			return 0;
		}

		$format = static fn (mixed $t): string => is_int($t) ? gmdate('Y-m-d H:i', $t) . ' UTC' : '-';
		$table = new Table($output);
		$table->setHeaders(['ID', 'Status', 'Type', 'Title', 'Starts', 'Ends', 'Dismissible', 'Dismissed by']);
		foreach ($list as $a) {
			$table->addRow([
				$a['id'], $a['status'], $a['level'], $a['title'],
				$format($a['startsAt']), $format($a['endsAt']),
				$a['dismissible'] ? 'yes' : 'no', $a['dismissals'],
			]);
		}
		$table->render();
		return 0;
	}
}
