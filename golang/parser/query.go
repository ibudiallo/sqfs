package parser

import (
	"encoding/json"
	"fmt"
	"io/ioutil"
	"os"
	"os/user"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"
)

type Query struct {
	cmd        string
	fields     []string
	paths      []string
	conditions []Condition
}

type QueryFileInfo map[string]*FileInfo

func (q *Query) Execute() ([]byte, error) {
	var paths []string
	for _, p := range q.paths {
		path, err := resolvePath(p)
		if err != nil {
			return nil, fmt.Errorf("resolve path: %w", err)
		}
		paths = append(paths, path)
	}

	Files := make(QueryFileInfo)
	for _, p := range paths {
		files, err := ioutil.ReadDir(p)
		if err != nil {
			return nil, fmt.Errorf("read dir: %w", err)
		}
		for _, f := range files {
			path := p + "/" + f.Name()
			info, err := os.Stat(path)
			if err != nil {
				return nil, fmt.Errorf("file status: %w", err)
			}
			var UID string
			var GID string
			if stat, ok := info.Sys().(*syscall.Stat_t); ok {
				UID = strconv.FormatUint(uint64(stat.Uid), 10)
				GID = strconv.FormatUint(uint64(stat.Gid), 10)
			}
			userName, _ := user.LookupId(UID)
			userGroup, _ := user.LookupGroupId(GID)
			ext := filepath.Ext(path)
			if len(ext) > 0 && ext[0] == '.' {
				ext = ext[1:]
			}
			Files[path] = &FileInfo{
				dir:       f.IsDir(),
				Name:      f.Name(),
				Path:      p + "/" + f.Name(),
				Extension: ext,
				Lastmod:   f.ModTime(),
				Createdt:  f.ModTime(), // incorrect
				Owner:     userName.Username,
				Group:     userGroup.Name,
				Size:      int(f.Size()),
			}
		}
	}
	results, err := q.filter(Files, q.conditions)
	if err != nil {
		return nil, fmt.Errorf("filter files: %w", err)
	}
	byt, err := json.Marshal(results)
	if err != nil {
		return nil, fmt.Errorf("marshal results: %w", err)
	}
	fmt.Printf("%s\n\n", string(byt))
	return byt, nil
}

func (q *Query) filter(qf QueryFileInfo, conds []Condition) (QueryFileInfo, error) {
	results := make(QueryFileInfo)
	for key, file := range qf {
		if file.filter(conds) {
			results[key] = file
		}
	}
	return results, nil
}

func (q Query) String() string {
	var display []string
	display = append(display, fmt.Sprintf(`
	%s
		%s
	FROM
		%s`, q.cmd, strings.Join(q.fields, ", "), strings.Join(q.paths, ", ")))
	if len(q.conditions) > 0 {
		conds := []string{}
		for _, c := range q.conditions {
			conds = append(conds, fmt.Sprint(c))
		}
		display = append(display, fmt.Sprintf(`	WHERE
		%s`, strings.Join(conds, "\n")))
	}
	return strings.Join(display, "\n")
}

func resolvePath(mainPath string) (string, error) {
	var path []string
	if len(mainPath) > 1 && mainPath[:2] == "~/" {
		c, err := user.Current()
		if err != nil {
			return "", fmt.Errorf("get current user: %w", err)
		}
		path = append(path, "/home/"+c.Username+"/")
		mainPath = mainPath[2:]
	}
	path = append(path, mainPath)
	fullPath := strings.Join(path, "")
	if _, err := os.Stat(fullPath); os.IsNotExist(err) {
		return "", fmt.Errorf("check file exists: %w", err)
	}
	return fullPath, nil
}

type FileInfo struct {
	dir       bool
	Name      string    `json:"name"`
	Path      string    `json:"path"`
	Extension string    `json:"extension"`
	Lastmod   time.Time `json:"lastmod"`
	Createdt  time.Time `json:"createdt"`
	Owner     string    `json:"owner"`
	Group     string    `json:"group"`
	Size      int       `json:"size"`
}

func (f *FileInfo) filter(conds []Condition) bool {
	for _, cond := range conds {
		var left string
		valueType := "string"
		switch cond.field {
		case "name":
			left = f.Name
			break
		case "path":
			left = f.Path
			break
		case "extension":
			left = f.Extension
			break
		case "owner":
			left = f.Owner
			break
		case "group":
			left = f.Group
			break
		case "lastmod":
			left = f.Lastmod.String()
			valueType = "date"
			break
		case "createdt":
			left = f.Createdt.String()
			valueType = "date"
			break
		case "size":
			left = fmt.Sprintf("%d", f.Size)
			valueType = "number"
			break
		}
		if !evaluate(left, cond.value, cond.operator, valueType) {
			return false
		}
	}
	return true
}

func evaluate(left, right, operator, valType string) bool {
	switch operator {
	case "=":
		return left == right
	case ">", ">=":
		leftNum, err := strconv.ParseInt(left, 10, 64)
		if err != nil {
			panic(err)
		}
		rightNum, err := strconv.ParseInt(right, 10, 64)
		if err != nil {
			panic(err)
		}
		if operator == ">" {
			return leftNum > rightNum
		}
		return leftNum >= rightNum
	case "<":
		leftNum, err := strconv.ParseInt(left, 10, 64)
		if err != nil {
			panic(err)
		}
		rightNum, err := strconv.ParseInt(right, 10, 64)
		if err != nil {
			panic(err)
		}
		if operator == "<" {
			return leftNum < rightNum
		}
		return leftNum <= rightNum
	default:
		panic(fmt.Sprintf("unknown operator \"%s\"", operator))
	}
}
