<template>
	<NcModal v-if="showModal" size="large" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create row') }}</h2>
				</div>
			</div>
			<div v-for="column in columns" :key="column.id">
				<TextLineForm v-if="column.type === 'text' && column.subtype === 'line'"
					:column="column"
					:value.sync="row[column.id]" />
				<TextLongForm v-if="column.type === 'text' && column.subtype === 'long'"
					:column="column"
					:value.sync="row[column.id]" />
				<TextLinkForm v-if="column.type === 'text' && column.subtype === 'link'"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberForm v-if="column.type === 'number' && !column.subtype"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberStarsForm v-if="column.type === 'number' && column.subtype === 'stars'"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberProgressForm v-if="column.type === 'number' && column.subtype === 'progress'"
					:column="column"
					:value.sync="row[column.id]" />
				<SelectionCheckForm v-if="column.type === 'selection' && column.subtype === 'check'"
					:column="column"
					:value.sync="row[column.id]" />
				<DatetimeForm v-if="column.type === 'datetime' && !column.subtype"
					:column="column"
					:value.sync="row[column.id]" />
				<DatetimeDateForm v-if="column.type === 'datetime' && column.subtype === 'date'"
					:column="column"
					:value.sync="row[column.id]" />
				<DatetimeTimeForm v-if="column.type === 'datetime' && column.subtype === 'time'"
					:column="column"
					:value.sync="row[column.id]" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-B space-T">
					<button class="secondary" @click="actionCancel">
						{{ t('tables', 'Cancel') }}
					</button>
					<button v-if="!localLoading" class="primary" @click="actionConfirm(true)">
						{{ t('tables', 'Save') }}
					</button>
					<button v-if="!localLoading" class="primary" @click="actionConfirm(false)">
						{{ t('tables', 'Save and new') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import TextLineForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLineForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLongForm.vue'
import TextLinkForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLinkForm.vue'
import NumberForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberForm.vue'
import NumberStarsForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberStarsForm.vue'
import NumberProgressForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberProgressForm.vue'
import SelectionCheckForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionCheckForm.vue'
import DatetimeForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeForm.vue'
import DatetimeDateForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeTimeForm.vue'

export default {
	name: 'CreateRow',
	components: {
		SelectionCheckForm,
		NcModal,
		TextLineForm,
		TextLongForm,
		TextLinkForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		DatetimeForm,
		DatetimeDateForm,
		DatetimeTimeForm,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: null,
		},
	},
	data() {
		return {
			row: {},
			localLoading: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async actionConfirm(closeModal) {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				if (col.mandatory) {
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !(this.row[col.id] && this.row[col.id] !== 0)
				}
			})
			if (!mandatoryFieldsEmpty) {
				this.localLoading = true
				await this.sendNewRowToBE()
				this.localLoading = false
				if (closeModal) {
					this.actionCancel()
				} else {
					showSuccess(t('tables', 'Row successfully created.'))
					this.reset()
				}
			} else {
				showWarning(t('tables', 'Please fill in the mandatory fields.'))
			}
		},
		async sendNewRowToBE() {
			try {
				const data = []
				for (const [key, value] of Object.entries(this.row)) {
					data.push({
						columnId: key,
						value,
					})
				}
				await this.$store.dispatch('insertNewRow', { tableId: this.activeTable.id, data })
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new row'))
			}
		},
		reset() {
			this.row = {}
		},
	},
}
</script>
