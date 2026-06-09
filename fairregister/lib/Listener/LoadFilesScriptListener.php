<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Fairregister\Listener;

use OCA\Fairregister\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadFilesScriptListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}
		Util::addScript(Application::APP_ID, Application::APP_ID . '-filesAction');
	}
}
