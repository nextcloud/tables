<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div v-if="loading" class="icon-loading" />

			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit columns') }}</h2>
				</div>
			</div>

			<div v-for="column in columns" :key="column.id" class="row">
				<div class="col-1 block" :class="{mandatory: column.mandatory}">
					{{ column.title }}

					<span v-if="column.type === 'number'" class="block">{{ t('tables', 'Number') }}
						{{ (column.mandatory) ? ', ' + t('tables', 'mandatory'): '' }}</span>
					<span v-if="column.type === 'text' && !column.textMultiline" class="block">{{ t('tables', 'Textline') }}
						{{ (column.mandatory) ? ', ' + t('tables', 'mandatory'): '' }}</span>
					<span v-if="column.type === 'number' && column.textMultiline" class="block">{{ t('tables', 'Longtext') }}
						{{ (column.mandatory) ? ', ' + t('tables', 'mandatory'): '' }}</span>
				</div>
				<div class="col-1">
					{{ column.description }}
				</div>
				<div class="col-1">
					{{ column.oderWeight }}
				</div>
				<div class="col-1">
					<Actions>
						<ActionButton icon="icon-delete" @click="alert('Delete')">
							{{ t('tables', 'Delete') }}
						</ActionButton>
					</Actions>
				</div>
			</div>

			<div class="row">
				<div class="col-4 margin-bottom">
					<Button @click="actionCancel">
						{{ t('tables', 'Close') }}
					</Button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'EditColumns',
	components: {
		Modal,
		Actions,
		ActionButton,
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
					this.columns = response.data
					console.debug('columns loaded', this.columns)
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch columns for table'))
				}
			}
			this.loading = false
		},
		actionCancel() {
			this.$emit('close')
		},
	},
}
</script>
