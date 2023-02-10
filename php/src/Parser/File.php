<?php

namespace Parser;

class File {
    private $name;
    private $path;
    private $extension;
    private $lastmod;
    private $createdt;
    private $owner;
    private $group;
    private $filesize;

    const VALID_FIELDS = [
        "name", "path", "extension", "lastmod",
        "createdt", "owner", "group", "filesize",
        "type",
    ];

    public function __construct(
        string $name, string $path
    ) {
        $this->name = $name;
        $this->path = $path;
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
        $fieldName = $criteria->getField();
        $field = "";
        if (!property_exists($this, $fieldName)) {
            throw new \Exception("\"$fieldName\" does not exist");
        }
        $field = $this->{$criteria->getField()};
        switch($criteria->getOperator()) {
            case "=":
                if ($field === $criteria->getValue()) {
                    return true;
                }
                break;
            case "!=":
                if ($field !== $criteria->getValue()) {
                    return true;
                }
                break;
            case "LIKE":
                    return $this->wildCardSearch($field, $criteria->getValue());
                break;
        }
        return false;
    }

    public function getField($field) {
        return $this->$field;
    }

    private function wildCardSearch($value, $token){
        // TODO: Sanitize regex
        $regex = '/^'.str_replace('%', '(.*)', $token).'$/';
        $match = null;
        preg_match($regex, $value, $match);
        return count($match) > 0;
    }
}