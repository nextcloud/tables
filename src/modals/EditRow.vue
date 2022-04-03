<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit row') }}</h2>
				</div>
			</div>
			<div v-for="column in columns" :key="column.id">
				<TextLineForm
					v-if="column.type === 'text' && column.subtype === 'line'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<TextLongForm
					v-if="column.type === 'text' && column.subtype === 'long'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<TextLinkForm
					v-if="column.type === 'text' && column.subtype === 'link'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<NumberForm
					v-if="column.type === 'number' && !column.subtype"
					:column="column"
					:value.sync="localRow[column.id]" />
				<NumberStarsForm
					v-if="column.type === 'number' && column.subtype === 'stars'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<NumberProgressForm
					v-if="column.type === 'number' && column.subtype === 'progress'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<SelectionCheckForm
					v-if="column.type === 'selection' && column.subtype === 'check'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<DatetimeForm
					v-if="column.type === 'datetime' && !column.subtype"
					:column="column"
					:value.sync="localRow[column.id]" />
				<DatetimeDateForm
					v-if="column.type === 'datetime' && column.subtype === 'date'"
					:column="column"
					:value.sync="localRow[column.id]" />
				<DatetimeTimeForm
					v-if="column.type === 'datetime' && column.subtype === 'time'"
					:column="column"
					:value.sync="localRow[column.id]" />
			</div>
			<div class="row">
				<div class="fix-col-3 space-B">
					<button class="secondary" @click="actionCancel">
						{{ t('tables', 'Cancel') }}
					</button>
					<button class="primary" @click="actionConfirm">
						{{ t('tables', 'Save') }}
					</button>
				</div>
				<div class="fix-col-1" style="justify-content: end;">
					<button v-if="!prepareDeleteRow" class="error" @click="prepareDeleteRow = true">
						{{ t('tables', 'Delete') }}
					</button>
					<button v-if="prepareDeleteRow" class="error" @click="actionDeleteRow">
						{{ t('tables', 'I really want to delete this row!') }}
					</button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import TextLineForm from '../rowTypePartials/TextLineForm'
import TextLongForm from '../rowTypePartials/TextLongForm'
import TextLinkForm from '../rowTypePartials/TextLinkForm'
import NumberForm from '../rowTypePartials/NumberForm'
import NumberStarsForm from '../rowTypePartials/NumberStarsForm'
import NumberProgressForm from '../rowTypePartials/NumberProgressForm'
import SelectionCheckForm from '../rowTypePartials/SelectionCheckForm'
import DatetimeForm from '../rowTypePartials/DatetimeForm'
import DatetimeDateForm from '../rowTypePartials/DatetimeDateForm'
import DatetimeTimeForm from '../rowTypePartials/DatetimeTimeForm'

export default {
	name: 'EditRow',
	components: {
		SelectionCheckForm,
		Modal,
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
		row: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			localRow: {},
			prepareDeleteRow: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	watch: {
		row() {
			this.loadValues()
		},
	},
	methods: {
		loadValues() {
			if (this.row) {
				const tmp = {}
				this.row.data.forEach(item => {
					tmp[item.columnId] = item.value
				})
				this.localRow = Object.assign({}, tmp)
			}
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async actionConfirm() {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				// console.debug('col', col)
				if (col.mandatory) {
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !(this.localRow[col.id] && this.localRow[col.id] !== 0)
					// console.debug('after update', mandatoryFieldsEmpty)
				}
			})
			if (!mandatoryFieldsEmpty) {
				await this.sendRowToBE()
				this.$emit('update-rows')
				this.actionCancel()
			} else {
				showWarning(t('tables', 'Please fill in the mandatory fields.'))
			}
		},
		async sendRowToBE() {
			try {
				const data = []
				for (const [key, value] of Object.entries(this.localRow)) {
					data.push({
						columnId: key,
						value,
					})
				}
				const response = await axios.put(generateUrl('/apps/tables/row/' + this.row.id), { data })
				if (response.status === 200) {
					showSuccess(t('tables', 'The row was saved.'))
				} else {
					showWarning(t('tables', 'Something did not work as expected.'))
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column'))
			}
		},
		reset() {
			this.localRow = {}
			this.dataLoaded = false
			this.prepareDeleteRow = false
		},
		actionDeleteRow() {
			this.deleteRowAtBE(this.row.id)
		},
		async deleteRowAtBE(rowId) {
			try {
				const res = await axios.delete(generateUrl('/apps/tables/row/' + rowId))
				if (res.status === 200) {
					showSuccess(t('tables', 'Row deleted.'))
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not delete row.'))
			}
			this.$emit('update-rows')
		},
	},
}
</script>
