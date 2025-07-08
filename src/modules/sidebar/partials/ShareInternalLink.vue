<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="share-internal-link">
		<div class="share-internal-link__icon">
			<OpenInNew :size="20" />
		</div>

		<div class="share-internal-link__info">
			<span class="share-internal-link__title">
				{{ t('tables', 'Internal link') }}
			</span>
			<span class="share-internal-link__subtitle">
				{{ internalLinkSubtitle }}
			</span>
		</div>

		<NcButton
			:title="copyTooltip"
			:aria-label="copyTooltip"
			class="share-internal-link__button"
			variant="tertiary"
			@click="copyUrl">
			<template #icon>
				<ContentCopy :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import copyToClipboard from '../../../shared/mixins/copyToClipboard.js'
import { t } from '@nextcloud/l10n'

import { NcButton } from '@nextcloud/vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

export default {
	name: 'ShareInternalLink',

	components: {
		NcButton,
		OpenInNew,
		ContentCopy,
	},

	mixins: [copyToClipboard],

	props: {
		currentUrl: {
			type: String,
			required: true,
		},
		isView: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		copyTooltip() {
			return t('tables', 'Copy internal link to clipboard')
		},
		internalLinkSubtitle() {
			if (this.isView) {
				return t('tables', 'Only works for users with access to this view')
			}
			return t('tables', 'Only works for users with access to this table')
		},
	},

	methods: {
		copyUrl() {
			this.copyToClipboard(this.currentUrl, false)
		},
	},
}
</script>

<style scoped lang="scss">
.share-internal-link {
  list-style: none;
  display: flex;
  align-items: center;
  min-height: 44px;
  gap: 12px;

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: var(--default-clickable-area);
    height: var(--default-clickable-area);
    background-color: var(--color-text-maxcontrast);
    border-radius: 50%;
    color: var(--color-main-background);
    flex-shrink: 0;

    svg {
      fill: currentColor;
    }
  }

  &__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  &__title {
    font-weight: 500;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
  }

  &__subtitle {
    font-size: 0.85em;
    color: var(--color-text-light);
  }
}
</style>
