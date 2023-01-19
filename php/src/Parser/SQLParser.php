<?php

namespace Parser;

use Exception;

class SQLParser {

    const COMMANDS = [
        "SELECT"
    ];

    const FIELDS = [
        "permission", "links", "owner", "group", 
        "filesize", "lastmod", "name", "*",
    ];

    private int $size = 0;
    private int $pos = 0;
    public function __construct(
        private string $input,
    ) {
        $this->size = strlen($input);
    }

    public function parse() {
        $nodes = $this->parseNodes();
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
            return new Query($cmd, $fields, $paths, []);
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
        throw new \Exception("Condition not implemented");
    }

    private function parseFields() {
        $fields = [];
        while(true) {
            $this->consumeWhitespace();
            if($this->startWith("FROM") || $this->eof()) {
                break;
            }
            $fields[] = $this->parseField();
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
        var_dump($nodes);
        return $nodes;
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
        private $path,
        private $condition,
    )
    { }
}

class Path {
    public function __construct(
        private string $path
    ){ }

    public function getPath() {
        return $this->path;
    }
}