<?php

namespace Parser;

class Query {

    public function __construct(
        private string $name,
        private $fields,
        private $paths,
        private $condition = [],
    ) { }

    public function execute() {
        $paths = [];
        foreach($this->paths as $p) {
            $paths[] = $p->resolve();
        }
        echo "Getting files from folders ".implode(", ", $paths)." \n";
        $files = [];
        foreach($paths as $p) {
            $fh = opendir($p);
            if(!isset($files[$p])) {
                $files[$p] = [];
            }
            while(false !== ($entry = readdir($fh))) {
                if ($entry === "." || $entry === "..") {
                    continue;
                }
                $files[$p][] = $this->provisionFile($entry, $p);
            }
        };
        $filteredFiles = $this->filter($files, $this->condition);
        $dsp = new Display($this->fields, $filteredFiles);
        $dsp->print();
    }

    private function provisionFile($entry, $path) {
        return new File($entry, $path);
    }

    public function filter($folders, $condition) {
        $fs = [];
        if (empty($condition)) {
            foreach($folders as $folder) {
                foreach($folder as $file) {
                    $fs[] = $file;
                }
            }
            return $fs;
        }
        //$c = $condition[0];
        $c = array_shift($condition);
        foreach($folders as $folder) {
            foreach($folder as $file) {
                if ($file->filter($c)) {
                    $fs[] = $file;
                }
            }
        }
        return $fs;
    }
}