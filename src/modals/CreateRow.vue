<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create row') }}</h2>
				</div>
			</div>
			<div v-for="column in columns" :key="column.id">
				<TextlineForm
					v-if="column.type === 'text' && !column.textMultiline"
					:column="column"
					:value.sync="row[column.id]" />
				<LongtextForm
					v-if="column.type === 'text' && column.textMultiline"
					:column="column"
					:value.sync="row[column.id]" />
				<NumberForm
					v-if="column.type === 'number'"
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import TextlineForm from '../rowTypePartials/TextlineForm'
import LongtextForm from '../rowTypePartials/LongtextForm'
import NumberForm from '../rowTypePartials/NumberForm'

export default {
	name: 'CreateRow',
	components: {
		Modal,
		TextlineForm,
		LongtextForm,
		NumberForm,
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
		actionConfirm() {

		},
		async sendNewColumnToBE() {
			try {
				let type = this.type
				let textMultiline = false
				if (type === 'textline') {
					type = 'text'
				} else if (type === 'longtext') {
					type = 'text'
					textMultiline = true
				}
				const data = {
					type,
					title: this.title,
					description: this.description,
					numberPrefix: this.numberPrefix,
					numberSuffix: this.numberSuffix,
					orderWeight: this.orderWeight,
					mandatory: this.mandatory,
					numberDefault: this.numberDefault,
					numberMin: this.numberMin,
					numberMax: this.numberMax,
					numberDecimals: this.numberDecimals,
					textDefault: this.textDefault,
					textAllowedPattern: this.textAllowedPattern,
					textMaxLength: this.textMaxLength,
					textMultiline,
					tableId: this.activeTable.id,
				}
				// console.debug('try so send new column', data)
				await axios.post(generateUrl('/apps/tables/column'), data)
				showSuccess(t('tables', 'The column »{column}« was created.', { column: data.title }))
				// await this.$store.dispatch('loadTablesFromBE')
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column'))
			}
		},
		reset() {
		},
	},
}
</script>
