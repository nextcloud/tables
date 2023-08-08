<!--
  - @copyright Copyright (c) 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @author 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->
<template>
	<div class="picker-content">
		<h2 class="picker-title">
			{{ t('tables', 'Nextcloud Tables') }}
		</h2>
		<div class="space-B">
			<table>
				<tr>
					<td>
						<Search :value.sync="value" />
					</td>
					<td>
						<h3>{{ t('tables', 'Render mode') }}</h3>
						<div class="radio">
							<NcCheckboxRadioSwitch
								:checked.sync="renderMode"
								value="link"
								name="render-mode"
								type="radio">
								<IconLink :size="20" />{{ t('tables', 'Link') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								:checked.sync="renderMode"
								value="content"
								name="render-mode"
								type="radio">
								<IconText :size="20" />{{ t('tables', 'Content') }}
							</NcCheckboxRadioSwitch>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<div v-if="getLink">
			{{ getLink }}
		</div>
		<NcEmptyContent v-else>
			<template #icon>
				<IconTables />
			</template>
		</NcEmptyContent>

		<div class="select-button">
			<NcButton type="primary" :disabled="value === null" :aria-label="t('tables', 'Select')" @click="selectReference">
				<template #icon>
					<IconCheck :size="20" />
				</template>
				{{ t('tables', 'Confirm selection') }}
			</NcButton>
		</div>
	</div>
</template>
<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { NcEmptyContent, NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import IconTables from '../shared/assets/icons/IconTables.vue'
import Search from './SmartPicker/Search.vue'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconText from 'vue-material-design-icons/Text.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { generateUrl } from '@nextcloud/router'

export default {

	components: {
		IconTables,
		IconLink,
		IconText,
		IconCheck,
		NcEmptyContent,
		Search,
		NcCheckboxRadioSwitch,
		NcButton,
	},

	props: {
	},

	data() {
		return {
			renderMode: 'link', // { link, content }
			value: null,
		}
	},

	computed: {
		getLink() {
			if (!this.value) {
				return ''
			}

			const suffix = this.renderMode === 'content' ? '/content' : ''
			return generateUrl('/apps/tables/#/' + this.value?.type + '/' + this.value?.value + suffix)
		},
	},

	methods: {
		t,
		n,
		selectReference() {
			this.$emit('submit', this.getLink)
		},
	},

}
</script>
<style scoped lang="scss">

	.picker-content {
		width: 100%;
		min-height: 350px;
		display: flex;
		flex-direction: column;
		overflow-y: auto;
		padding: 0 16px 16px 16px;
	}

	.picker-content > h2 {
		margin: 12px 0;
		text-align: center;
	}

	h3 {
		margin: 0;
	}

	.space-B {
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	table {
		width: 100%;
	}

	table th, table td {
		border: none !important;
		width: 50%;
		padding-left: calc(var(--default-grid-baseline) * 2);
		padding-right: calc(var(--default-grid-baseline) * 2);
	}

	.radio {
		display: flex;
	}

	.radio > span {
		padding-right: calc(var(--default-grid-baseline) * 6);
	}

	.radio label .material-design-icon {
		padding-right: calc(var(--default-grid-baseline) * 1);
	}

	.select-button {
		bottom: 0;
		width: 100%;
		display: flex;
		align-items: end;
		flex-direction: column;
		position:sticky;
	}

</style>
