package parser

import (
	"fmt"
	"io/ioutil"
	"os"
	"os/user"
	"strings"
)

type Query struct {
	cmd        string
	fields     []string
	paths      []string
	conditions []Condition
}

func (q *Query) Execute() ([]byte, error) {
	var paths []string
	for _, p := range q.paths {
		path, err := resolvePath(p)
		if err != nil {
			return nil, fmt.Errorf("resolve path: %w", err)
		}
		paths = append(paths, path)
	}

	for _, p := range paths {
		files, err := ioutil.ReadDir(p)
		if err != nil {
			return nil, fmt.Errorf("walk dir: %w", err)
		}
		for _, f := range files {
			fmt.Println(f.Name())
		}

	}
	/*
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
	*/
	return nil, fmt.Errorf("Not Implemented")
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
