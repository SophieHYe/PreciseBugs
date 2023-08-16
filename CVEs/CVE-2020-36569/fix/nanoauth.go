// Package nanoauth provides a uniform means of serving HTTP/S for golang
// projects securely. It allows the specification of a certificate (or
// generates one) as well as an auth token which is checked before the request
// is processed.
package nanoauth

import (
	"crypto/subtle"
	"crypto/tls"
	"errors"
	"net"
	"net/http"
)

// Auth is a structure containing listener information
type Auth struct {
	child         http.Handler     // child is the http handler passed in
	Header        string           // Header is the authentication token's header name
	Certificate   *tls.Certificate // Certificate is the tls.Certificate to serve requests with
	ExcludedPaths []string         // ExcludedPaths is a list of paths to be excluded from being authenticated
	Token         string           // Token is the security/authentication string to validate by
}

var (
	// DefaultAuth is the default Auth object
	DefaultAuth = &Auth{}
)

func init() {
	DefaultAuth.Header = "X-NANOBOX-TOKEN"
	DefaultAuth.Certificate, _ = Generate("nanobox.io")
}

// ServeHTTP is to implement the http.Handler interface. Also let clients know
// when I have no matching route listeners
func (self *Auth) ServeHTTP(rw http.ResponseWriter, req *http.Request) {
	reqPath := req.URL.Path
	skipOnce := false

	for _, path := range self.ExcludedPaths {
		if path == reqPath {
			skipOnce = true
			break
		}
	}

	// open up for the CORS "secure" pre-flight check (browser doesn't allow devs to set headers in OPTIONS request)
	if req.Method == "OPTIONS" {
		// todo: actually check origin header to better implement CORS
		skipOnce = true
	}

	if !skipOnce {
		auth := ""
		if auth = req.Header.Get(self.Header); auth == "" {
			// check form value (case sensitive) if header not set
			auth = req.FormValue(self.Header)
		}

		if subtle.ConstantTimeCompare([]byte(auth), []byte(self.Token)) == 0 {
			rw.WriteHeader(http.StatusUnauthorized)
			return
		}
	}

	self.child.ServeHTTP(rw, req)
}

// ListenAndServeTLS starts a TLS listener and handles serving https
func (self *Auth) ListenAndServeTLS(addr, token string, h http.Handler, excludedPaths ...string) error {
	if token == "" {
		return errors.New("nanoauth: token missing")
	}
	config := &tls.Config{
		Certificates: []tls.Certificate{*self.Certificate},
	}
	config.BuildNameToCertificate()
	tlsListener, err := tls.Listen("tcp", addr, config)
	if err != nil {
		return err
	}

	self.ExcludedPaths = excludedPaths
	self.Token = token

	if h == nil {
		h = http.DefaultServeMux
	}
	self.child = h

	return http.Serve(tlsListener, self)
}

// ListenAndServe starts a normal tcp listener and handles serving http while
// still validating the auth token.
func (self *Auth) ListenAndServe(addr, token string, h http.Handler, excludedPaths ...string) error {
	if token == "" {
		return errors.New("nanoauth: token missing")
	}
	httpListener, err := net.Listen("tcp", addr)
	if err != nil {
		return err
	}

	self.ExcludedPaths = excludedPaths
	self.Token = token

	if h == nil {
		h = http.DefaultServeMux
	}
	self.child = h

	return http.Serve(httpListener, self)
}

// ListenAndServeTLS is a shortcut function which uses the default one
func ListenAndServeTLS(addr, token string, h http.Handler, excludedPaths ...string) error {
	return DefaultAuth.ListenAndServeTLS(addr, token, h, excludedPaths...)
}

// ListenAndServe is a shortcut function which uses the default one
func ListenAndServe(addr, token string, h http.Handler, excludedPaths ...string) error {
	return DefaultAuth.ListenAndServe(addr, token, h, excludedPaths...)
}
