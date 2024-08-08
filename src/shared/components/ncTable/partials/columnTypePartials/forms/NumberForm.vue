<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<div class="fix-col-4 title">
					{{ t('tables', 'Default value') }}
				</div>
				<div class="fix-col-4" :class="{error: defaultValueErrorHint !== ''}">
					<input v-model="mutableColumn.numberDefault" type="number" @input="event => defaultValue = event.target.value">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- decimals -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Decimals') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberDecimals" type="number">
				</div>
			</div>
			<div v-if="defaultValueErrorHint !== ''" class="col-4">
				<NcNoteCard type="warning">
					<p>{{ defaultValueErrorHint }}</p>
				</NcNoteCard>
			</div>
		</div>

		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<!-- min -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Minimum') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberMin" type="number">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- max -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Maximum') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberMax" type="number">
				</div>
			</div>
		</div>

		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<!-- prefix -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Prefix') }}
				</div>
				<div class="fix-col-4 space-B">
					<input v-model="mutableColumn.numberPrefix">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- suffix -->
				<div class="fix-col-4 title" style="display: flex;">
					{{ t('tables', 'Suffix') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberSuffix">
				</div>
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

export default {
	name: 'NumberForm',

	components: {
		NcNoteCard,
	},

	props: {
		column: {
			type: Object,
			default: null,
		},
		canSave: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			mutableColumn: this.column,
			defaultValue: null,
		}
	},

	computed: {
		defaultValueErrorHint() {
			if (this.defaultValue === null || this.defaultValue === '') {
				return ''
			}

			if (this.mutableColumn.numberMin !== null && this.mutableColumn.numberMin !== '' && this.defaultValue < this.mutableColumn.numberMin) {
				return t('tables', 'The default value is lower than the minimum allowed value.')
			}

			if (this.mutableColumn.numberMax !== null && this.mutableColumn.numberMax !== '' && this.defaultValue > this.mutableColumn.numberMax) {
				return t('tables', 'The default value is greater than the maximum allowed value.')
			}

			return ''
		},
	},

	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
	methods: {
		t,
	},
}
</script>
