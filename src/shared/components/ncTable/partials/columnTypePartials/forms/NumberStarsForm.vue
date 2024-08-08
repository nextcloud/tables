<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-2 align-center">
				<NcButton type="tertiary" :aria-label="t('tables', 'Reduce stars')" @click="less">
					<template #icon>
						<Minus :size="20" />
					</template>
				</NcButton>
				<div class="stars">
					{{ getStars }}
				</div>
				<NcButton type="tertiary" :aria-label="t('tables', 'Increase stars')" @click="more">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Minus from 'vue-material-design-icons/Minus.vue'

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'NumberStarsForm',

	components: {
		Plus,
		Minus,
		NcButton,
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
		getStars() {
			return '★'.repeat(this.mutableColumn.numberDefault) + '☆'.repeat(5 - this.mutableColumn.numberDefault)
		},
	},
	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
	methods: {
		t,
		more() {
			if (this.mutableColumn.numberDefault < 5) {
				this.mutableColumn.numberDefault++
			}
		},
		less() {
			if (this.mutableColumn.numberDefault > 0) {
				this.mutableColumn.numberDefault--
			}
		},
	},

}
</script>
<style lang="scss" scoped>

.align-center {
	align-items: center;
	display: inline-flex;
}

.stars {
	font-size: 1.4em;
	padding: 7px;
}

</style>
