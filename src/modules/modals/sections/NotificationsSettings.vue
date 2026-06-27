<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="elementId" class="notifications-settings">
		<p>
			{{ targetDescription }}
		</p>
		<p class="notifications-settings__hint">
			{{ t('tables', 'These settings are stored separately for each table and view.') }}
		</p>

		<div v-if="loading" class="notifications-settings__loading">
			<NcLoadingIcon :size="24" :name="t('tables', 'Loading notification settings')" />
		</div>

		<div v-else class="notifications-settings__list">
			<NcCheckboxRadioSwitch
				v-for="setting in notificationSettings"
				:key="setting.key"
				:checked="config[setting.key]"
				:disabled="savingConfig[setting.key]"
				:description="setting.description"
				type="switch"
				@update:checked="updateConfig(setting.key, $event)">
				<div class="notifications-settings__label">
					<span>{{ setting.label }}</span>
					<span v-if="savingConfig[setting.key]" class="notifications-settings__saving">
						{{ t('tables', 'Saving...') }}
					</span>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { NcCheckboxRadioSwitch, NcLoadingIcon } from '@nextcloud/vue'
import displayError from '../../../shared/utils/displayError.js'

export default {
	name: 'NotificationsSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
	},

	props: {
		isView: {
			type: Boolean,
			default: false,
		},
		elementId: {
			type: Number,
			default: null,
		},
	},

	data() {
		return {
			loading: false,
			loadRequestId: 0,
			config: this.getDefaultConfig(),
			savingConfig: this.getDefaultConfig(),
		}
	},

	computed: {
		configScope() {
			return this.isView ? 'view' : 'table'
		},
		activeConfigTarget() {
			if (!this.elementId) {
				return ''
			}

			return `${this.configScope}:${this.elementId}`
		},
		targetDescription() {
			return this.isView
				? t('tables', 'Choose which changes in this view should notify you.')
				: t('tables', 'Choose which changes in this table should notify you.')
		},
		notificationSettings() {
			return [
				{
					key: 'notify-row',
					label: t('tables', 'Content changes'),
					description: t('tables', 'Notify me when row values are created, updated or deleted.'),
				},
				{
					key: 'notify-column',
					label: t('tables', 'Structure changes'),
					description: t('tables', 'Notify me when columns are created, updated or deleted.'),
				},
				{
					key: 'notify-assigned',
					label: t('tables', 'Assigned'),
					description: t('tables', 'Notify me when I am assigned in a user group field.'),
				},
			]
		},
	},

	watch: {
		activeConfigTarget: {
			immediate: true,
			handler() {
				this.loadConfig()
			},
		},
	},

	methods: {
		t,
		getDefaultConfig() {
			return {
				'notify-column': false,
				'notify-row': false,
				'notify-mention': false,
			}
		},
		getConfigCollectionEndpoint() {
			if (!this.elementId) {
				return ''
			}

			return generateOcsUrl(`/apps/tables/api/2/config/${this.configScope}/${this.elementId}`)
		},
		getConfigItemEndpoint(configKey, targetType, targetId) {
			const key = encodeURIComponent(`${targetType}:${targetId}:${configKey}`)
			return generateOcsUrl(`/apps/tables/api/2/config/${key}`)
		},
		resetState() {
			this.config = this.getDefaultConfig()
			this.savingConfig = this.getDefaultConfig()
		},
		async loadConfig() {
			if (!this.elementId) {
				this.resetState()
				return
			}

			const requestId = ++this.loadRequestId
			this.loading = true
			this.savingConfig = this.getDefaultConfig()

			try {
				const response = await axios.get(this.getConfigCollectionEndpoint())
				if (requestId !== this.loadRequestId) {
					return
				}

				this.config = {
					...this.getDefaultConfig(),
					...(response?.data?.ocs?.data ?? {}),
				}
			} catch (error) {
				if (requestId !== this.loadRequestId) {
					return
				}

				this.config = this.getDefaultConfig()
				displayError(error, t('tables', 'Could not load notification settings.'))
			} finally {
				if (requestId === this.loadRequestId) {
					this.loading = false
				}
			}
		},
		async updateConfig(configKey, value) {
			if (!this.elementId) {
				return
			}

			const targetType = this.configScope
			const targetId = this.elementId
			const previousValue = this.config[configKey]

			this.config = {
				...this.config,
				[configKey]: value,
			}
			this.savingConfig = {
				...this.savingConfig,
				[configKey]: true,
			}

			try {
				const response = await axios.post(this.getConfigItemEndpoint(configKey, targetType, targetId), { value })
				if (this.configScope !== targetType || this.elementId !== targetId) {
					return
				}

				this.config = {
					...this.config,
					[configKey]: response?.data?.ocs?.data ?? value,
				}
			} catch (error) {
				if (this.configScope === targetType && this.elementId === targetId) {
					this.config = {
						...this.config,
						[configKey]: previousValue,
					}
				}

				displayError(error, t('tables', 'Could not update notification settings.'))
			} finally {
				if (this.configScope === targetType && this.elementId === targetId) {
					this.savingConfig = {
						...this.savingConfig,
						[configKey]: false,
					}
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.notifications-settings {
	&__hint {
		color: var(--color-text-maxcontrast);
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__loading {
		display: flex;
		justify-content: center;
		padding: calc(var(--default-grid-baseline) * 2) 0;
	}

	&__list {
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__label {
		display: flex;
		gap: calc(var(--default-grid-baseline) * 2);
		justify-content: space-between;
		width: 100%;
	}

	&__saving {
		color: var(--color-text-maxcontrast);
		font-size: 0.875rem;
		font-weight: normal;
	}

	:deep(.checkbox-radio-switch__label) {
		width: 100%;
	}
}
</style>
