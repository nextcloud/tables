/*
 * @copyright Copyright (c) 2020 Florian Steffens
 *
 * @author Florian Steffens <flost-online@mailbox.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

Vue.use(Vuex)

// eslint-disable-next-line import/no-named-as-default-member
export default new Vuex.Store({
	state: {
		tablesLoading: false,
		tables: [],
		activeTableId: null,
	},
	getters: {
		activeTable(state) {
			if (state.tables && state.tables.filter(item => item.id === state.activeTableId).length > 0) { return state.tables.filter(item => item.id === state.activeTableId)[0] }
			return null
		},
	},
	mutations: {
		setTablesLoading(state, value) {
			state.tablesLoading = !!(value)
		},
		setActiveTableId(state, tableId) {
			// console.debug('set activeTableId in store', tableId)
			state.activeTableId = tableId || null
		},
		setTables(state, tables) {
			state.tables = tables
		},
	},
	actions: {
		async loadTablesFromBE({ commit }) {
			commit('setTablesLoading', true)
			try {
				const response = await axios.get(generateUrl('/apps/tables/table'))
				commit('setTables', response.data)
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
			commit('setTablesLoading', false)
		},
	},
})
