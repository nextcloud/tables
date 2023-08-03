<template>
	<div v-if="activeElement" class="integration">
		<h3>{{ t('tables', 'API') }}</h3>
		<p>
			{{ t('tables', 'This is your API endpoint for this view') }}
		</p>
		<NcInputField id="urlTextField"
			:value="apiEndpointUrl"
			:show-trailing-button="true"
			:trailing-button-label="t('files', 'Copy to clipboard')"
			readonly="readonly"
			:success="copied"
			type="url"
			@focus="$event.target.select()"
			@trailing-button-click="copyUrl">
			<template #trailing-button-icon>
				<ContentCopy :size="20" />
			</template>
		</NcInputField>
		<h4>
			{{ t('tables', 'Your permissions') }}
		</h4>
		<ul>
			<li :class="{'notPermitted': !canReadData(activeElement)}">
				{{ canReadData(activeElement) ? '✓' : '' }} {{ t('tables', 'Read') }}
			</li>
			<li :class="{'notPermitted': !canCreateRowInElement(activeElement)}">
				{{ canCreateRowInElement(activeElement) ? '✓' : '' }} {{ t('tables', 'Create') }}
			</li>
			<li :class="{'notPermitted': !canUpdateData(activeElement)}">
				{{ canUpdateData(activeElement) ? '✓' : '' }} {{ t('tables', 'Update') }}
			</li>
			<li :class="{'notPermitted': !canDeleteData(activeElement)}">
				{{ canDeleteData(activeElement) ? '✓' : '' }} {{ t('tables', 'Delete') }}
			</li>
			<li :class="{'notPermitted': !canManageElement(activeElement)}">
				{{ canManageElement(activeElement) ? '✓' : '' }} {{ t('tables', 'Manage') }}
			</li>
		</ul>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	components: {
		NcInputField,
		ContentCopy,
	},

	mixins: [permissionsMixin],

	data() {
		return {
			loading: false,
			copied: false,
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeElement', 'isView']),
		apiEndpointUrl() {
			const params = {
				elementId: this.activeElement.id,
			}
			const url = '/apps/tables/api/1/' + (this.isView ? 'views' : 'tables') + '/{elementId}'
			return window.location.protocol + '//' + window.location.host + generateUrl(url, params)
		},
	},
	 methods: {
		async copyUrl() {
			document.querySelector('input#urlTextField').select()

			if (!navigator.clipboard) {
				try {
					document.execCommand('copy')
				} catch (e) {
					showError(t('files', 'Clipboard is not available'))
					return
				}
			} else {
				await navigator.clipboard.writeText(this.webdavUrl)
			}
			this.copied = true
			showSuccess(t('files', 'Integration URL copied to clipboard'))
			setTimeout(() => {
				this.copied = false
			}, 5000)
		},
	 },
}
</script>
<style lang="scss" scoped>

.url {
	font-style: italic;
	overflow-x: auto
}

h4 {
	margin-top:calc(var(--default-grid-baseline) * 3);
}

ul {
	margin-left: calc(var(--default-grid-baseline) * 3);
}

.notPermitted {
	padding-left: 17px;
}

</style>
