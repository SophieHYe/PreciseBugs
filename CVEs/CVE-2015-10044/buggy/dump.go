package main

/* TODO
 * turn into generic functions
 */

import (
	"database/sql"
	"fmt"
	_ "github.com/go-sql-driver/mysql"
	"net/http"
	"strconv"
	"strings"
)

// Shows selection of databases at top level
func home(w http.ResponseWriter, r *http.Request) {

	user, pw := getCredentials(r)
	conn, err := sql.Open("mysql", dsn(user, pw, database))
	checkY(err)
	defer conn.Close()

	statement, err := conn.Prepare("show databases")
	checkY(err)

	rows, err := statement.Query()
	checkY(err)
	defer rows.Close()

	var n int = 1
	for rows.Next() {
		var field string
		rows.Scan(&field)
		fmt.Fprint(w, linkDeeper("", field, "DB["+strconv.Itoa(n)+"]"))
		fmt.Fprintln(w, " ", field, "<br>")
		n = n + 1
	}
}

//  Dump all tables of a database
func dumpdb(w http.ResponseWriter, r *http.Request, parray []string) {

	user, pw := getCredentials(r)
	database := parray[0]
	conn, err := sql.Open("mysql", dsn(user, pw, database))
	checkY(err)
	defer conn.Close()

	statement, err := conn.Prepare("show tables")
	checkY(err)

	rows, err := statement.Query()
	checkY(err)
	defer rows.Close()

	var n int = 1
	for rows.Next() {
		var field string
		rows.Scan(&field)
		fmt.Fprint(w, linkDeeper(r.URL.Path, field, "T["+strconv.Itoa(n)+"]"))
		fmt.Fprintln(w, "  ", field, "<br>")
		n = n + 1
	}
}

//  Dump all records of a table, one per line
func dumptable(w http.ResponseWriter, r *http.Request, parray []string) {

	user, pw := getCredentials(r)
	database := parray[0]
	table := parray[1]

	conn, err := sql.Open("mysql", dsn(user, pw, database))
	checkY(err)
	defer conn.Close()

	statement, err := conn.Prepare("select * from ?")
	checkY(err)

	rows, err := statement.Query(table)
	checkY(err)
	defer rows.Close()

	cols, err := rows.Columns()
	checkY(err)
	fmt.Fprintln(w, "<p>"+"# "+strings.Join(cols, " ")+"</p>")

	/*  credits:
	 * 	http://stackoverflow.com/questions/19991541/dumping-mysql-tables-to-json-with-golang
	 * 	http://go-database-sql.org/varcols.html
	 */

	raw := make([]interface{}, len(cols))
	val := make([]interface{}, len(cols))

	for i := range val {
		raw[i] = &val[i]
	}

	var n int = 1
	for rows.Next() {

		fmt.Fprint(w, linkDeeper(r.URL.Path, strconv.Itoa(n), strconv.Itoa(n)))
		err = rows.Scan(raw...)
		checkY(err)

		for _, col := range val {
			if col != nil {
				fmt.Fprintf(w, "%s ", string(col.([]byte)))
			}
		}
		fmt.Fprintln(w, "<br>")
		n = n + 1
	}
}

// Dump all fields of a record, one column per line
func dumprecord(w http.ResponseWriter, r *http.Request, parray []string) {

	database := parray[0]
	table := parray[1]
	rec, err := strconv.Atoi(parray[2])
	checkY(err)

	user, pw := getCredentials(r)
	conn, err := sql.Open("mysql", dsn(user, pw, database))
	checkY(err)
	defer conn.Close()

	statement, err := conn.Prepare("select * from ?")
	checkY(err)

	rows, err := statement.Query(table)
	checkY(err)
	defer rows.Close()

	columns, err := rows.Columns()
	checkY(err)

	raw := make([]interface{}, len(columns))
	val := make([]interface{}, len(columns))

	for i := range val {
		raw[i] = &val[i]
	}

	var n int = 1

rowLoop:
	for rows.Next() {

		// unfortunately we have to iterate up to row of interest
		if n == rec {
			err = rows.Scan(raw...)
			checkY(err)

			fmt.Fprintln(w, "<p>")
			for i, col := range val {
				if col != nil {
					fmt.Fprintln(w, columns[i], ":", string(col.([]byte)), "<br>")
				}
			}
			fmt.Fprintln(w, "</p>")
			break rowLoop
		}
		n = n + 1
	}
}
