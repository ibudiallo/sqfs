<?php

namespace Parser;

use Exception;

class SQLParser {

    const COMMANDS = [
        "SELECT"
    ];

    const FIELDS = [
        "permission", "links", "owner", "group", 
        "filesize", "lastmod", "name", "extension",
        "path", "*",
    ];

    private int $size = 0;
    private int $pos = 0;
    public function __construct(
        private string $input,
    ) {
        $this->size = strlen($input);
    }

    public function parse() {
        return $this->parseNodes();
    }

    private function nextChar() {
        return $this->input[$this->pos];
    }

    private function startWith(string $str) : bool {
        $cur = substr($this->input, $this->pos);
        return strpos($cur, $str) === 0;
    }

    private function eof() {
        return $this->pos >= $this->size;
    }

    private function consumeChar() {
        $cur = $this->nextChar();
        $this->pos++;
        return $cur;
    }

    private function consumeWhile($fn) {
        $result = [];
        if (is_string($fn)) {
            while(!$this->eof() && $this->$fn($this->nextChar())) {
                $result[] = $this->consumeChar();
            }
        } elseif (is_callable($fn)) {
            while(!$this->eof() && $fn($this->nextChar())) {
                $result[] = $this->consumeChar();
            }
        }
        return implode("", $result);
    }

    private function consumeWhitespace() {
        $this->consumeWhile("isWhitespace");
    }

    private function isWhitespace($char) {
        return $char === " " || $char === "\n" || $char === "";
    }

    private function parseCommandName() {
        $fullname = $this->consumeWhile(function($c) {
            return $c !== " ";
        });
        $upper = strtoupper($fullname);
        if (!in_array($upper, self::COMMANDS)) {
            throw new \Exception("invalid command \"$upper\"", 1);
        }
        return $upper;
    }

    private function isTagName($c) {
        $match = [];
        preg_match('/[a-zA-Z0-9]/',$c, $match);
        return count($match) > 0;
    }

    private function parseNode() {
        return $this->nextChar() === "/"
            ? $this->parseComment()
            : $this->parseQuery();
    }

    private function parseComment() {

    }

    private function parseQuery() {
        $cmd = $this->parseCommandName();
        $fields = $this->parseFields();
        $paths = $this->parsePaths();
        
        $this->consumeWhitespace();
        if ($this->eof()) {
            return new Query($cmd, $fields, $paths, null);
        }
        $conditions = $this->parseConditions();
        return new Query($cmd, $fields, $paths, $conditions);
    }

    private function parseField() {
        $name = $this->parseFieldName();
        return new Field($name);
    }

    private function parseFieldName() {
        $char = $this->nextChar();
        switch($char) {
            case "\"":
                // quoted value
                $name = $this->consumeWhile(function($c) {
                    return $c === "\"" || $this->eof();
                });
                return $name;
                break;
            case "*":
                $this->consumeChar();
                return "*";
                break;
            case ",":
                $this->consumeChar();
                return "";
                break;
            default:
                $name = $this->consumeWhile(function($c) {
                    return ($c !== "\"" && $c !== " " && $c !== ",") || $this->eof();
                });
                if(in_array($name, self::FIELDS)) {
                    return $name;
                }
                throw new \Exception("unknown field name \"$name\"");
                break;
        }
        throw new \Exception("unknown field name");
    }

    private function parseAttrValue() {
        $openquote = $this->consumeChar();
        $this->assertNot($openquote === '"' || $openquote === "'", "missing opening quote");
        $value = $this->consumeWhile(function($c) use ($openquote) {
            return $c !== $openquote;
        });
        $this->assertNot($this->consumeChar() === $openquote, "missing closing quote");
        return $value;
    }

    private function parsePaths() {
        if (!$this->startWith("FROM")) {
            throw new \Exception("Missing FROM path");
        }
        $this->consumeWhile(function($c) {
            return $c !== " ";
        });
        $paths = [];
        while(true) {
            $this->consumeWhitespace();
            if($this->startWith("WHERE") || $this->startWith("ON") || $this->eof()) {
                break;
            }
            $paths[] = $this->parsePath();
        }
        return $paths;
    }

    private function parsePath() {
        $this->consumeWhitespace();
        if ($this->nextChar() === ",") {
            $this->consumeChar();
            $this->consumeWhitespace();
        }
        //echo $this->nextChar();exit;
        $path = $this->consumeWhile(function($c) {
            return !in_array($c, [" ", "", ","]);
        });
        return new Path($path);
    }

    private function parseConditions() {
        if (!$this->startWith("WHERE")) {
            throw new \Exception("Missing Where");
        }
        $this->consumeWhile(function($c) {
            return $c !== " ";
        });
        $this->consumeWhitespace();

        $seps = ["=", "!", "<", ">", " "];
        $ops = ["=", "!", "<", ">", "LIKE"];
        $conditions = [];
        while(true) {
            if ($this->eof()) {
                break;
            }
            $field = $this->consumeWhile(function($c) use ($seps) {
                return !in_array($c, $seps);
            });
            $this->consumeWhitespace();

            // TODO: Add support of no space after operator
            $operator = $this->consumeWhile(function($c) {
                return $c !== " ";
            });
            
            if (!in_array($operator, $ops)) {
                throw new \Exception("unknown operator \"$operator\"");
            }
            $this->consumeWhitespace();
            $value = "";
            switch($this->nextChar()) {
                case "'":
                    $this->consumeChar();
                    $value = $this->consumeWhile(function($c) {
                        return $c !== "'";
                    });
                    $this->consumeChar();
                    break;
                case "\"":
                    $this->consumeChar();
                    $value = $this->consumeWhile(function($c) {
                        return $c !== "\"";
                    });
                    $this->consumeChar();
                    break;
                default:
                    $value = $this->consumeWhile(function($c) {
                        return $c !== " " || $this->eof();
                    });
                    break;
            }
            $conditions[] = new Condition($field, $operator, $value);
        }
        return $conditions;
    }

    private function parseFields() {
        $fields = [];
        while(true) {
            $this->consumeWhitespace();
            if($this->startWith("FROM") || $this->eof()) {
                break;
            }
            $f = $this->parseField();
            if ($f->getName() !== "") {
                $fields[] = $f;
            }
        }
        return $fields;
    }

    private function parseNodes() {
        $nodes = [];
        while(true) {
            $this->consumeWhitespace();
            if($this->eof()) {
                break;
            }
            $nodes[] = $this->parseNode();
        }
        return $nodes[0];
    }

    private function assertNot($bool, $msg = "error") {
        if(!$bool) {
            throw new \Exception($msg);
        }
    }

}

class Field  {

    public function __construct(private string $name) {}

    public function getName() {
        return $this->name;
    }

}


class Query {

    public function __construct(
        private string $name,
        private $fields,
        private $paths,
        private $condition,
    )
    { }

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
        $c = $condition[0];
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

class File {
    private $extension;
    private $lastmod;
    private $createdt;
    private $owner;
    private $group;
    private $filesize;

    public function __construct(
        private $name, private $path
    ) {
        $fullpath = implode("/", [$path, $name]);
        $this->createdt = date("Y-m-d H:i:s", (int) (filectime($fullpath)));
        $this->lastmod = date("Y-m-d H:i:s", (int) (filemtime($fullpath)));
        $this->owner = posix_getpwuid(fileowner($fullpath))["name"];
        $this->group = posix_getpwuid(filegroup($fullpath))["name"];
        $this->filesize = filesize($fullpath);
        $parts = explode(".", $name);
        $this->extension = end($parts);
    }

    public function filter($criteria) {
        $field = $this->{$criteria->getField()};
        switch($criteria->getOperator()) {
            case "=":
                if ($field === $criteria->getValue()) {
                    return true;
                }
                break;
        }
    }

    public function getField($field) {
        return $this->$field;
    }
}

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
                    $all = [
                        "path", "lastmod", "createdt", "owner",
                        "group", "name", "extension",
                    ];
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
        $title = [];
        foreach($fields as $val) {
            $title[] = str_pad($this->displayName[$val], $size[$val]);
        }
        // display titles
        echo implode(" ", $title)."\n";

        // display rows
        $rows = [];
        foreach($this->files as $file) {
            $col = [];
            foreach($fields as $val) {
                $col[] = str_pad($file->getField($val), $size[$val]);
            }
            $rows[] = implode(" ", $col);
        }
        echo implode("\n", $rows)."\n";
        // print_r($size);
    }

    private function printFolder($files) {
        // print_r($files);
    }
}

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