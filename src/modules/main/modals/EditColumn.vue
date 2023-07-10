<template>
	<NcModal size="large" @close="actionCancel">
		<div class="modal__content">
			<div v-if="loading" class="icon-loading" />

			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit column') }}</h2>
				</div>
			</div>

			<div class="row space-L">
				<div class="col-2">
					<MainForm :description.sync="editColumn.description"
						:mandatory.sync="editColumn.mandatory"
						:order-weight.sync="editColumn.orderWeight"
						:title.sync="editColumn.title"
						:edit-column="true"
						:title-missing-error="editErrorTitle" />
				</div>
				<div class="col-2 space-LR space-T">
					<component :is="getColumnForm" :column="editColumn" />
				</div>
				<!-- <div class="col-3 space-B space-T">
					<button class="secondary" @click="editColumn = null">
						{{ t('tables', 'Cancel') }}
					</button>
					<button class="primary" @click="saveColumn">
						{{ t('tables', 'Save') }}
					</button>
				</div> -->
				<!-- <div class="col-1 align-right">
					<ColumnInfoPopover :column="column" />
				</div> -->
			</div>
			<div class="buttons">
				<ColumnInfoPopover :column="column" />
				<div style="display: flex">
					<div class="button-padding-right">
						<NcButton type="secondary" @click="actionCancel">
							{{ t('tables', 'Cancel') }}
						</NcButton>
					</div>
					<NcButton type="primary" @click="saveColumn">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>

				<!-- no edit mode -->
				<!-- <div v-else class="row">
					<div class="col-2">
						<div class="row space-T">
							<div class="col-1" style="display: inline-flex;">
								<NcActions v-if="!otherActionPerformed" type="secondary">
									<NcActionButton icon="icon-rename" :close-after-click="true" @click="editColumn = column">
										{{ t('tables', 'Edit') }}
									</NcActionButton>
									<NcActionButton :close-after-click="true" icon="icon-delete" @click="deleteId = column.id">
										{{ t('tables', 'Delete') }}
									</NcActionButton>
								</NcActions>
							</div>
						</div>
					</div>
					<div v-if="column.id === deleteId" class="row space-L">
						<div class="col-4 space-T">
							<h4>{{ t('tables', 'Do you really want to delete the column "{column}"?', { column: column.title }) }}</h4>
						</div>
						<div class="col-4 space-T space-B">
							<button class="secondary" @click="deleteId = null">
								{{ t('tables', 'Cancel') }}
							</button>
							<button class="error" @click="deleteColumn">
								{{ t('tables', 'Delete') }}
							</button>
						</div>
					</div>
				</div> -->
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcActions, NcActionButton, NcButton } from '@nextcloud/vue'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters, mapState } from 'vuex'
import ColumnInfoPopover from '../partials/ColumnInfoPopover.vue'
import NumberForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberForm.vue'
import NumberStarsForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberStarsForm.vue'
import NumberProgressForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberProgressForm.vue'
import TextLineForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLineForm.vue'
import TextLinkForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLinkForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLongForm.vue'
import TextRichForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextRichForm.vue'
import MainForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/MainForm.vue'
import SelectionCheckForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionCheckForm.vue'
import SelectionForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionForm.vue'
import SelectionMultiForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionMultiForm.vue'
import DatetimeForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeForm.vue'
import DatetimeDateForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeTimeForm.vue'

export default {
	name: 'EditColumn',
	components: {
		DatetimeDateForm,
		DatetimeForm,
		DatetimeTimeForm,
		SelectionCheckForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		TextLineForm,
		TextLongForm,
		TextRichForm,
		TextLinkForm,
		MainForm,
		SelectionForm,
		SelectionMultiForm,
		NcModal,
		NcActions,
		NcActionButton,
		ColumnInfoPopover,
		NcButton,
	},
	filters: {
		truncate(text, length, suffix) {
			if (text?.length > length) {
				return text.substring(0, length) + suffix
			}
			return text
		},
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			editColumn: structuredClone(this.column),
			deleteId: null,
			editErrorTitle: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		otherActionPerformed() {
			return !!(this.editColumn !== null || this.deleteId !== null)
		},
		getColumnForm() {
			const form = this.snakeToCamel(this.column.type) + 'Form'
			if (this.$options.components && this.$options.components[form]) {
				return form
			} else {
				throw Error('Form ' + form + ' does no exist')
			}
		},
	},

	methods: {
		snakeToCamel(str) {
			str = str.toLowerCase().replace(/([-_][a-z])/g, group =>
				group
					.toUpperCase()
					.replace('_', '')
					.replace('-', '')
			)
			return str.charAt(0).toUpperCase() + str.slice(1)
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async saveColumn() {
			if (this.editColumn.title === '') {
				showError(t('tables', 'Cannot update column. Title is missing.'))
				this.editErrorTitle = true
				return
			}
			this.editErrorTitle = false
			await this.updateColumn()
			this.reset()
			this.$emit('close')
		},
		reset() {
			this.loading = false
			this.editColumn = null
			this.deleteId = null
			this.editErrorTitle = false
		},
		async updateColumn() {
			const data = { ...this.editColumn }
			delete data.type
			delete data.id
			delete data.tableId
			delete data.createdAt
			delete data.createdBy
			delete data.lastEditAt
			delete data.lastEditBy
			const res = await this.$store.dispatch('updateColumn', { id: this.editColumn.id, data })
			if (res) {
				showSuccess(t('tables', 'The column "{column}" was updated.', { column: this.editColumn.title }))
			}
		},
	},
}
</script>
<style>

.column-details-table table {
	width: 100%;
	max-width: 200px;
}
.buttons {
	display: flex;
	justify-content: space-between;
    padding:  calc(var(--default-grid-baseline) * 5);
}
.button-padding-right {
	padding-right: calc(var(--default-grid-baseline) * 2)
}

</style>
