<?php

namespace Parser;

class Condition {

    public function __construct(
        private $field,
        private $operator,
        private $value,
    ) {}

    public function getField() {
        return $this->field;
    }

    public function getOperator(){
        return $this->operator;
    }

    public function getValue(){
        return $this->value;
    }
    
}