package main

import (
	"database/sql"
	"fmt"
	"log"
	"net/http"

	"github.com/gorilla/mux"
	_ "github.com/lib/pq"
)

var servport = ":6862"

func failOnError(err error, msg string) {
	if err != nil {
		log.Fatalf("[X] %s, %s", msg, err)
		panic(fmt.Sprintf("%s, %s", msg, err))
	}
}

func main() {
	log.Println("[i] Server started")

	// Connect to DB
	db, err := sql.Open("postgres", "user=appread dbname='quantifiedSelf' sslmode=disable")
	failOnError(err, "Error connecting to database")
	defer db.Close()

	http.HandleFunc("/data/all/", func(w http.ResponseWriter, r *http.Request) {
		// TODO: Allow filtering via URL
		// Get rows from DB
		var output string
		err := db.QueryRow(`SELECT json_agg(r) FROM (SELECT * FROM trello.cards) r;`).Scan(&output)
		if err != nil {
			log.Println("Error retriving from DB, ", err)
			w.WriteHeader(http.StatusInternalServerError)
			fmt.Fprintln(w, "Error retriving from DB, ", err)
			return
		}

		// Print out returned
		fmt.Fprint(w, output)
	})

	// Restful handler
	r := mux.NewRouter()
	r.HandleFunc("/api", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintln(w, "dla;jfkdlsajflkdsa;jfk;ldsajfklds;a")
	})

	r.HandleFunc("/api/totals/last/{num}", func(w http.ResponseWriter, r *http.Request) {
		// Grab vars
		vars := mux.Vars(r)

		var output string
		// This is bad... don't do this.... omg
		query := `SELECT json_agg(r) FROM (select EXTRACT(epoch FROM day) as day, end_of_day_total from trello.dailytallies order by day DESC limit $1) r;`
		err := db.QueryRow(query, vars["num"]).Scan(&output)

		if err != nil {
			log.Println("Error retriving from DB, ", err)
			w.WriteHeader(http.StatusInternalServerError)
			fmt.Fprintln(w, "Error retriving from DB, ", err)
			return
		}

		// Print out returned
		w.Header().Set("Content-Type", "application/json")
		fmt.Fprint(w, output)
	})

	r.HandleFunc("/api/diffs/last/{num}", func(w http.ResponseWriter, r *http.Request) {
		// Grab vars
		vars := mux.Vars(r)

		var output string
		// This is bad... don't do this.... omg
		query := `SELECT json_agg(r) FROM (select EXTRACT(epoch FROM day) as day, up_count, down_count, finished_count from trello.dailytallies order by day DESC limit $1) r;`
		err := db.QueryRow(query, vars["num"]).Scan(&output)

		if err != nil {
			log.Println("Error retriving from DB, ", err)
			w.WriteHeader(http.StatusInternalServerError)
			fmt.Fprintln(w, "Error retriving from DB, ", err)
			return
		}

		// Print out returned
		w.Header().Set("Content-Type", "application/json")
		fmt.Fprint(w, output)
	})
	r.PathPrefix("/").Handler(http.FileServer(http.Dir("../ui")))
	http.Handle("/", r)

	// Die gracefully
	// killchan := make(chan os.Signal)
	// signal.Notify(killchan, os.Interrupt, os.Kill)

	log.Println("[i] Serving on ", servport, "\n\tWaiting...")

	log.Fatal(http.ListenAndServe(servport, nil))
	// <-killchan

	log.Println("[i] Shutting down...")
}
