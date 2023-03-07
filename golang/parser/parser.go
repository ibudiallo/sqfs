package parser

import (
	"fmt"
	"strings"
)

type Parser struct {
	input    []byte
	size     int
	position int
}

var CMD = map[string]string{
	"SELECT": "",
}

var FIELDS = map[string]int{
	"permission": 1,
	"links":      1,
	"owner":      1,
	"group":      1,
	"filesize":   1,
	"lastmod":    1,
	"name":       1,
	"extension":  1,
	"path":       1,
	"type":       1,
	"*":          1,
}

func New(input []byte) (*Parser, error) {
	size := len(input)
	if size < 2 {
		return nil, fmt.Errorf("input is empty")
	}
	return &Parser{
		input:    input,
		size:     size,
		position: 0,
	}, nil
}

func (p *Parser) Parse() (*Node, error) {
	return p.parse()
}

func (p *Parser) parse() (*Node, error) {
	p.consumeWhitespace()
	if p.eof() {
		return nil, fmt.Errorf("empty query")
	}
	n, err := p.parseNode()
	if err != nil {
		return nil, fmt.Errorf("parse node: %w", err)
	}
	return n, nil
}

func (p *Parser) parseNode() (*Node, error) {
	q, err := p.parseQuery()
	if err != nil {
		return nil, fmt.Errorf("parse query: %w", err)
	}
	return &Node{
		Query: q,
	}, nil
}

func (p *Parser) consumeChar() string {
	c := p.nextChar()
	p.position++
	return c
}

func (p *Parser) nextChar() string {
	return fmt.Sprintf("%c", p.input[p.position])
}

func (p *Parser) eof() bool {
	return p.position >= p.size
}

func (p *Parser) consumeWhile(cond string) string {
	var ret []string
	switch cond {
	case "isWhiteSpace":
		for {
			if !p.eof() && isWhiteSpace(p.nextChar()) {
				p.consumeChar()
			} else {
				break
			}
		}
		break
	case "isToken":
		for {
			if !p.eof() && !isWhiteSpace(p.nextChar()) {
				ret = append(ret, p.consumeChar())
			} else {
				break
			}
		}
		break
	case "isInDoubleQuote":
		p.consumeChar()
		for {
			if !p.eof() && isInDoubleQuote(p.nextChar()) {
				ret = append(ret, p.consumeChar())
			} else {
				break
			}
		}
		break
	}
	return strings.Join(ret, "")
}

func (p *Parser) consumeWhitespace() {
	p.consumeWhile("isWhiteSpace")
}

func isWhiteSpace(char string) bool {
	return char == " " || char == "\n" || char == ""
}

func isInDoubleQuote(char string) bool {
	return char != "\""
}

func (p *Parser) parseQuery() (*Query, error) {
	cmd, err := p.parseCommand()
	if err != nil {
		return nil, fmt.Errorf("parse command %w", err)
	}
	fields, err := p.parseFields()
	if err != nil {
		return nil, fmt.Errorf("parse fields: %w", err)
	}
	paths, err := p.parsePaths()
	if err != nil {
		return nil, fmt.Errorf("parse paths: %w", err)
	}
	conditions, err := p.parseConditions()
	if err != nil {
		return nil, fmt.Errorf("parse conditions: %w", err)
	}
	return &Query{
		cmd:        cmd,
		fields:     fields,
		paths:      paths,
		conditions: conditions,
	}, nil
}

func (p *Parser) parseCommand() (string, error) {
	text := p.consumeWhile("isToken")
	if len(text) < 1 {
		return "", fmt.Errorf("no command token found")
	}
	if _, ok := CMD[text]; !ok {
		return "", fmt.Errorf("unknown command \"%s\"", text)
	}
	return text, nil
}

func (p *Parser) parseFields() ([]string, error) {
	var fields []string
	for {
		p.consumeWhitespace()
		if p.startWith("FROM") || p.eof() {
			break
		}
		field, err := p.parseField()
		if err != nil {
			return nil, fmt.Errorf("parse field: %w", err)
		}
		if field == "" {
			continue
		}
		fields = append(fields, field)
	}
	return fields, nil
}

func (p *Parser) parseField() (string, error) {
	name, err := p.parseFieldName()
	if err != nil {
		return "", fmt.Errorf("parse field name: %w", err)
	}
	return name, nil
}

func (p *Parser) parseFieldName() (string, error) {
	char := p.nextChar()
	switch char {
	case "\"":
		return p.consumeWhile("isInDoubleQuote"), nil
	case "*":
		p.consumeChar()
		return "*", nil
	case ",":
		p.consumeChar()
		return "", nil
	default:
		name := p.consumeWhileFunc(func(c string) bool {
			return (c != "\"" && c != " " && c != ",") || p.eof()
		})
		if _, ok := FIELDS[name]; !ok {
			return "", fmt.Errorf("unknow field name: \"%s\"", name)
		}
		return name, nil
	}
}

func (p *Parser) parsePaths() ([]string, error) {
	if !p.startWith("FROM") {
		return nil, fmt.Errorf("missing FROM paths")
	}
	p.consumeWhileFunc(func(c string) bool {
		return c != " "
	})
	var paths []string
	for {
		p.consumeWhitespace()
		if p.startWith("WHERE") || p.startWith("ON") || p.eof() {
			break
		}
		paths = append(paths, p.parsePath())
	}
	return paths, nil
}

func (p *Parser) parsePath() string {
	p.consumeWhitespace()
	if p.nextChar() == "," {
		p.consumeChar()
		p.consumeWhitespace()
	}
	path := p.consumeWhileFunc(func(c string) bool {
		return !contains([]string{" ", "", ","}, c)
	})
	return path
}

func (p *Parser) parseConditions() ([]Condition, error) {
	if !p.startWith("WHERE") || p.eof() {
		return []Condition{}, nil
	}
	p.consumeWhileFunc(func(c string) bool {
		return c != " "
	})
	p.consumeWhitespace()
	ops := []string{"=", "!", "!=", "<", "=<", ">", ">=", "LIKE"}
	var conditions []Condition

	for {
		if p.eof() {
			break
		}
		field, err := p.parseField()
		if err != nil {
			return nil, fmt.Errorf("parse condition field: %w", err)
		}
		p.consumeWhitespace()
		// TODO: Add support of no space after operator
		operator := p.consumeWhileFunc(func(c string) bool {
			return contains(ops, c)
		})
		if !contains(ops, operator) {
			return nil, fmt.Errorf("unknow operator \"%s\"", operator)
		}
		p.consumeWhitespace()
		var value string
		switch p.nextChar() {
		case "'":
			p.consumeChar()
			value = p.consumeWhileFunc(func(c string) bool {
				return c != "'"
			})
			p.consumeChar()
			break
		case "\"":
			p.consumeChar()
			value = p.consumeWhileFunc(func(c string) bool {
				return c != "\""
			})
			p.consumeChar()
			break
		default:
			value = p.consumeWhileFunc(func(c string) bool {
				return c != " " || p.eof()
			})
		}
		condition := &Condition{
			field:    field,
			operator: operator,
			value:    value,
		}

		conditions = append(conditions, *condition)
		break
	}
	return conditions, nil
}

func (p *Parser) startWith(str string) bool {
	if p.position >= p.size || p.position+len(str) >= p.size {
		// we are at the end of file
		return false
	}
	byt := p.input[p.position : len(str)+p.position]
	return string(byt) == str
}

func (p *Parser) consumeWhileFunc(fn func(string) bool) string {
	strs := []string{}
	for {
		if !p.eof() && fn(p.nextChar()) {
			strs = append(strs, p.consumeChar())
		} else {
			break
		}
	}
	return strings.Join(strs, "")
}

func contains(s []string, str string) bool {
	for _, v := range s {
		if v == str {
			return true
		}
	}

	return false
}

type Node struct {
	Query *Query
}
