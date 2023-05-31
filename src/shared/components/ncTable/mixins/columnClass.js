import { Filters } from './filter.js'
import { MagicFields } from './magicFields.js'

export class AbstractColumn {

	type = null

	constructor(data) {
		this.createdAt = data.createdAt
		this.createdBy = data.createdBy
		this.id = data.id
		this.lastEditAt = data.lastEditAt
		this.lastEditBy = data.lastEditBy
		this.mandatory = data.mandatory
		this.orderWeight = data.orderWeight
		this.tableId = data.tableId
		this.title = data.title
		this.description = data.description
	}

	canSort() {
		return typeof this.sort === 'function'
	}

	getPossibleOperators() {
		return Object.values(Filters).filter(fil => fil.goodFor.includes(this.type))
	}

	getPossibleMagicFields() {
		return Object.values(MagicFields).filter(item => item.goodFor.includes(this.type))
	}

	isSearchStringFound(cellValue, cell, searchString) {
		if (cellValue != null && cellValue.toLowerCase().includes(searchString)) {
			cell.searchStringFound = true
			return true
		}
		return false
	}

	isFilterFound(filterMethod, cell) {
		if (filterMethod()) {
			cell.filterFound = true
			return true
		}
		return false
	}

}

export class AbstractNumberColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.numberDefault = data.numberDefault
	}

	default() {
		return this.numberDefault
	}

}

export class AbstractDatetimeColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.datetimeDefault = data.datetimeDefault
	}

	formatValue(value) {}

	default() {
		return this.datetimeDefault
	}

}

export class AbstractTextColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.textDefault = data.textDefault
	}

	default() {
		return this.textDefault
	}

}

export class AbstractSelectionColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.selectionDefault = data.selectionDefault
	}

	default() {
		return this.selectionDefault
	}

}
