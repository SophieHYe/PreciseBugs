package main

import (
	"fmt"
	"net/http"
	"os"
	"strings"
)

// simple error checker
func checkY(err error) {
	if err != nil {
		fmt.Println(err)
		os.Exit(1)
	}
}

// will create a link into one level deeper
func linkDeeper(cwd string, link string, name string) string {
	return "<a href=\"" + cwd + "/" + link + "\">" + name + "</a>"
}

// Compose dataSourceName from components and globals
func dsn(user string, pw string, host string, port string, db string) string {
	return user + ":" + pw + "@tcp(" + host + ":" + port + ")/" + db
}

// Converts an URL into an array of strings
func url2array(r *http.Request) []string {
	path := r.URL.Path
	path = strings.TrimSpace(path)
	if strings.HasPrefix(path, "/") {
		path = path[1:]
	}
	if strings.HasSuffix(path, "/") {
		path = path[:len(path)-1]
	}
	return strings.Split(path, "/")
}
