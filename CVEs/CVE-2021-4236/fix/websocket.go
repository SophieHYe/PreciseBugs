package web

import (
	"encoding/json"
	"net/http"

	"github.com/gorilla/websocket"
	"github.com/julienschmidt/httprouter"
)

// Socket register a new websocket server at the given path
func (s *Server) Socket(path string, handle SocketHandle, options HandleOptions) {
	s.registerSocketEndpoint("GET", path, handle, options)
}

func (s *Server) registerSocketEndpoint(method string, path string, handle SocketHandle, options HandleOptions) {
	s.log.Debug("Register HTTP %s %s", method, path)
	s.router.Handle(method, path, s.socketHandler(handle, options))
}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func (s *Server) socketHandler(endpointHandle SocketHandle, options HandleOptions) httprouter.Handle {
	return func(w http.ResponseWriter, r *http.Request, ps httprouter.Params) {
		var userData interface{}

		if options.AuthenticateMethod != nil {
			userData = options.AuthenticateMethod(r)
			if isUserdataNil(userData) {
				if options.UnauthorizedMethod == nil {
					s.log.Warn("Rejected authenticated request")
					w.Header().Set("Content-Type", "application/json")
					w.WriteHeader(http.StatusUnauthorized)
					json.NewEncoder(w).Encode(Error{401, "Unauthorized"})
					return
				}

				options.UnauthorizedMethod(w, r)
				return
			}
		}

		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			s.log.Error("Error upgrading client for websocket connection: %s", err.Error())
			return
		}
		endpointHandle(Request{
			Params:   ps,
			UserData: userData,
			log:      s.log,
		}, WSConn{
			c: conn,
		})
		s.log.Debug("HTTP WS Request: ws://%s", r.RequestURI)
	}
}
