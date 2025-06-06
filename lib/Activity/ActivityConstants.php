<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

class ActivityConstants {
	public const APP_ID = 'tables';

	/*****
	 * Types can have different Settings for Mail/Notifications.
	 */
	public const TYPE_IMPORT_FINISHED = 'tables_import_finished';

	/*****
	 * Subjects are internal 'types', that get interpreted by our own Provider.
	 */

	/**
	 * Somebody shared a form to a selected user
	 * Needs Params:
	 * "user": The userId of the user who shared.
	 * "formTitle": The hash of the shared form.
	 * "formHash": The hash of the shared form
	 */
	public const SUBJECT_IMPORT_FINISHED = 'import_finished_subject';

	public const MESSAGE_IMPORT_FINISHED = 'import_finished_message';
}
