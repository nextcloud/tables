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
					<button class="primary" @click="actionConfirm(true)">
						{{ t('tables', 'Save') }}
					</button>
					<button class="primary" @click="actionConfirm(false)">
						{{ t('tables', 'Save and new') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import TextLineForm from '../rowTypePartials/TextLineForm.vue'
import TextLongForm from '../rowTypePartials/TextLongForm.vue'
import TextLinkForm from '../rowTypePartials/TextLinkForm.vue'
import NumberForm from '../rowTypePartials/NumberForm.vue'
import NumberStarsForm from '../rowTypePartials/NumberStarsForm.vue'
import NumberProgressForm from '../rowTypePartials/NumberProgressForm.vue'
import SelectionCheckForm from '../rowTypePartials/SelectionCheckForm.vue'
import DatetimeForm from '../rowTypePartials/DatetimeForm.vue'
import DatetimeDateForm from '../rowTypePartials/DatetimeDateForm.vue'
import DatetimeTimeForm from '../rowTypePartials/DatetimeTimeForm.vue'

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
				await this.sendNewRowToBE()
				this.reset()
				if (closeModal) {
					this.$emit('update-rows')
					this.$emit('close')
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
				const res = await axios.post(generateUrl('/apps/tables/row'), { tableId: this.activeTable.id, data })
				if (res.status === 200) {
					showSuccess(t('tables', 'The row was saved.'))
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
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
