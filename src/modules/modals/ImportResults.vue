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
					{{ results.foundColumnsCount }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Matching columns') }}</td>
				<td data-cy="importResultColumnsMatch">
					{{ results.matchingColumnsCount }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Created columns') }}</td>
				<td data-cy="importResultColumnsCreated">
					{{ results.createdColumnsCount }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Inserted rows') }}</td>
				<td data-cy="importResultRowsInserted">
					{{ results.insertedRowsCount }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Updated rows') }}</td>
				<td data-cy="importResultRowsUpdated">
					{{ results.updatedRowsCount }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Value parsing errors') }}</td>
				<td data-cy="importResultParsingErrors">
					{{ results.errorsParsingCount }}
				</td>
			</tr>

			<tr>
				<td>{{ t('tables', 'Row creation errors') }}</td>
				<td data-cy="importResultRowErrors">
					{{ results.errorsCount }}
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
					foundColumnsCount: 0,
					matchingColumnsCount: 0,
					createdColumnsCount: 0,
					insertedRowsCount: 0,
					updatedRowsCount: 0,
					errorsParsingCount: 0,
					errorsCount: 0,
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
