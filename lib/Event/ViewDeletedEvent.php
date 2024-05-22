<?php

declare(strict_types=1);

namespace OCA\Tables\Event;

use OCA\Tables\Db\View;
use OCP\EventDispatcher\Event;

final class ViewDeletedEvent extends Event {
	public function __construct(protected View $view) {
		parent::__construct();
	}

	public function getView(): View {
		return $this->view;
	}
}
