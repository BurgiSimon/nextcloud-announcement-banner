<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Command;

use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ announcement_banner:create, so an update or maintenance script can announce
 * itself, e.g.:
 *
 *   occ announcement_banner:create "Nextcloud was updated" --level=success --ends="+7 days"
 *
 * Written against the Symfony Console API that both Nextcloud 34 (6.4) and 35 (7.4) ship.
 */
class Create extends Command {
	public function __construct(
		private AnnouncementService $service,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('announcement_banner:create')
			->setDescription('Publish an announcement as a banner on every user\'s Dashboard')
			->addArgument('title', InputArgument::REQUIRED, 'Headline of the banner')
			->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Text below the headline', '')
			->addOption('level', 'l', InputOption::VALUE_REQUIRED, 'One of: ' . implode(', ', AnnouncementService::LEVELS), 'info')
			->addOption('link', null, InputOption::VALUE_REQUIRED, 'A "Learn more" link: https://... or a path on this server', '')
			->addOption('starts', null, InputOption::VALUE_REQUIRED, 'Show from this moment, e.g. "2026-12-01 22:00 Europe/Berlin" or "tomorrow" (UTC unless a zone is given)')
			->addOption('ends', null, InputOption::VALUE_REQUIRED, 'Hide from this moment, e.g. "+7 days"')
			->addOption('not-dismissible', null, InputOption::VALUE_NONE, 'Users cannot close this banner');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$announcement = $this->service->create(
				(string)$input->getArgument('title'),
				(string)$input->getOption('message'),
				(string)$input->getOption('level'),
				(string)$input->getOption('link'),
				self::parseTime($input->getOption('starts')),
				self::parseTime($input->getOption('ends')),
				!$input->getOption('not-dismissible'),
			);
		} catch (ValidationException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}
		$output->writeln('Created announcement ' . $announcement->getId());
		return 0;
	}

	/**
	 * @throws ValidationException
	 */
	public static function parseTime(mixed $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}
		try {
			return (new \DateTimeImmutable((string)$value))->getTimestamp();
		} catch (\Exception) {
			throw new ValidationException('Cannot read the date "' . (string)$value . '"');
		}
	}
}
