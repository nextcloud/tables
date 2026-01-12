<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="public-table-element" data-cy="publicTableElement">
		<div class="content-wrapper">
			<PublicMainWrapper v-if="tokenToUse" :token="tokenToUse" />
		</div>
	</div>
</template>

<script>
import PublicMainWrapper from '../modules/main/sections/PublicMainWrapper.vue'

export default {
	name: 'PublicTableView',

	components: {
		PublicMainWrapper,
	},

	props: {
		token: {
			type: String,
			default: '',
		},
	},

	computed: {
		tokenToUse() {
			return this.token || this.$route.params.token
		},
	},
}
</script>

<style scoped lang="scss">
.public-table-element {
	display: flex;
	flex-direction: column;

	:deep(.first-row) {
		padding-inline-start: calc(var(--default-grid-baseline) * 5);

		h1 {
			font-size: revert;
		}
	}

	:deep(.element-description) {
		padding-inline: calc(var(--default-grid-baseline) * 5);
	}

	:deep(.tables-list__table) {
		margin-top: 0;
	}
}

#header {
	display: flex;
	align-items: center;
	height: 50px;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
	padding: 0 20px;
	flex-shrink: 0;
}

.content-wrapper {
	width: 100%;
	height: 100%;
}

.logo {
	background-image: var(--image-logo);
	background-repeat: no-repeat;
	background-size: contain;
	background-position: center;
	width: 175px;
	height: 34px;
}
</style>
