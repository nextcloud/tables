<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<div class="row">
			<div class="fix-col-4">
				<NcEditor v-if="!showBigEditorModal" :text.sync="localValue" height="small" />
			</div>
		</div>
		<template #head>
			<NcButton type="tertiary" @click="showBigEditorModal = true">
				<template #icon>
					<Fullscreen :size="20" />
				</template>
			</NcButton>
			<NcModal v-if="showBigEditorModal" :close-button-contained="true" size="full" :title="column.title" @close="showBigEditorModal = false">
				<div class="row">
					<div class="fix-col-4">
						<NcEditor :text.sync="localValue" :show-border="false" />
					</div>
				</div>
				<div class="closeModalButton">
					<NcButton @click="showBigEditorModal = false">
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

export default {
	components: {
		Fullscreen,
		RowFormWrapper,
		NcEditor,
		NcButton,
		NcModal,
	},

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
				return (this.value !== null)
					? this.value
					: ((this.column.textDefault !== undefined)
						? this.column.textDefault
						: '')
			},
			set(v) {
				this.$emit('update:value', v)
			},
		},
	},

}
</script>
