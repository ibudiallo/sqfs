<?php

namespace Parser;

require_once("Field.php");
require_once("Query.php");
require_once("Display.php");
require_once("File.php");
require_once("Path.php");
require_once("Condition.php");

use \Exception;

class SQLParser {

    const COMMANDS = [
        "SELECT"
    ];

    const FIELDS = [
        "permission", "links", "owner", "group", 
        "filesize", "lastmod", "name", "extension",
        "path", "type", "*",
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
        $ops = ["=", "!", "!=", "<", "=<", ">", ">=", "LIKE", ""];
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