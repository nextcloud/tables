<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<NcSelect v-model="localValue"
			:options="results"
			:clearable="true"
			label="title"
			style="width: 100%"
			@search="v => term = v">
			<template #option="props">
				<div class="icon-label-container">
					<img v-if="props.thumbnailUrl" :src="props.thumbnailUrl" width="35" height="35">
					<img v-else-if="props.icon" :src="props.icon" width="35" height="35">
					<LinkIcon v-else :size="35" />

					<div class="labels">
						<div class="multiSelectOptionLabel">
							{{ props.title }}
						</div>
						<p v-if="props.subline" class="multiSelectOptionLabel span">
							{{ props.subline }}
						</p>
					</div>
				</div>
			</template>
			<template #selected-option="props">
				<div class="icon-label-container">
					<img v-if="props.thumbnailUrl" :src="props.thumbnailUrl" width="35" height="35">
					<img v-else-if="props.icon" :src="props.icon" width="35" height="35">
					<LinkIcon v-else :size="35" />

					<div class="labels">
						<div class="multiSelectOptionLabel">
							{{ props.title }}
						</div>
						<p v-if="props.subline" class="multiSelectOptionLabel span">
							{{ props.subline }}
						</p>
					</div>
				</div>
			</template>
		</NcSelect>
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import displayError from '../../../../utils/displayError.js'
import { NcSelect } from '@nextcloud/vue'
import debounce from 'debounce'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import generalHelper from '../../../../mixins/generalHelper.js'

export default {

	components: {
		RowFormWrapper,
		NcSelect,
		LinkIcon,
	},

	mixins: [generalHelper],

	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			providers: null,
			results: [],
			term: '',
		}
	},

	computed: {
		localValue: {
			get() {
				// if we got an old value (string not object as json)
				if (!this.hasJsonStructure(this.value) && this.value !== '') {
					return {
						title: this.value,
						subline: t('tables', 'Url'),
						providerId: 'url',
						value: this.value,
					}
				}

				return JSON.parse(this.value)
			},
			set(v) {
				if (v === null) {
					v = ''
				}
				const value = JSON.stringify(v)
				this.$emit('update:value', value)
			},
		},
	},

	watch: {
		term() {
			this.debounceSubmit()
		},
	},

	beforeMount() {
		if (this.localValue === null) {
			this.localValue = this.column.textDefault
		}
	},

	mounted() {
		this.providers = this.column?.textAllowedPattern.split(',')
	},

	methods: {
		debounceSubmit: debounce(function() {
			this.loadResults()
		}, 500),

		loadResults() {
			if (this.term.length >= 3) {
				this.providers?.forEach(provider => this.loadResultsForProvider(provider, this.term))
			}
		},

		async loadResultsForProvider(providerId, term) {
			if (term === null || term === '') {
				this.results = []
				return
			}

			this.removeResultsByProviderId(providerId)

			if (providerId === 'url') {
				this.addUrlResult(term)
				return
			}

			let res = null
			try {
				res = await axios.get(generateOcsUrl('/search/providers/' + providerId + '/search?term=' + term))
			} catch (e) {
				displayError(e, t('tables', 'Could not load link provider results.'))
				return
			}
			for (const item of res.data.ocs.data.entries) {
				// remove previews for thumbnail and icons if they can not be fetched from the server
				// depending on the server configuration if previews are allowed or not
				if (item.thumbnailUrl && !await this.isUrlReachable(item.thumbnailUrl)) {
					delete item.thumbnailUrl
				}
				if (item.icon && !await this.isUrlReachable(item.icon)) {
					delete item.icon
				}

				// add needed general data
				item.providerId = providerId
				item.subline = res.data?.ocs?.data?.name
				item.value = item.resourceUrl
			}
			this.results = this.results.concat(res.data?.ocs?.data?.entries)
		},

		addUrlResult(term) {
			this.results.push({
				title: term,
				subline: t('tables', 'Url'),
				providerId: 'url',
				value: term,
			})
		},

		removeResultsByProviderId(providerId) {
			this.results = this.results.filter(item => item.providerId !== providerId)
		},
	},
}
</script>
<style scoped>

.typeSelections span {
	padding-right: 21px;
}

.multiSelectOptionLabel {
	padding-left: calc(var(--default-grid-baseline) * 2);
}

.icon-label-container {
	display: flex;
	align-items: center;
}

.labels {
	display: block;
}

</style>
