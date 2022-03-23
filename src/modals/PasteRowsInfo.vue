<template>
	<Modal v-if="showModal" @close="actionClose">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Paste rows from clipboard') }}</h2>
				</div>
				<div class="col-4">
					<ol>
						<li>{{ t('tables', 'Click inside the table.') }}</li>
						<li>{{ t('tables', 'Then push the buttons "Ctrl + V" or as a mac user "⌘ + V".') }}</li>
					</ol>
				</div>
				<div class="col-4">
					<p class="span">
						{{ t('tables', 'The app will attempt to work out which columns in the data correspond to columns in the table. It tries three different ways to achieve this:') }}
						<ol>
							<li>{{ t('tables', 'First it checks the values of all columns in the first row of data to see if they match the titles of columns in the table.') }}</li>
							<li>{{ t('tables', 'If any of the columns don\'t match it then tries the same approach but with the column ids.') }}</li>
							<li>{{ t('tables', 'If either of those options match, the app will assume the columns in the data are in the same order as the visible columns in the table and import them that way. There have to be two bling columns for the row numbers and edit-button-column.') }}</li>
						</ol>
					</p>
				</div>
				<div class="col-4">
					<button class="success" @click="actionClose">
						{{ t('tables', 'Close') }}
					</button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'PasteRowsInfo',
	components: {
		Modal,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	methods: {
		actionClose() {
			this.$emit('close')
		},
	},
}
</script>
<style scoped>

	.modal__content {
		padding: 20px;
		min-height: inherit;
		max-height: inherit;
	}

	ol {
		margin-left: 30px;
	}

</style>
