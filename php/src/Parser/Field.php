<?php

namespace Parser;

class Field {

    public function __construct(private string $name) {}

    public function getName() {
        return $this->name;
    }

}
