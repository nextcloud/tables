<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Column extends Entity implements JsonSerializable {
	protected $title;
    protected $tableId;
	protected $ownership;
    protected $createdBy;
    protected $createdAt;
    protected $lastEditBy;
    protected $lastEditAt;
    protected $type;
    protected $mandatory;
    protected $prefix;
    protected $suffix;
    protected $description;
    protected $orderWeight;

    // type number
    protected $numberDefault;
    protected $numberMin;
    protected $numberMax;
    protected $numberDecimals;

    // type text
    protected $textDefault;
    protected $textAllowedPattern;
    protected $textMaxLength;
    protected $textMultiline;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('tableId', 'integer');
        $this->addType('mandatory', 'boolean');
        $this->addType('orderWeight', 'integer');

        // type number
        $this->addType('numberDecimals', 'integer');
        $this->addType('numberMin', 'float');
        $this->addType('numberMax', 'float');
        $this->addType('numberDefault', 'float');

        // type text
        $this->addType('textMultiline', 'boolean');
        $this->addType('textMaxLength', 'integer');
    }

	public function jsonSerialize(): array {
		return [
			'id'            => $this->id,
            'tableId'       => $this->tableId,
            'title'         => $this->title,
			'ownership'     => $this->ownership,
            'createdBy'     => $this->createdBy,
            'createdAt'     => $this->createdAt,
            'lastEditBy'    => $this->lastEditBy,
            'lastEditAt'    => $this->lastEditAt,
            'type'          => $this->type,
            'mandatory'      => $this->mandatory,
            'prefix'        => $this->prefix,
            'suffix'        => $this->suffix,
            'description'   => $this->description,
            'orderWeight'   => $this->orderWeight,

            // type number
            'numberDefault' => $this->numberDefault,
            'numberMin'     => $this->numberMin,
            'numberMax'     => $this->numberMax,
            'numberDecimals' => $this->numberDecimals,

            // type text
            'textDefault'   => $this->textDefault,
            'textAllowedPattern' => $this->textAllowedPattern,
            'textMaxLength' => $this->textMaxLength,
            'textMultiline' => $this->textMultiline,
        ];
	}
}
