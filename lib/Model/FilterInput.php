<?php

namespace OCA\Tables\Model;

use OCP\IRequest;

// TODO: This is a protoype. declare and copyright is missing and proper logic

class FilterInput {
	public array $filter;
	public function __construct(IRequest $request) {
		$value = $request->getParam('filter', '[]');
		$this->filter = json_decode($value,true) ?? [];
	}
}
