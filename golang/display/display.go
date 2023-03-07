package display

type Display struct {
	data []byte
}

func New(data []byte) *Display {
	return &Display{
		data: data,
	}
}

func (d *Display) Render() string {
	return "Look at me"
}
