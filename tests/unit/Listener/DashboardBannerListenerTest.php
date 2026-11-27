<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Tests\Unit\Listener;

use OCA\AnnouncementBanner\Listener\DashboardBannerListener;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The cases where the listener must stay out of the way. The case where it loads the
 * banner is covered end to end by the Playwright suite (it needs a running server for
 * Util::addScript()).
 */
class DashboardBannerListenerTest extends TestCase {
	private AnnouncementService&MockObject $service;
	private IInitialState&MockObject $initialState;
	private DashboardBannerListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(AnnouncementService::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$this->listener = new DashboardBannerListener($this->service, $this->initialState, $session);
	}

	private function event(bool $loggedIn, string $app, string $template = 'index', string $renderAs = TemplateResponse::RENDER_AS_USER): BeforeTemplateRenderedEvent {
		$response = $this->createMock(TemplateResponse::class);
		$response->method('getApp')->willReturn($app);
		$response->method('getTemplateName')->willReturn($template);
		$response->method('getRenderAs')->willReturn($renderAs);
		return new BeforeTemplateRenderedEvent($loggedIn, $response);
	}

	public function testOtherPagesAreLeftAlone(): void {
		$this->service->expects($this->never())->method('findActiveForUser');
		$this->initialState->expects($this->never())->method('provideInitialState');

		$this->listener->handle($this->event(true, 'files'));
		$this->listener->handle($this->event(true, 'dashboard', 'other'));
		$this->listener->handle($this->event(true, 'dashboard', 'index', TemplateResponse::RENDER_AS_BLANK));
		$this->listener->handle($this->event(false, 'dashboard'));
		$this->listener->handle(new Event());
	}

	public function testNothingIsLoadedWhenThereIsNothingToShow(): void {
		$this->service->expects($this->once())->method('findActiveForUser')->with('alice')->willReturn([]);
		$this->initialState->expects($this->never())->method('provideInitialState');

		$this->listener->handle($this->event(true, 'dashboard'));
	}
}
