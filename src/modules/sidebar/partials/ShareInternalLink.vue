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
        {{ t('tables', 'Only works for users with access to this folder') }}
      </span>
    </div>

    <NcActionButton :title="copyTooltip" :aria-label="copyTooltip" @click="copyUrl" class="share-internal-link__button">
      <template #icon>
        <ContentCopy :size="20" />
      </template>
    </NcActionButton>
  </div>
</template>

<script>
import copyToClipboard from '../../../shared/mixins/copyToClipboard.js'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

export default {
  name: 'ShareInternalLink',

  mixins: [copyToClipboard],

  components: {
    NcActionButton,
    OpenInNew,
    ContentCopy,
  },

  props: {
    currentUrl: {
      type: String,
      required: true,
    },
  },

  computed: {
    copyTooltip() {
      return t('tables', 'Copy internal link to clipboard')
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
    width: 32px;
    height: 32px;
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

  &__button {
    margin-inline-start: auto;
    padding: 0.25rem !important;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}
</style>