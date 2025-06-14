<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<input v-model="mutableColumn.textDefault">
			</div>
		</div>

		<!-- allowed pattern -->
		<!--
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Allowed pattern (regex)') }}
			</div>
			<div class="fix-col-3 margin-bottom">
				<input v-model="mutableColumn.textAllowedPattern">
			</div>
		</div>
-->

		<!-- max text length -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Maximum text length') }}
			</div>
			<div class="fix-col-4">
				<input v-model="maxLength"
					type="number"
					step="1"
					min="0">
			</div>
		</div>

		<!-- unique value -->
		<div class="row">
			<div class="row space-T">
				<div class="fix-col-4 title">
					{{ t('tables', 'Unique value') }}
				</div>
				<div class="fix-col-4 margin-bottom">
					<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.textUnique" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	components: {
		NcCheckboxRadioSwitch,
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
		}
	},

	computed: {
		maxLength: {
			get() {
				return this.mutableColumn.textMaxLength > 0 ? this.mutableColumn.textMaxLength : null
			},
			set(v) {
				this.mutableColumn.textMaxLength = parseInt(v)
			},
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
