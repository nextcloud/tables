<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
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

		<template v-if="problems.length">
			<p class="file_import__problems-title">
				{{ t('tables', 'Problems') }}
			</p>

			<div class="file_import__problems-wrapper">
				<table class="file_import__problems" data-cy="importResultProblems">
					<thead>
						<tr>
							<th class="file_import__problems-row">
								{{ t('tables', 'Row') }}
							</th>
							<th class="file_import__problems-column">
								{{ t('tables', 'Column') }}
							</th>
							<th>{{ t('tables', 'Problem') }}</th>
						</tr>
					</thead>

					<tbody>
						<tr v-for="(problem, index) in problems" :key="index" data-cy="importResultProblem">
							<td class="file_import__problems-row">
								{{ problem.row ?? '–' }}
							</td>
							<td class="file_import__problems-column">
								{{ problem.column ?? '–' }}
							</td>
							<td>{{ problem.description ?? '–' }}</td>
						</tr>
					</tbody>
				</table>
			</div>

		</template>
	</div>
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
					problems: [],
				}
			},
		},
	},

	computed: {
		problems() {
			return this.results?.problems ?? []
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

.file_import__problems-title {
	font-weight: bold;
	margin: calc(var(--default-grid-baseline) * 4) auto calc(var(--default-grid-baseline) * 2);
	max-width: 600px;
}

.file_import__problems-wrapper {
	max-height: 300px;
	overflow-y: auto;
	max-width: 600px;
	margin: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.file_import__problems {
	border-collapse: collapse;
	width: 100%;

	& th, & td {
		padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
		text-align: start;
		vertical-align: top;
	}

	& th {
		position: sticky;
		top: 0;
		background-color: var(--color-main-background);
		color: var(--color-text-maxcontrast);
		font-weight: normal;
		box-shadow: inset 0 -1px 0 var(--color-border);
	}

	& tbody tr + tr td {
		box-shadow: inset 0 1px 0 var(--color-border);
	}

	& td.file_import__problems-row {
		width: 1%;
		white-space: nowrap;
		text-align: end;
		font-variant-numeric: tabular-nums;
	}

	& td.file_import__problems-column {
		font-weight: 600;
		font-size: 1.05em;
		white-space: nowrap;
	}
}
</style>
