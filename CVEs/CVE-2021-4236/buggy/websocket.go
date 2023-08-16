package web

import (
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
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			s.log.Error("Error upgrading client for websocket connection: %s", err.Error())
			return
		}
		endpointHandle(Request{
			Params: ps,
			log:    s.log,
		}, WSConn{
			c: conn,
		})
		s.log.Debug("HTTP WS Request: ws://%s", r.RequestURI)
	}
}
