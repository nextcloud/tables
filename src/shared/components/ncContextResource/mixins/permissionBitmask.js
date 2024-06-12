import { PERMISSION_READ, PERMISSION_CREATE, PERMISSION_UPDATE, PERMISSION_DELETE } from '../../../constants.js'

export default {
	data() {
		return {
			PERMISSION_READ,
			PERMISSION_CREATE,
			PERMISSION_UPDATE,
			PERMISSION_DELETE,
		}
	},
	methods: {
		getPermissionBitmaskFromBools(permissionRead, permissionCreate, permissionUpdate, permissionDelete) {
			const read = permissionRead ? PERMISSION_READ : 0
			const create = permissionCreate ? PERMISSION_CREATE : 0
			const update = permissionUpdate ? PERMISSION_UPDATE : 0
			const del = permissionDelete ? PERMISSION_DELETE : 0
			return read | create | update | del
		},
	},
}
