<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<table class="file_import__results">
		<caption>
			{{ t('tables', 'Result') }}
		</caption>

		<tbody>
			<tr>
				<td>{{ t('tables', 'Found columns') }}</td>
				<td data-cy="importResultColumnsFound">
					{{ results.found_columns_count }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Matching columns') }}</td>
				<td data-cy="importResultColumnsMatch">
					{{ results.matching_columns_count }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Created columns') }}</td>
				<td data-cy="importResultColumnsCreated">
					{{ results.created_columns_count }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Inserted rows') }}</td>
				<td data-cy="importResultRowsInserted">
					{{ results.inserted_rows_count }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Updated rows') }}</td>
				<td data-cy="importResultRowsUpdated">
					{{ results.updated_rows_count }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Value parsing errors') }}</td>
				<td data-cy="importResultParsingErrors">
					{{ results.errors_parsing_count }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Row creation errors') }}</td>
				<td data-cy="importResultRowErrors">
					{{ results.errors_count }}
				</td>
			</tr>
		</tbody>
	</table>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ImportResults',

	props: {
		results: {
			type: Object,
			default() {
				return {
					found_columns_count: 0,
					matching_columns_count: 0,
					created_columns_count: 0,
					inserted_rows_count: 0,
					errors_parsing_count: 0,
					errors_count: 0,
				}
			},
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.file_import__results {
	margin: auto;
	min-width: 350px;
	max-width: 600px;

	& caption {
		font-weight: bold;
		margin: calc(var(--default-grid-baseline) * 2) auto;
	}
}
</style>
