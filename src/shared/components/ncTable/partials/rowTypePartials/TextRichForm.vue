<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<div class="row">
			<div class="fix-col-4">
				<NcEditor v-if="!showBigEditorModal" :can-edit="!column.viewColumnInformation?.readonly" :text.sync="localValue" height="small" />
			</div>
		</div>
		<template #head>
			<NcButton :aria-label="t('tables', 'Show fullscreen')" type="tertiary" @click="showBigEditorModal = true">
				<template #icon>
					<Fullscreen :size="20" />
				</template>
			</NcButton>
			<NcModal v-if="showBigEditorModal" :close-button-contained="false" size="full" :title="column.title" @close="showBigEditorModal = false">
				<div class="row">
					<div class="fix-col-4">
						<NcEditor :text.sync="localValue" :can-edit="!column.viewColumnInformation?.readonly" :show-border="false" />
					</div>
				</div>
				<div class="closeModalButton">
					<NcButton :aria-label="t('tables', 'Close editor')" @click="showBigEditorModal = false">
						{{ t('tables', 'Close editor') }}
					</NcButton>
				</div>
			</NcModal>
		</template>
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import NcEditor from '../../../ncEditor/NcEditor.vue'
import { NcButton, NcModal } from '@nextcloud/vue'
import Fullscreen from 'vue-material-design-icons/Fullscreen.vue'
import { translate as t } from '@nextcloud/l10n'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'

export default {
	components: {
		Fullscreen,
		RowFormWrapper,
		NcEditor,
		NcButton,
		NcModal,
	},

	mixins: [rowHelper],

	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			showBigEditorModal: false,
		}
	},
	computed: {
		localValue: {
			get() {
				if (this.value !== null) return this.value
				const newValue = this.column?.textDefault ? this.column.textDefault : ''
				this.$emit('update:value', newValue)
				return newValue
			},
			set(v) {
				this.$emit('update:value', v)
			},
		},
	},
	methods: {
		t,
	},

}
</script>
<style lang="scss" scoped>

	.closeModalButton {
		position: fixed;
		bottom: calc(var(--default-grid-baseline) * 2);
		inset-inline-end: calc(var(--default-grid-baseline) * 2);
	}

</style>
