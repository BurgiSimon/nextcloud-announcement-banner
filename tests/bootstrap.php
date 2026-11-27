<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// One bootstrap for both suites:
//
// - Inside a Nextcloud installation (the app in apps/, apps-extra/ or custom_apps/, or
//   NEXTCLOUD_ROOT pointing at the server when the app directory is a symlink): boot the
//   server through lib/base.php, so the integration suite can use the real database.
//   lib/base.php exists in a git checkout and in a release tarball alike, unlike the
//   server's tests/ directory.
// - Standalone, after `composer install`: only the OCP interfaces from nextcloud/ocp are
//   available, which is all the unit suite needs.
require_once __DIR__ . '/../vendor/autoload.php';

$serverRoot = getenv('NEXTCLOUD_ROOT') ?: __DIR__ . '/../../..';
if (file_exists($serverRoot . '/lib/base.php')) {
	require_once $serverRoot . '/lib/base.php';
	\OC_App::loadApp('announcement_banner');
}
