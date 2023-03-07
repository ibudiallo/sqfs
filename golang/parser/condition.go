package parser

import "fmt"

type Condition struct {
	field    string
	operator string
	value    string
}

func (c *Condition) GetField() string {
	return c.field
}

func (c *Condition) GetOperator() string {
	return c.operator
}

func (c *Condition) GetValue() string {
	return c.value
}

func (c Condition) String() string {
	return fmt.Sprintf("%s %s %s", c.field, c.operator, c.value)
}
