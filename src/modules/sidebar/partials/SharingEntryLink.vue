<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="sharing-entry-link">
		<!-- Create Link State -->
		<div v-if="isCreateMode">
			<div v-if="!showCreateForm" class="sharing-entry-link__create">
				<div class="sharing-entry-link__create-row">
					<NcAvatar :is-no-user="true" icon-class="avatar-class-icon avatar-link-share icon-public-white " />
					<div class="sharing-entry-link__create-text">
						{{ t('tables', 'Create public link') }}
					</div>
				</div>
				<NcActionButton :disabled="loading" :aria-label="t('tables', 'Create a new share link')"
					:icon="loading ? 'icon-loading-small' : 'icon-add'" data-cy="sharingEntryLinkCreateButton"
					@click.prevent.stop="showCreateForm = true" />
			</div>

			<!-- Inline Creation Form -->
			<div v-else class="sharing-entry-link__create-form">
				<div class="sharing-entry-link__form-row">
					<NcActionCheckbox :checked.sync="usePassword" data-cy="sharingEntryLinkPasswordCheck" @update:chk="onTogglePassword">
						{{ t('tables', 'Set password') }}
					</NcActionCheckbox>
				</div>

				<div v-if="usePassword" class="sharing-entry-link__form-row">
					<NcActionInput :value.sync="password" type="password" :label="t('tables', 'Password')"
						:show-trailing-button="true" class="sharing-entry-link__password-input" data-cy="sharingEntryLinkPasswordInput">
						<template #trailing>
							<NcActionButton @click="copyPassword">
								<template #icon>
									<ContentCopy :size="20" />
								</template>
							</NcActionButton>
						</template>
					</NcActionInput>
				</div>

				<div class="sharing-entry-link__form-actions">
					<NcButton type="primary" :disabled="loading || (usePassword && !password && password !== 0)"
						data-cy="sharingEntryLinkCreateFormCreateButton" @click="onCreate">
						{{ t('tables', 'Create') }}
					</NcButton>
					<NcButton type="tertiary" :disabled="loading" @click="cancelCreate">
						{{ t('tables', 'Cancel') }}
					</NcButton>
				</div>
			</div>
		</div>

		<!-- Existing Link State -->
		<div v-else-if="share" class="sharing-entry-link__content">
			<div class="sharing-entry-link__row">
				<div class="sharing-entry-link__row-content">
					<NcAvatar :is-no-user="true" icon-class="icon-public-white" class="sharing-entry-link__avatar" />

					<div>
						<span class="sharing-entry__title" data-cy="sharingEntryLinkTitle"
							:title="t('tables', 'Share link')">
							{{ t('tables', 'Share link') }}
						</span>
						<div class="sharing-entry-link__subtitle">
							{{ t('tables', 'View only') }}
							<LockIcon v-if="hasPassword" :size="12" />
						</div>
					</div>
				</div>

				<div class="sharing-entry-link__actions">
					<!-- Copy Button -->
					<NcActionButton :aria-label="t('tables', 'Copy public share link')"
						data-cy="sharingEntryLinkCopyButton" @click="copyLink">
						<template #icon>
							<ContentCopy :size="20" />
						</template>
					</NcActionButton>

					<!-- Actions Menu -->
					<NcActions :open.sync="openMenu" menu-align="right">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>

						<!-- Delete -->
						<NcActionButton data-cy="sharingEntryLinkDeleteButton" @click="onDelete">
							<template #icon>
								<TrashCan :size="20" />
							</template>
							{{ t('tables', 'Delete link') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import copyToClipboard from '../../../shared/mixins/copyToClipboard.js'

import {
	NcActions,
	NcActionButton,
	NcActionInput,
	NcActionCheckbox,
	NcButton,
	NcAvatar,
} from '@nextcloud/vue'

import TrashCan from 'vue-material-design-icons/TrashCan.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'

export default {
	name: 'SharingEntryLink',

	components: {
		NcActions,
		NcActionButton,
		NcActionInput,
		NcActionCheckbox,
		NcButton,
		NcAvatar,
		TrashCan,
		ContentCopy,
		LockIcon,
		DotsHorizontal,
	},

	mixins: [copyToClipboard],

	props: {
		share: {
			type: Object,
			default: null,
		},
		isCreateMode: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loading: false,
			openMenu: false,

			// Creation State
			showCreateForm: false,
			usePassword: false,
			password: '',
		}
	},

	computed: {
		linkShareUrl() {
			if (!this.share) return ''
			return window.location.origin + generateUrl('/apps/tables/s/{token}', { token: this.share.token })
		},
		hasPassword() {
			return !!this.share?.password
		},
	},

	watch: {
		showCreateForm(val) {
			if (val) {
				this.onTogglePassword(this.usePassword)
			}
		},
	},

	methods: {
		t,
		onTogglePassword(checked) {
			this.usePassword = checked
			if (!checked) {
				this.password = ''
			}
		},
		cancelCreate() {
			this.showCreateForm = false
			this.password = ''
			this.usePassword = false
		},
		async onCreate() {
			this.loading = true
			try {
				// Pass password if enabled, otherwise plain create
				await this.$emit('create-link-share', this.usePassword ? this.password : null)
				this.cancelCreate()
			} catch (e) {
				showError(t('tables', 'Error creating link share'))
			} finally {
				this.loading = false
			}
		},
		async onDelete() {
			if (!this.share) return
			this.loading = true
			try {
				await this.$emit('delete-share', this.share)
			} catch (e) {
				showError(t('tables', 'Error deleting link share'))
			} finally {
				this.loading = false
			}
		},
		copyLink() {
			this.copyToClipboard(this.linkShareUrl)
				.then(() => {
					showSuccess(t('tables', 'Link copied to clipboard'))
				})
				.catch(() => {
					showError(t('tables', 'Error copying link'))
				})
		},
		copyPassword() {
			this.copyToClipboard(this.password)
				.then(() => {
					showSuccess(t('tables', 'Password copied'))
				})
				.catch(() => {
					showError(t('tables', 'Error copying password'))
				})
		},
	},
}
</script>

<style scoped lang="scss">
.sharing-entry-link {
	margin-bottom: 10px;
	padding-bottom: 5px;
	align-items: center;

	&__create {
		display: flex;
		justify-content: flex-start;
	}

	&__create-button {
		width: 100%;
		justify-content: flex-start;
	}

	&__create-form {
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		padding: 10px;
		background-color: var(--color-background-hover);
	}

	&__form-row {
		margin-bottom: 10px;
	}

	&__password-input {
		width: 100%;
	}

	&__form-actions {
		display: flex;
		gap: 5px;
		justify-content: flex-end;
	}

	&__content {
		width: 100%;
	}

	&__row {
		display: flex;
		align-items: center;
		height: 44px;
	}

	&__avatar {
		margin-inline-end: 10px;
		flex-shrink: 0;
	}

	&__info {
		flex-grow: 1;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	&__title {
		font-weight: bold;
		white-space: nowrap;
		text-overflow: ellipsis;
		overflow: hidden;
	}

	&__subtitle {
		font-size: 0.8em;
		color: var(--color-text-maxcontrast);
		display: flex;
		align-items: center;
		gap: 3px;
	}

	&__actions {
		display: flex;
		align-items: center;
		flex-shrink: 0;
	}

	li {
		list-style-type: none;
	}

	.sharing-entry__title {
		font-weight: normal;
	}

	.sharing-entry-link__create {
		display: flex;
		width: 100%;
		justify-content: space-between;
		align-items: center;

		:deep(.avatar-class-icon) {
			background-color: var(--color-primary-element);
		}
	}

	.sharing-entry-link__create-row {
		display: flex;
		align-items: center;

	}

	.sharing-entry-link__row-content {
		flex-grow: 1;
		overflow: hidden;
		display: flex;
		flex-direction: row;
		justify-content: left;
		align-items: center;
	}

	.sharing-entry-link__create-text {
		padding: 10px;
	}
}
</style>
