<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="context-resource-tabs">
		<div class="context-resource-tabs__bar">
			<div class="context-resource-tabs__list"
				role="tablist"
				:aria-label="t('tables', 'Application resources')"
				@keydown="onKeydown">
				<div v-for="(resource, index) in resources"
					:key="resource.key"
					class="context-resource-tabs__item"
					:class="{ 'context-resource-tabs__item--active': index === activeIndex }"
					role="presentation"
					@mouseenter="showTooltip($event, index)"
					@mouseleave="hideTooltip">
					<button :id="`context-resource-tab-${resource.key}`"
						type="button"
						role="tab"
						class="context-resource-tabs__tab"
						:class="{
							'context-resource-tabs__tab--active': index === activeIndex,
							'context-resource-tabs__tab--with-info': hasDescription(resource),
						}"
						:aria-selected="index === activeIndex ? 'true' : 'false'"
						:aria-controls="`context-resource-panel-${resource.key}`"
						:aria-describedby="tooltip?.index === index ? TOOLTIP_ID : undefined"
						:tabindex="index === activeIndex ? 0 : -1"
						@focus="onTabFocus($event, index)"
						@blur="hideTooltip"
						@click="select(index)">
						<span class="context-resource-tabs__label">
							<span v-if="resource.emoji">{{ resource.emoji }}&nbsp;</span>{{ resource.title }}
						</span>
					</button>

					<!-- Only on tabs whose table has a description -->
					<button v-if="hasDescription(resource)"
						type="button"
						class="context-resource-tabs__info"
						:class="{ 'context-resource-tabs__info--open': descriptionOpen && index === activeIndex }"
						:tabindex="index === activeIndex ? 0 : -1"
						:aria-label="descriptionOpen && index === activeIndex ? t('tables', 'Hide description') : t('tables', 'Show description')"
						:aria-expanded="index === activeIndex ? (descriptionOpen ? 'true' : 'false') : undefined"
						:aria-controls="index === activeIndex && descriptionOpen ? PANEL_ID : undefined"
						@click.stop="toggleDescription(index)">
						<InformationOutline :size="16" />
					</button>
				</div>
			</div>
		</div>

		<!-- Positioned from the hovered tab (whichever row it is on) and
		     rendered outside the tab list so it is never clipped.
		     The teaser goes through TableDescription (Text editor, so rich
		     content such as link previews is rendered); without the Text app
		     TableDescription falls back to plain markdown. -->
		<div v-if="tooltip"
			:id="TOOLTIP_ID"
			class="context-resource-tabs__tooltip"
			:class="{ 'context-resource-tabs__tooltip--wide': tooltip.preview }"
			role="tooltip"
			:style="{ left: tooltip.left + 'px', top: tooltip.top + 'px' }">
			<span class="context-resource-tabs__tooltip-title">{{ tooltip.title }}</span>
			<div v-if="tooltip.preview"
				ref="preview"
				class="context-resource-tabs__tooltip-preview"
				:class="{ 'context-resource-tabs__tooltip-preview--clipped': tooltip.clipped }">
				<TableDescription :description="tooltip.preview" :read-only="true" />
			</div>
		</div>

		<Transition name="tabs-panel">
			<div v-if="descriptionOpen && activeHasDescription"
				:id="PANEL_ID"
				class="context-resource-tabs__panel">
				<div class="context-resource-tabs__panel-header">
					<h3 class="context-resource-tabs__panel-title">
						<span v-if="activeResource.emoji">{{ activeResource.emoji }}&nbsp;</span>{{ activeResource.title }}
					</h3>
					<button type="button"
						class="context-resource-tabs__close"
						:aria-label="t('tables', 'Hide description')"
						@click="descriptionOpen = false">
						<Close :size="20" />
					</button>
				</div>

				<!-- Rendered by the Text app; TableDescription falls back to a
				     plain markdown rendering when that app is unavailable. -->
				<TableDescription :key="activeResource.key"
					class="context-resource-tabs__panel-text"
					:description="activeResource.description"
					:read-only="true" />
			</div>
		</Transition>
	</div>
</template>

<script>
import TableDescription from './TableDescription.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'

const TOOLTIP_ID = 'context-resource-tooltip'
const PANEL_ID = 'context-resource-description'
const TOOLTIP_DELAY = 400
const TOOLTIP_MAX_WIDTH = 480 // keep in sync with the CSS `max-width` of the tooltip
const TOOLTIP_GAP = 4
// The description panel is made taller than the visible area by this much, so
// no gap shows at the bottom of the screen while the page is scrolling.
const PANEL_OVERSHOOT = 200
const PREVIEW_MAX_CHARS = 600
const PREVIEW_MAX_LINES = 10

export default {
	name: 'ContextResourceTabs',

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
			TOOLTIP_ID,
			PANEL_ID,
			// The description is always the one of the active resource, so a
			// single flag is enough.
			descriptionOpen: false,
			// Set when the (i) of an inactive tab is clicked: the description
			// opens once the table below has switched to that tab.
			openAfterSwitch: false,
			tooltip: null,
		}
	},

	computed: {
		activeResource() {
			return this.resources[this.activeIndex] ?? null
		},

		activeHasDescription() {
			return this.hasDescription(this.activeResource)
		},
	},

	watch: {
		activeIndex() {
			this.descriptionOpen = this.openAfterSwitch && this.hasDescription(this.activeResource)
			this.openAfterSwitch = false
			this.hideTooltip()
		},

		// The active resource lost its description (e.g. after a reload):
		// close the panel so its listeners are released.
		activeHasDescription(has) {
			if (!has) {
				this.descriptionOpen = false
			}
		},

		// While the description is open it fills the screen below the tabs, so
		// its height follows the window size and the scroll position.
		descriptionOpen(open) {
			if (open) {
				this.startPanelSizing()
			} else {
				this.stopPanelSizing()
			}
		},
	},

	created() {
		// Not reactive on purpose.
		this.clipObserver = null
		this.resizeObserver = null
		this.panelFrame = 0
		this.tooltipTimer = null
		this.clipTimer = null
	},

	mounted() {
		// Tabs wrap onto several rows, so the height of this block varies.
		// Publish it as `--tbl-tabs-h` on the parent (`.resources`): the
		// sticky options bar of the table below uses it as its `top`.
		this.publishHeight()
		if (typeof ResizeObserver !== 'undefined') {
			this.resizeObserver = new ResizeObserver(() => this.publishHeight())
			this.resizeObserver.observe(this.$el)
		}
	},

	beforeUnmount() {
		this.hideTooltip()
		this.stopPanelSizing()
		this.resizeObserver?.disconnect()
		this.resizeObserver = null
		this.$el.parentElement?.style.removeProperty('--tbl-tabs-h')
	},

	methods: {
		publishHeight() {
			this.$el.parentElement?.style.setProperty('--tbl-tabs-h', this.$el.offsetHeight + 'px')
			if (this.descriptionOpen) {
				this.updatePanelHeight()
			}
		},

		startPanelSizing() {
			this.updatePanelHeight()
			window.addEventListener('resize', this.schedulePanelHeight)
			// Capture: the scrolling element is an ancestor, not the window.
			document.addEventListener('scroll', this.schedulePanelHeight, true)
		},

		stopPanelSizing() {
			window.removeEventListener('resize', this.schedulePanelHeight)
			document.removeEventListener('scroll', this.schedulePanelHeight, true)
			cancelAnimationFrame(this.panelFrame)
			this.panelFrame = 0
			this.$el?.style.removeProperty('--tbl-panel-h')
			this.$el?.style.removeProperty('--tbl-panel-extra')
		},

		schedulePanelHeight() {
			if (!this.panelFrame) {
				this.panelFrame = requestAnimationFrame(() => {
					this.panelFrame = 0
					this.updatePanelHeight()
				})
			}
		},

		// From the bottom of the tab area down to the bottom of the screen, plus
		// an overshoot that stays off-screen (compensated by the panel padding).
		updatePanelHeight() {
			const bottom = this.$el.getBoundingClientRect().bottom
			const visible = Math.max(160, window.innerHeight - bottom)
			this.$el.style.setProperty('--tbl-panel-h', (visible + PANEL_OVERSHOOT) + 'px')
			this.$el.style.setProperty('--tbl-panel-extra', PANEL_OVERSHOOT + 'px')
		},

		hasDescription(resource) {
			const description = resource?.description
			if (typeof description === 'string') {
				return description.trim() !== ''
			}
			return !!description
		},

		select(index) {
			this.hideTooltip()
			if (index !== this.activeIndex) {
				this.$emit('update:active-index', index)
			}
		},

		toggleDescription(index) {
			this.hideTooltip()
			if (index === this.activeIndex) {
				this.descriptionOpen = !this.descriptionOpen
				return
			}
			// The table below must follow the tab whose description is shown.
			this.openAfterSwitch = true
			this.$emit('update:active-index', index)
			// If the parent did not switch tabs, do not leave the flag armed.
			this.$nextTick(() => {
				this.openAfterSwitch = false
			})
		},

		// First lines of the raw markdown, cut on a line boundary so a link
		// preview or list is not split in the middle. The CSS decides how
		// much of it is actually visible.
		previewMarkdown(description) {
			const lines = String(description).trim().split('\n')
			const kept = []
			let size = 0
			for (const line of lines) {
				if (kept.length >= PREVIEW_MAX_LINES || size + line.length > PREVIEW_MAX_CHARS) {
					break
				}
				kept.push(line)
				size += line.length
			}
			return kept.length > 0 ? kept.join('\n') : (lines[0] || '').slice(0, PREVIEW_MAX_CHARS)
		},

		onTabFocus(event, index) {
			// Mouse clicks also focus the tab: only show it for keyboard focus.
			if (event.currentTarget.matches(':focus-visible')) {
				this.showTooltip(event, index, true)
			}
		},

		// Shown when the title is cut off and/or the table has a description.
		showTooltip(event, index, immediate = false) {
			const item = event.currentTarget.closest('.context-resource-tabs__item')
			const resource = this.resources[index]
			if (!item || !resource) {
				return
			}
			const label = item.querySelector('.context-resource-tabs__label')
			const truncated = !!label && label.scrollWidth > label.clientWidth
			const described = this.hasDescription(resource)
			if (!truncated && !described) {
				this.hideTooltip()
				return
			}

			clearTimeout(this.tooltipTimer)
			this.tooltipTimer = setTimeout(() => {
				const rootRect = this.$el.getBoundingClientRect()
				const itemRect = item.getBoundingClientRect()
				const left = Math.max(8, Math.min(itemRect.left - rootRect.left, rootRect.width - TOOLTIP_MAX_WIDTH - 8))
				this.tooltip = {
					index,
					left,
					// Right under the hovered tab, on whichever row it is.
					top: itemRect.bottom - rootRect.top + TOOLTIP_GAP,
					title: `${resource.emoji ? resource.emoji + ' ' : ''}${resource.title}`,
					preview: described ? this.previewMarkdown(resource.description) : '',
				}

				this.$nextTick(() => this.watchPreviewClip())
			}, immediate ? 0 : TOOLTIP_DELAY)
		},

		hideTooltip() {
			clearTimeout(this.tooltipTimer)
			clearTimeout(this.clipTimer)
			this.clipObserver?.disconnect()
			this.clipObserver = null
			this.tooltip = null
		},

		// The editor renders asynchronously, so the content height is not
		// known when the tooltip opens: re-check whenever the preview changes
		// and fade the bottom edge only if the content is really cut off.
		watchPreviewClip() {
			this.clipObserver?.disconnect()
			clearTimeout(this.clipTimer)
			const el = this.$refs.preview
			if (!el) {
				return
			}
			const check = () => {
				if (this.tooltip && !this.tooltip.clipped && el.scrollHeight > el.clientHeight + 1) {
					this.tooltip = { ...this.tooltip, clipped: true }
				}
			}
			if (typeof MutationObserver !== 'undefined') {
				this.clipObserver = new MutationObserver(check)
				this.clipObserver.observe(el, { childList: true, subtree: true, attributes: true })
			}
			check()
			this.clipTimer = setTimeout(check, 600)
		},

		// Arrow keys move focus, Enter/Space select (manual activation, so
		// browsing the tabs does not reload a table on every keypress).
		// Escape closes the tooltip first, then the description panel.
		onKeydown(event) {
			if (event.key === 'Escape') {
				if (this.tooltip) {
					this.hideTooltip()
				} else if (this.descriptionOpen) {
					this.descriptionOpen = false
				}
				return
			}
			if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
				return
			}
			// Works from the tab button as well as from its (i) button.
			const tabs = [...event.currentTarget.querySelectorAll('[role="tab"]')]
			const currentItem = event.target.closest('.context-resource-tabs__item')
			const current = tabs.indexOf(currentItem?.querySelector('[role="tab"]'))
			if (current === -1) {
				return
			}
			const rtl = getComputedStyle(event.currentTarget).direction === 'rtl'
			let next = current
			if (event.key === 'Home') {
				next = 0
			} else if (event.key === 'End') {
				next = tabs.length - 1
			} else {
				const forward = (event.key === 'ArrowRight') !== rtl
				next = (current + (forward ? 1 : -1) + tabs.length) % tabs.length
			}
			event.preventDefault()
			tabs[next].focus()
		},
	},
}
</script>

<style scoped lang="scss">
// One row of tabs = 47px + the 1px bottom border of the root = 48px.
$tab-row-h: 47px;
$anim-quick: var(--animation-quick, 100ms);

@mixin icon-button($size) {
	appearance: none;
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	width: $size;
	height: $size;
	padding: 0;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	background: transparent;
	border: none;
	border-radius: 50%;
	transition: color $anim-quick ease, background-color $anim-quick ease;

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
}

.context-resource-tabs {
	// The height is not fixed: tabs wrap onto extra rows. The component
	// measures itself and publishes it as `--tbl-tabs-h` on its parent, the
	// `top` of the table's sticky options bar (see Context.vue).
	position: sticky;
	top: 0;
	inset-inline-start: 0; // stays in view when a wide table scrolls sideways
	// Must stay above the table's own sticky rows (title, options bar, header),
	// otherwise the description overlay is painted underneath them.
	z-index: 100;
	width: var(--app-content-width, 100%);
	box-sizing: border-box;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	&__bar {
		padding-inline: 20px;
		box-sizing: border-box;
	}

	&__list {
		display: flex;
		flex-wrap: wrap; // every table stays visible, extra ones go to the next row
		gap: 0 var(--default-grid-baseline, 4px);
	}

	// One tab = the tab button + its (i) button, side by side (a button
	// cannot contain another button). The underline lives on the item.
	&__item {
		flex: 0 0 auto;
		display: flex;
		align-items: center;
		height: $tab-row-h;
		max-width: calc(65 * var(--default-grid-baseline, 4px));
		box-sizing: border-box;
		border-bottom: 3px solid transparent;
		transition: border-color $anim-quick ease;

		&:hover {
			border-bottom-color: var(--color-border-maxcontrast);
		}

		&--active,
		&--active:hover {
			border-bottom-color: var(--color-primary-element);
		}
	}

	&__tab {
		appearance: none;
		flex: 1 1 auto;
		min-width: 0;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		align-self: stretch;
		margin: 0;
		padding: 0 calc(4 * var(--default-grid-baseline, 4px));
		font: inherit;
		color: var(--color-text-maxcontrast);
		cursor: pointer;
		user-select: none;
		background: transparent;
		border: none;
		border-radius: 0;
		transition: color $anim-quick ease;

		&--with-info {
			padding-inline-end: var(--default-grid-baseline, 4px);
		}

		// Global `button:hover` styles must not tint the tab background.
		&:hover {
			color: var(--color-main-text);
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
			outline-offset: -2px;
		}

		&--active,
		&--active:hover {
			color: var(--color-main-text);
		}
	}

	&__label {
		min-width: 0;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	// (i) sitting on the tab itself
	&__info {
		@include icon-button(24px);
		margin-inline-end: calc(2 * var(--default-grid-baseline, 4px));

		&--open,
		&--open:hover {
			color: var(--color-primary-element-text);
			background-color: var(--color-primary-element);
		}
	}

	// × inside the description panel
	&__close {
		@include icon-button(32px);
	}

	// Hover / focus tooltip: full title + a faded teaser of the description.
	&__tooltip {
		position: absolute;
		z-index: 3;
		box-sizing: border-box;
		width: max-content;
		max-width: min(480px, calc(100vw - 32px)); // keep in sync with TOOLTIP_MAX_WIDTH
		padding: calc(2 * var(--default-grid-baseline, 4px)) calc(3 * var(--default-grid-baseline, 4px));
		background-color: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large, 12px);
		box-shadow: 0 2px 8px var(--color-box-shadow, rgba(0, 0, 0, 0.2));
		pointer-events: none;

		// With a preview the tooltip gets its full width right away. Sized to
		// its content it would only be as wide as the title, because the
		// description is rendered asynchronously.
		&--wide {
			width: min(480px, calc(100vw - 32px)); // keep in sync with TOOLTIP_MAX_WIDTH
		}
	}

	&__tooltip-title {
		display: block;
		font-weight: bold;
		overflow-wrap: anywhere;
	}

	// Teaser: the first ~80px of the rendered markdown, fading out at the bottom.
	&__tooltip-preview {
		margin-top: var(--default-grid-baseline, 4px);
		max-height: 80px;
		overflow: hidden;
		overflow-wrap: anywhere;
		color: var(--color-text-maxcontrast);

		// Fixed-length fade at the bottom edge (only set when the text is cut off).
		&--clipped {
			-webkit-mask-image: linear-gradient(to bottom, #000 calc(100% - 24px), transparent 100%);
			mask-image: linear-gradient(to bottom, #000 calc(100% - 24px), transparent 100%);
		}

		// Neutralise the description's own layout (wide centred column,
		// editor padding) so the teaser starts at the top-left.
		:deep(.element-description) {
			width: 100%;
			max-width: 100%;
			padding-inline: 0 !important;
		}

		:deep(.ProseMirror) {
			margin: 0 !important;
			padding: 0 !important;
		}

		// Compact markdown: no big headings or margins in a small window.
		:deep(h1),
		:deep(h2),
		:deep(h3),
		:deep(h4),
		:deep(h5),
		:deep(h6) {
			margin: 0;
			font-size: var(--default-font-size);
			font-weight: bold;
			color: var(--color-main-text);
		}

		:deep(p),
		:deep(ul),
		:deep(ol),
		:deep(blockquote),
		:deep(pre) {
			margin: 0;
		}

		:deep(ul),
		:deep(ol) {
			padding-inline-start: calc(5 * var(--default-grid-baseline, 4px));
		}
	}

	// Covers the whole area below the tabs (down to the bottom of the screen)
	// instead of pushing the table, so the tab area keeps its height and the
	// table's sticky bar offset stays valid. Its height is `--tbl-panel-h`,
	// kept up to date by the script; 60vh is only the fallback.
	&__panel {
		position: absolute;
		inset-inline: 0;
		top: 100%;
		z-index: 2;
		box-sizing: border-box;
		height: var(--tbl-panel-h, 60vh);
		overflow-x: hidden;
		overflow-y: auto;
		// Scrolling the description must not scroll the page behind it.
		overscroll-behavior: contain;
		// The bottom padding includes the off-screen overshoot, so the last
		// lines of the description stay reachable.
		padding: calc(4 * var(--default-grid-baseline, 4px)) 20px calc(5 * var(--default-grid-baseline, 4px) + var(--tbl-panel-extra, 0px));
		background-color: var(--color-main-background);
		border-bottom: 1px solid var(--color-border);
		box-shadow: 0 6px 12px var(--color-box-shadow, rgba(0, 0, 0, 0.15));

		:deep(.element-description) {
			width: 100%;
			max-width: 100%;
			padding-inline: 0 !important;
		}
	}

	&__panel-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: calc(3 * var(--default-grid-baseline, 4px));
		margin-bottom: calc(3 * var(--default-grid-baseline, 4px));
	}

	&__panel-title {
		margin: 0;
		overflow-wrap: anywhere;
	}

	&__panel-text {
		overflow-wrap: anywhere;
	}
}

.tabs-panel-enter-active,
.tabs-panel-leave-active {
	transition: opacity $anim-quick ease, transform $anim-quick ease;
}

.tabs-panel-enter-from,
.tabs-panel-leave-to {
	opacity: 0;
	transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
	.tabs-panel-enter-active,
	.tabs-panel-leave-active,
	.context-resource-tabs__item,
	.context-resource-tabs__tab,
	.context-resource-tabs__info,
	.context-resource-tabs__close {
		transition: none;
	}
}
</style>
