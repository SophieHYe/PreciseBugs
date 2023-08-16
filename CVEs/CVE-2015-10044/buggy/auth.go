/* no Credentials management needed
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

func getCredentials(request *http.Request) (userName string, password string) {
	if cookie, err := request.Cookie("Credentials"); err == nil {
		cookieValue := make(map[string]string)
		if err = cookieHandler.Decode("Credentials", cookie.Value, &cookieValue); err == nil {
			userName = cookieValue["user"]
			password = cookieValue["passwd"]
		}
	}
	return userName, password
}

func setCredentials(userName string, pw string, w http.ResponseWriter) {
	value := map[string]string{
		"user":   userName,
		"passwd": pw,
	}
	if encoded, err := cookieHandler.Encode("Credentials", value); err == nil {
		cookie := &http.Cookie{
			Name:  "Credentials",
			Value: encoded,
			Path:  "/",
		}
		http.SetCookie(w, cookie)
	}
}

func clearCredentials(w http.ResponseWriter) {
	cookie := &http.Cookie{
		Name:   "Credentials",
		Value:  "",
		Path:   "/",
		MaxAge: -1,
	}
	http.SetCookie(w, cookie)
}

func loginHandler(w http.ResponseWriter, request *http.Request) {
	user := request.FormValue("user")
	pass := request.FormValue("password")
	if user != "" && pass != "" {
		setCredentials(user, pass, w)
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
   <label for="user">User name</label><input type="text" id="user" name="user">
   <label for="password">Password</label><input type="password" id="password" name="password">
   <button type="submit">Login</button>
</form>
`
