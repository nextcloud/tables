<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="context-resource-cards" role="tablist" :aria-label="t('tables', 'Application resources')">
		<Transition name="card-flip" mode="out-in">
			<div v-if="flippedIndex === null" key="grid" class="context-resource-cards__grid">
				<div v-for="(resource, index) in resources"
					:key="resource.key"
					class="context-resource-cards__card"
					:class="{ 'context-resource-cards__card--active': index === activeIndex }">
					<button :id="`context-resource-card-${resource.key}`"
						type="button"
						role="tab"
						class="context-resource-cards__card-button"
						:aria-selected="index === activeIndex"
						@click="$emit('update:active-index', index)">
						<h3 class="context-resource-cards__title">
							<span v-if="resource.emoji">{{ resource.emoji }}&nbsp;</span>{{ resource.title }}
						</h3>
					</button>

					<button v-if="resource.description"
						type="button"
						class="context-resource-cards__info"
						:aria-label="t('tables', 'Show description')"
						@click.stop="flippedIndex = index">
						<InformationOutline :size="16" />
					</button>
				</div>
			</div>

			<div v-else key="detail" class="context-resource-cards__detail">
				<div class="context-resource-cards__detail-panel">
					<div class="context-resource-cards__detail-header">
						<h3 class="context-resource-cards__title">
							<span v-if="resources[flippedIndex].emoji">{{ resources[flippedIndex].emoji }}&nbsp;</span>{{ resources[flippedIndex].title }}
						</h3>
						<button type="button"
							class="context-resource-cards__info context-resource-cards__info--active"
							:aria-label="t('tables', 'Back to cards')"
							@click="flippedIndex = null">
							<Close :size="16" />
						</button>
					</div>
					<TableDescription class="context-resource-cards__detail-text" :description="resources[flippedIndex].description" :read-only="true" />
				</div>
			</div>
		</Transition>
	</div>
</template>

<script>
import TableDescription from './TableDescription.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
	name: 'ContextResourceCards',

	components: {
		TableDescription,
		InformationOutline,
		Close,
	},

	props: {
		resources: {
			type: Array,
			required: true,
		},
		activeIndex: {
			type: Number,
			default: 0,
		},
	},

	emits: ['update:active-index'],

	data() {
		return {
			flippedIndex: null,
		}
	},

	watch: {
		// Switching which resource is open below should drop back to the grid.
		activeIndex() {
			this.flippedIndex = null
		},
	},
}
</script>

<style scoped lang="scss">
.context-resource-cards {
	width: var(--app-content-width, 100%);
	padding: calc(4 * var(--default-grid-baseline, 4px)) 20px;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
	box-sizing: border-box;

	&__grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
		gap: calc(3 * var(--default-grid-baseline, 4px));
	}

	&__card {
		position: relative;
		box-sizing: border-box;
		height: calc(30 * var(--default-grid-baseline, 4px));
		overflow-y: auto;
		overflow-x: hidden;
		background-color: var(--color-main-background);
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-large, 12px);
		padding: calc(5 * var(--default-grid-baseline, 4px));
		transition: border-color var(--animation-quick, 100ms) ease, background-color var(--animation-quick, 100ms) ease;

		&:hover {
			border-color: var(--color-primary-element);
		}

		&--active {
			border-color: var(--color-primary-element);
			background-color: var(--color-primary-element-light);
		}
	}

	&__card-button {
		appearance: none;
		box-sizing: border-box;
		width: 100%;
		text-align: start;
		cursor: pointer;
		user-select: none;
		background: transparent;
		border: none;
		padding: 0;
		margin: 0;
		font: inherit;
		color: inherit;

		&:hover {
			background: transparent !important;
		}
	
		&:focus {
			outline: none;
		}
	
		&:focus:not(:focus-visible) {
			background: transparent !important;
			box-shadow: none !important;
		}
	
		&:focus-visible {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
		}
	}

	&__title {
		margin: 0;
		padding-inline-end: calc(6 * var(--default-grid-baseline, 4px));
		overflow-wrap: anywhere;
	}

	&__info {
		appearance: none;
		position: absolute;
		top: calc(3 * var(--default-grid-baseline, 4px));
		inset-inline-end: calc(3 * var(--default-grid-baseline, 4px));
		display: flex;
		align-items: center;
		justify-content: center;
		width: 24px;
		height: 24px;
		background: transparent;
		border: none;
		border-radius: 50%;
		cursor: pointer;
		color: var(--color-text-maxcontrast);
		transition: color var(--animation-quick, 100ms) ease, background-color var(--animation-quick, 100ms) ease;

		&:hover {
			color: var(--color-main-text);
			background-color: var(--color-background-hover);
		}

		&:focus {
			outline: none;
		}

		&:focus-visible {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
		}

		// Used as the "back" button inside the detail panel header instead of
		// a floating corner badge.
		&--active {
			position: static;
			flex-shrink: 0;
		}
	}

	&__detail {
		width: 100%;
		box-sizing: border-box;
	}

	&__detail-panel {
		width: 100%;
		box-sizing: border-box;
		background-color: var(--color-main-background);
		border: 2px solid var(--color-primary-element);
		border-radius: var(--border-radius-large, 12px);
		padding: calc(5 * var(--default-grid-baseline, 4px));
		overflow-x: hidden;

		:deep(.element-description) {
			width: 100%;
			max-width: 100%;
			padding-inline: 0 !important;
		}
	}

	&__detail-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: calc(3 * var(--default-grid-baseline, 4px));
		margin-bottom: calc(3 * var(--default-grid-baseline, 4px));
	}

	&__detail-text {
		overflow-wrap: anywhere;
	}
}

.card-flip-enter-active,
.card-flip-leave-active {
	transition: opacity var(--animation-quick, 150ms) ease, transform var(--animation-quick, 150ms) ease;
}

.card-flip-enter-from,
.card-flip-leave-to {
	opacity: 0;
	transform: scaleY(0.98);
}
</style>
