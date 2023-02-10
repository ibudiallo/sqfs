<?php

namespace Parser;


class Display {

    private $displayName = [
        "path" => "Path",
        "lastmod" => "Last Mod",
        "createdt" => "Created",
        "owner" => "Owner",
        "group" => "Group",
        "name" => "Name",
        "extension" => "Ext.",
        "filesize" => "Size"
    ];

    public function __construct(
        private $fields,
        private $files
    ) { }
    
    public function print() {
        $fields = [];
        foreach($this->fields as $f) {
            switch($f->getName()) {
                case "*":
                    $all = array_keys($this->displayName);
                    $fields = array_merge($fields, $all);
                    break;
                default:
                    $fields[] = $f->getName();
                    break;
            }
        }
        
        $size = [];
        foreach($fields as $val) {
            $size[$val] = 10;
        }
        foreach($this->files as $file) {
            foreach($fields as $val) {
                $size[$val] = max($size[$val], strlen($file->getField($val))+1);
            }
        }
        $titles = [];
        foreach($fields as $val) {
            $titles[] = str_pad($this->displayName[$val], $size[$val]);
        }

        // display rows
        $rows = [];
        foreach($this->files as $file) {
            $col = [];
            foreach($fields as $val) {
                $col[] = str_pad($file->getField($val), $size[$val]);
            }
            $rows[] = implode(" ", $col);
        }

        // display titles
        echo implode(" ", $titles)."\n";

        // display rows
        echo implode("\n", $rows)."\n";
    }

    private function printFolder($files) {
        // print_r($files);
    }
}
