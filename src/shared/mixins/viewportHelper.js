/*
XS <= 460px
x <= 640px
m <= 1024px
 */

export default {
	computed: {
		isExtraSmallMobile() {
			return window.innerWidth <= 460
		},
		isSmallMobile() {
			return window.innerWidth <= 640
		},
		isMediumMobile() {
			return window.innerWidth <= 1024
		},
	},
}
