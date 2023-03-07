package main

import (
	"fmt"
	display "ibudiallo/sqfs/display"
	"ibudiallo/sqfs/parser"
	"os"
)

type Args struct {
}

const manualTxt = `SQLFS
Query your file system using SQL

Example 1:
    > sqlfs "SELECT * FROM ~/ WHERE extension = 'sh'"

Columns available:
    owner       - file group
    group       - file group owner
    filesize    - File size in bytes
    createdt    - file creation time in seconds
    lastmod     - last modified date in seconds
    name        - file name
    extension   - file extension
    path        - file path
    *           - All fields`

func main() {
	args, err := getInput()
	if err != nil {
		fmt.Println(manualTxt)
		return
	}

	p, err := parser.New(args)
	if err != nil {
		panic(fmt.Errorf("new parser: %w", err))
	}
	node, err := p.Parse()
	if err != nil {
		panic(fmt.Errorf("parse query: %w", err))
	}
	byt, err := node.Query.Execute()
	if err != nil {
		panic(fmt.Errorf("execute query: %s", err))
	}
	disp := display.New(byt)

	disp.Render()
}

func getInput() ([]byte, error) {
	if len(os.Args) == 1 || len(os.Args) > 2 {
		return nil, fmt.Errorf("no arguments passed")
	}
	return []byte(os.Args[1]), nil
}
