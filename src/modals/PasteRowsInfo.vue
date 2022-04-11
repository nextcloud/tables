<template>
	<Modal v-if="showModal" @close="actionClose">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Paste rows from clipboard') }}</h2>
				</div>
				<div class="col-4">
					<ol>
						<li class="bold">
							{{ t('tables', 'Click inside the table.') }}
						</li>
						<li class="bold">
							{{ t('tables', 'Then push the buttons "Ctrl + V" or as a mac user "⌘ + V".') }}
						</li>
					</ol>
				</div>
				<div class="col-4">
					<h3>{{ t('tables', 'Column mapping') }}</h3>
				</div>
				<div class="col-4">
					<p class="light">
						{{ t('tables', 'The app will attempt to work out which columns in the data correspond to columns in the table. It tries three different ways to achieve this:') }}
					</p>
				</div>
				<div class="col-4">
					<ol>
						<li class="light">
							{{ t('tables', 'First it checks the values of all columns in the first row of data to see if they match the titles of columns in the table.') }}
						</li>
						<li class="light">
							{{ t('tables', 'If any of the columns don\'t match it then tries the same approach but with the column ids.') }}
						</li>
						<li class="light">
							{{ t('tables', 'If either of those options match, the app will assume the columns in the data are in the same order as the visible columns in the table and import them that way. There have to be two bling columns for the row numbers and edit-button-column.') }}
						</li>
					</ol>
				</div>
				<div class="col-4">
					<h3>{{ t('tables', 'Hints') }}</h3>
				</div>
				<div class="col-4 space-B">
					<ol>
						<li class="light">
							{{ t('tables', 'The format for a date has to be like YYYY-MM-DD, for example 2022-12-24.') }}
						</li>
						<li class="light">
							{{ t('tables', 'If you try to import by column order, see the third point above. You need to add two dummy columns at the beginning.') }}
						</li>
						<li class="light">
							{{ t('tables', 'If you are on a Chrome browser, you have to create a row first. Click on this newly created row and the insert process should run.') }}
						</li>
					</ol>
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
