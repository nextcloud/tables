<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<div class="row">
			<div class="fix-col-4 end space-B-small">
				<NcButton @click="showBigEditorModal = true">
					<template #icon>
						<Fullscreen :size="20" />
					</template>
					{{ t('tables', 'Show big editor') }}
				</NcButton>
				<NcModal v-if="showBigEditorModal" :close-button-contained="true" size="large" :title="column.title" @close="showBigEditorModal = false">
					<div class="row">
						<div class="fix-col-4 space-T-big">
							<NcEditor :text.sync="localValue" :show-border="false" />
						</div>
						<div class="col-4 end space-R space-B">
							<div class="end">
								<NcButton @click="showBigEditorModal = false">
									{{ t('tables', 'Close editor') }}
								</NcButton>
							</div>
						</div>
					</div>
				</NcModal>
			</div>
			<div class="fix-col-4">
				<NcEditor v-if="!showBigEditorModal" :text.sync="localValue" height="small" />
			</div>
		</div>
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
<style lang="scss" scoped>

	.space-R {
		padding-right: calc(var(--default-grid-baseline) * 2);
	}

	.end {
		float: right;
	}

	.space-T-big {
		padding-top: 40px;
	}

</style>
