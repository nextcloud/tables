<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create row') }}</h2>
				</div>
			</div>
			<div v-for="column in columns" :key="column.id">
				<TextLineForm
					v-if="column.type === 'text' && column.subtype === 'line'"
					:column="column"
					:value.sync="row[column.id]" />
				<TextLongForm
					v-if="column.type === 'text' && column.subtype === 'long'"
					:column="column"
					:value.sync="row[column.id]" />
				<TextLinkForm
					v-if="column.type === 'text' && column.subtype === 'link'"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberForm
					v-if="column.type === 'number' && !column.subtype"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberStarsForm
					v-if="column.type === 'number' && column.subtype === 'stars'"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberProgressForm
					v-if="column.type === 'number' && column.subtype === 'progress'"
					:column="column"
					:value.sync="row[column.id]" />
			</div>
			<div class="fix-col-4">
				<button class="secondary" @click="actionCancel">
					{{ t('tables', 'Cancel') }}
				</button>
				<button class="primary" @click="actionConfirm">
					{{ t('tables', 'Save') }}
				</button>
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

export default {
	name: 'CreateRow',
	components: {
		Modal,
		TextLineForm,
		TextLongForm,
		TextLinkForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
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
		async actionConfirm() {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				console.debug('col', col)
				if (col.mandatory) {
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !(this.row[col.id] && this.row[col.id] !== 0)
					console.debug('after update', mandatoryFieldsEmpty)
				}
			})
			if (!mandatoryFieldsEmpty) {
				console.debug('try to add new row to BE', this.row)
				await this.sendNewRowToBE()
				this.$emit('update-rows')
				this.actionCancel()
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
				await axios.post(generateUrl('/apps/tables/row'), { tableId: this.activeTable.id, data })
				showSuccess(t('tables', 'The row was saved.'))
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column'))
			}
		},
		reset() {
			this.row = {}
		},
	},
}
</script>
