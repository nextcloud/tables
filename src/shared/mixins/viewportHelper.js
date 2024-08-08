/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
XS <= 460px
x <= 640px
m <= 1024px
 */
export default {
	data() {
		return {
			isExtraSmallMobile: window.innerWidth <= 460,
			isSmallMobile: window.innerWidth <= 640,
			isMediumMobile: window.innerWidth <= 1024,
			wsize: window.innerWidth,
		}
	},

	created() {
		window.addEventListener('resize', this.updateMobileSizes)
	},
	destroyed() {
		window.removeEventListener('resize', this.updateMobileSizes)
	},
	methods: {
		updateMobileSizes() {
			this.isExtraSmallMobile = window.innerWidth <= 460
			this.isSmallMobile = window.innerWidth <= 640
			this.isMediumMobile = window.innerWidth <= 1024
			this.wsize = window.innerWidth
		},
	},
}
