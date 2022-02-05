<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div v-if="loading" class="icon-loading" />

			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit columns') }}</h2>
				</div>
			</div>

			<div v-for="column in columns"
				:key="column.id"
				:class="{ editRow: editColumn && editColumn.id === column.id }"
				style="margin-bottom: 25px;">
				<!-- edit mode -->
				<div v-if="editColumn && editColumn.id === column.id" class="row">
					<div class="col-2 margin-bottom">
						<MainForm :description.sync="editColumn.description"
							:mandatory.sync="editColumn.mandatory"
							:order-weight.sync="editColumn.orderWeight"
							:prefix.sync="editColumn.prefix"
							:suffix.sync="editColumn.suffix"
							:title.sync="editColumn.title" />
					</div>
					<div class="fix-col-2 margin-bottom">
						<NumberForm v-if="editColumn.type === 'number'"
							:number-default.sync="editColumn.numberDefault"
							:number-min.sync="editColumn.numberMin"
							:number-max.sync="editColumn.numberMax"
							:number-decimals.sync="editColumn.numberDecimals" />
						<TextlineForm v-if="editColumn.type === 'text' && !editColumn.textMultiline"
							:text-default.sync="editColumn.textDefault"
							:text-allowed-pattern.sync="editColumn.textAllowedPattern"
							:text-max-length.sync="editColumn.textMaxLength" />
						<LongtextForm v-if="editColumn.type === 'text' && editColumn.textMultiline"
							:text-default.sync="editColumn.textDefault"
							:text-max-length.sync="editColumn.textMaxLength" />
					</div>
					<div class="col-4">
						<button class="secondary" @click="editColumn = null">
							{{ t('tables', 'Cancel') }}
						</button>
						<button class="primary" @click="safeColumn">
							{{ t('tables', 'Save') }}
						</button>
					</div>
				</div>

				<!-- no edit mode -->
				<div v-else class="row">
					<div class="col-1 block" :class="{mandatory: column.mandatory}">
						{{ column.title }}

						<span v-if="column.type === 'number'" class="block">{{ t('tables', 'Number') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'mandatory'): '' }}</span>
						<span v-if="column.type === 'text' && !column.textMultiline" class="block">{{ t('tables', 'Textline') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'mandatory'): '' }}</span>
						<span v-if="column.type === 'text' && column.textMultiline" class="block">{{ t('tables', 'Longtext') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'mandatory'): '' }}</span>
					</div>
					<div class="col-1">
						<ColumnInfoPopover :column="column" />

						{{ column.description | truncate(50, '...') }}
					</div>
					<div class="col-1">
						<NumberTableDisplay v-if="column.type === 'number'" :column="column" />
						<TextlineTableDisplay v-if="column.type === 'text' && !column.textMultiline" :column="column" />
						<LongtextTableDisplay v-if="column.type === 'text' && column.textMultiline" :column="column" />
					</div>
					<div class="col-1">
						<Actions>
							<ActionButton icon="icon-rename" :close-after-click="true" @click="editColumn = column">
								{{ t('tables', 'Edit') }}
							</ActionButton>
							<ActionButton :close-after-click="true" icon="icon-delete">
								{{ t('tables', 'Delete') }}
							</ActionButton>
						</Actions>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-4 margin-bottom">
					<button class="secondary" @click="actionCancel">
						{{ t('tables', 'Close') }}
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ColumnInfoPopover from '../partials/ColumnInfoPopover'
import NumberTableDisplay from '../columnTypePartials/tableDisplay/NumberTableDisplay'
import TextlineTableDisplay from '../columnTypePartials/tableDisplay/TextlineTableDisplay'
import LongtextTableDisplay from '../columnTypePartials/tableDisplay/LongtextTableDisplay'
import NumberForm from '../columnTypePartials/forms/NumberForm'
import TextlineForm from '../columnTypePartials/forms/TextlineForm'
import LongtextForm from '../columnTypePartials/forms/LongtextForm'
import MainForm from '../columnTypePartials/forms/MainForm'

export default {
	name: 'EditColumns',
	components: {
		Modal,
		Actions,
		ActionButton,
		ColumnInfoPopover,
		NumberTableDisplay,
		TextlineTableDisplay,
		LongtextTableDisplay,
		NumberForm,
		TextlineForm,
		LongtextForm,
		MainForm,
	},
	filters: {
		truncate(text, length, suffix) {
			if (text.length > length) {
				return text.substring(0, length) + suffix
			} else {
				return text
			}
		},
	},
	props: {
		showModal: {
			type: Boolean,
		},
	},
	data() {
		return {
			loading: false,
			columns: null,
			editColumn: null,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	mounted() {
		this.getColumnsForTableFromBE()
	},
	methods: {
		async getColumnsForTableFromBE() {
			this.loading = true
			if (!this.activeTable.id) {
				this.columns = null
			} else {
				try {
					console.debug('try to fetch columns for table id: ', this.activeTable.id)
					const response = await axios.get(generateUrl('/apps/tables/column/' + this.activeTable.id))
					this.columns = response.data.sort(this.compareColumns)
					console.debug('columns loaded', this.columns)
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch columns for table'))
				}
			}
			this.loading = false
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		compareColumns(a, b) {
			if (a.orderWeight < b.orderWeight) { return 1 }
			if (a.orderWeight > b.orderWeight) { return -1 }
			return 0
		},
		async safeColumn() {
			await this.sendEditColumnToBE()
			this.editColumn = null
		},
		reset() {
			this.loading = false
			this.columns = null
			this.editColumn = null
		},
		async sendEditColumnToBE() {
			if (!this.editColumn) {
				showError(t('tables', 'Error occurs, see the logs.'))
				console.debug('tried to send editColumn to BE, but it is null', this.editColumn)
				return
			}
			try {
				console.debug('try so send column', this.editColumn)
				await axios.put(generateUrl('/apps/tables/column/' + this.editColumn.id), this.editColumn)
				showSuccess(t('tables', 'The column »{column}« was updated.', { column: this.editColumn.title }))
				this.getColumnsForTableFromBE()
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not update column'))
			}
		},
	},
}
</script>
<style scoped>

.editRow {
	background-color: var(--color-primary-light-hover);
}

</style>
