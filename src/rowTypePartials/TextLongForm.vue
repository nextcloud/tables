<template>
	<div class="row">
		<div :class="{ mandatory: column.mandatory, 'fix-col-1': !showBigEditor, 'fix-col-4': showBigEditor }">
			<div class="row">
				<div class="fix-col-4">
					{{ column.title }}
					<Popover v-if="isMobileDevice">
						<template #trigger>
							<button class="icon-info" />
						</template>
						<p>{{ t('tables', 'There is a bug with the editor on mobile devices, that is why you see only a simple text box.') }}</p>
					</Popover>
				</div>
				<div v-if="column.textMaxLength !== -1" class="fix-col-4 p span" style="padding-bottom: 0; padding-top: 0;">
					{{ t('tables', 'length: {length}/{maxLength}', { length: localValue.length ? localValue.length : 0, maxLength: column.textMaxLength }) }}
				</div>
			</div>
		</div>
		<div :class="{ 'fix-col-2': !showBigEditor, 'fix-col-4': showBigEditor, 'space-B': !showBigEditor }" class="no-padding-on-mobile">
			<TiptapMenuBar v-if="!isMobileDevice"
				:value.sync="localValue"
				@input="updateText"
				@big="setShowBigEditor" />
			<textarea v-if="isMobileDevice" v-model="localValue" />
		</div>
		<div v-if="column.description" class="p span space-B light" :class="{ 'fix-col-1': !showBigEditor, 'fix-col-4': showBigEditor }">
			<div :class="{ 'space-L-small': !showBigEditor }" class="no-padding-on-mobile">
				{{ column.description }}
			</div>
		</div>
		<div v-if="!column.description" class="fix-col-1 p span space-B hide-s">
			&nbsp;
		</div>
	</div>
</template>

<script>
import TiptapMenuBar from '../partials/TiptapMenuBar'
import Popover from '@nextcloud/vue/dist/Components/Popover'

export default {
	name: 'TextLongForm',
	components: {
		TiptapMenuBar,
		Popover,
	},
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
			showBigEditor: false,
		}
	},
	computed: {
		isMobileDevice() {
			return (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))
		},
		localValue: {
			get() {
				return (this.value && true)
					? this.value
					: ((this.column.textDefault !== undefined)
						? this.column.textDefault
						: '')
			},
			set(v) { this.$emit('update:value', v) },
		},
	},
	methods: {
		updateText(text) {
			this.localValue = text
		},
		setShowBigEditor(v) {
			this.showBigEditor = !!v
		},
	},
}
</script>
