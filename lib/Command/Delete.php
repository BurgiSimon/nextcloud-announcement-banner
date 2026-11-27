<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Command;

use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ announcement_banner:delete <id>
 */
class Delete extends Command {
	public function __construct(
		private AnnouncementService $service,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('announcement_banner:delete')
			->setDescription('Delete an announcement; its banner disappears for everyone')
			->addArgument('id', InputArgument::REQUIRED, 'ID from announcement_banner:list');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$this->service->delete((int)$input->getArgument('id'));
		} catch (NotFoundException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}
		$output->writeln('Deleted announcement ' . (int)$input->getArgument('id'));
		return 0;
	}
}
