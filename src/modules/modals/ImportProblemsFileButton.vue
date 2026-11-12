<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcButton v-if="problemsFile"
		:href="problemsFileUrl"
		target="_blank"
		:aria-label="t('tables', 'View problems report')"
		data-cy="importResultProblemsFile">
		<template #icon>
			<IconFileReport :size="20" />
		</template>
		{{ t('tables', 'View problems report') }}
	</NcButton>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import IconFileReport from 'vue-material-design-icons/FileDelimitedOutline.vue'

export default {
	name: 'ImportProblemsFileButton',

	components: {
		NcButton,
		IconFileReport,
	},

	props: {
		problemsFile: {
			type: Object,
			default: null,
		},
	},

	computed: {
		problemsFileUrl() {
			return this.problemsFile
				? generateUrl('/f/{fileid}', { fileid: this.problemsFile.id })
				: ''
		},
	},

	methods: {
		t,
	},
}
</script>
