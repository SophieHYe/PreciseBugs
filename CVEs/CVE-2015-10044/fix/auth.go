/* no session management needed
 * Credentials are stored at user side using secure cookies
 *
 * credits:
 * http://www.mschoebel.info/2014/03/09/snippet-golang-webapp-login-logout.html
 */

package main

import (
	"github.com/gorilla/securecookie"
	"net/http"
)

var cookieHandler = securecookie.New(
	securecookie.GenerateRandomKey(64),
	securecookie.GenerateRandomKey(32))

func getCredentials(request *http.Request) (userName string, password string, host string, port string) {
	if cookie, err := request.Cookie("Datasource"); err == nil {
		cookieValue := make(map[string]string)
		if err = cookieHandler.Decode("Datasource", cookie.Value, &cookieValue); err == nil {
			userName = cookieValue["user"]
			password = cookieValue["passwd"]
			host = cookieValue["host"]
			port = cookieValue["port"]
		}
	}
	return userName, password, host, port
}

func setCredentials( w http.ResponseWriter, userName string, pw string, host string, port string) {
	value := map[string]string{
		"user":   userName,
		"passwd": pw,
		"host": host,
		"port": port,
	}
	if encoded, err := cookieHandler.Encode("Datasource", value); err == nil {
		cookie := &http.Cookie{
			Name:  "Datasource",
			Value: encoded,
			Path:  "/",
		}
		http.SetCookie(w, cookie)
	}
}

func clearCredentials(w http.ResponseWriter) {
	cookie := &http.Cookie{
		Name:   "Datasource",
		Value:  "",
		Path:   "/",
		MaxAge: -1,
	}
	http.SetCookie(w, cookie)
}

func loginHandler(w http.ResponseWriter, request *http.Request) {
	user := request.FormValue("user")
	pass := request.FormValue("password")
	host := request.FormValue("host")
	port := request.FormValue("port")
	if user != "" && pass != "" {
		setCredentials(w, user, pass, host, port)
	}
	http.Redirect(w, request, "/", 302)
}

func logoutHandler(w http.ResponseWriter, request *http.Request) {
	clearCredentials(w)
	http.Redirect(w, request, "/", 302)
}

const loginPage = `
<h1>Login</h1>
<form method="post" action="/login">
   <label for="user">User name</label><input type="text" id="user" name="user"><br>
   <label for="password">Password</label><input type="password" id="password" name="password"><br>
   <label for="host">Host</label><input type="text" id="host" name="host" value="localhost"><br>
   <label for="port">Port</label><input type="text" id="port" name="port" value="3306"><br>
   <button type="submit">Login</button>
</form>
`
