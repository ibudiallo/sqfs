<?php

namespace Parser;

class Path {
    public function __construct(
        private string $path
    ){ }

    public function getPath() {
        return $this->path;
    }

    public function resolve() {
        $p = $this->path;
        if(substr($p, 0, 2) === "~/") {
            $p = "/home/".get_current_user()."/". substr($p, 2, );
        }
        return realpath($p);
    }
}