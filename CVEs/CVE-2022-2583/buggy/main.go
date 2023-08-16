package httpauth

import (
	"context"
	"errors"
	"github.com/dgrijalva/jwt-go"
	"github.com/ntbosscher/gobase/auth"
	"github.com/ntbosscher/gobase/auth/httpauth/oauth"
	"github.com/ntbosscher/gobase/env"
	"github.com/ntbosscher/gobase/res"
	"github.com/ntbosscher/gobase/strs"
	"io/ioutil"
	"log"
	"net/http"
	"strings"
	"time"
)

var jwtKey []byte
var IsVerbose bool

func init() {
	var err error
	jwtKey, err = ioutil.ReadFile("./.jwtkey")
	if err != nil {
		log.Println("./.jwtkey should contain 2048 random bytes. Run `go run github.com/ntbosscher/gobase/auth/httpauth/jwtgen` to automatically generate one")
		log.Fatal("failed to read required file ./.jwtkey: " + err.Error())
	}
}

type ActiveUserValidator func(ctx context.Context, user *auth.UserInfo) error

type Config struct {

	// Optional oauth config
	OAuth *oauth.Config

	// Checks user credentials on login
	CredentialChecker CredentialChecker

	// ValidateActiveUser allows you to do extra db checks when
	// we do a access token refresh
	// e.g. check if the user has been archived.
	//
	// If ValidateActiveUser returns an error, we'll assume the user is no longer valid
	// and force them to re-login
	//
	// if not set, this check will be ignored
	ValidateActiveUser ActiveUserValidator

	// POST route that will accept login requests
	// default: /api/auth/login
	LoginPath string

	// route that will accept logout requests
	// default: /api/auth/logout
	LogoutPath string

	// route/url to redirect logout requests to after they've been logged out
	// default: /
	LogoutRedirectTo string

	// POST route that will accept register requests
	// default: /api/auth/register
	RegisterPath string

	// Handler for registration requests. If a non-nil auth.UserInfo is returned
	// httpauth will setup the user session
	// if nil, register feature will be disabled
	RegisterHandler func(rq *res.Request) (*auth.UserInfo, res.Responder)

	// POST route that will accept jwt refresh requests
	// default: /api/auth/refresh
	RefreshPath string

	// default: 30 min
	AccessTokenLifeTime time.Duration

	// default: 30 days
	RefreshTokenLifeTime time.Duration

	// default: token
	AccessTokenCookieName string

	// default: refresh-token
	RefreshTokenCookieName string

	// route prefixes that don't require authentication
	PublicRoutePrefixes []string

	// exact match request paths that don't require authentication
	IgnoreRoutes []string

	// filters each request after authentication has been checked
	// default: nil
	PerRequestFilter PerRequestFilter
}

type PerRequestFilter func(ctx context.Context, r *http.Request, user *auth.UserInfo) error

func (c Config) getRefreshTokenCookieName() string {
	return strs.Coalesce(c.RefreshTokenCookieName, "refresh-token")
}

func (c Config) getAccessTokenCookieName() string {
	return strs.Coalesce(c.AccessTokenCookieName, "token")
}

func Setup(router *res.Router, config Config) *AuthRouter {
	loginPath := strs.Coalesce(config.LoginPath, defaultLoginEndpoint)
	router.Post(loginPath, loginHandler(&config))
	logoutPath := strs.Coalesce(config.LogoutPath, defaultLogoutEndpoint)
	router.Post(logoutPath, logoutHandler(&config))
	router.Get(logoutPath, logoutHandler(&config))
	refreshPath := strs.Coalesce(config.RefreshPath, defaultRefreshEndpoint)
	router.Post(refreshPath, refreshHandler(&config))

	if config.RegisterHandler != nil {
		router.Post(strs.Coalesce(config.RegisterPath, defaultRegisterEndpoint), registerHandler(&config))
	}

	config.IgnoreRoutes = append(config.IgnoreRoutes, loginPath, logoutPath, refreshPath)

	if config.OAuth != nil {
		config.IgnoreRoutes = append(config.IgnoreRoutes, config.OAuth.CallbackPath)
	}

	sessionSetter := func(rq *res.Request, user *auth.UserInfo) error {
		_, _, err := setupSession(rq, user, &config)
		return err
	}

	if config.OAuth != nil {
		oauth.Setup(router, config.OAuth, sessionSetter)
	}

	server := middleware(config)

	router.Use(func(h http.Handler) http.Handler {
		server.next = h
		return server
	})

	return &AuthRouter{
		config: &config,
		auth:   server,
		next:   router,
	}
}

func middleware(config Config) *server {

	if config.CredentialChecker == nil {
		log.Fatal("github.com/ntbosscher/gobase/auth/authhttp.Middleware(config): config requires CredentialChecker")
	}

	return &server{
		perRequestFilter:         config.PerRequestFilter,
		ignoreRoutesWithPrefixes: config.PublicRoutePrefixes,
		ignoreRoutes:             config.IgnoreRoutes,
		authHandler:              authHandler(&config),
	}
}

type server struct {
	next                     http.Handler
	perRequestFilter         PerRequestFilter
	ignoreRoutesWithPrefixes []string
	ignoreRoutes             []string
	authHandler              func(request *res.Request) (res.Responder, context.Context)
}

const defaultLoginEndpoint = "/api/auth/login"
const defaultRefreshEndpoint = "/api/auth/refresh"
const defaultLogoutEndpoint = "/api/auth/logout"
const defaultRegisterEndpoint = "/api/auth/register"

func (s *server) ServeHTTP(w http.ResponseWriter, r *http.Request) {

	ignoredRoute := false

	for _, path := range s.ignoreRoutes {
		if r.URL.Path == path {
			ignoredRoute = true
			break
		}
	}

	if !ignoredRoute {
		for _, prefix := range s.ignoreRoutesWithPrefixes {
			if strings.HasPrefix(r.URL.Path, prefix) {
				ignoredRoute = true
				break
			}
		}
	}

	err, ctx := s.authHandler(res.NewRequest(w, r))
	if err != nil {

		// attempt to authenticate, but ignore errors
		if ignoredRoute {
			s.next.ServeHTTP(w, r)
			return
		}

		err.Respond(w, r)
		return
	}

	r = r.WithContext(ctx)

	if !ignoredRoute && s.perRequestFilter != nil {
		if err := s.perRequestFilter(ctx, r, auth.Current(ctx)); err != nil {
			notAuthenticated.Respond(w, r)
			return
		}
	}

	s.next.ServeHTTP(w, r)
}

func logVerbose(err error) {
	if IsVerbose {
		log.Println(err)
	}
}

var notAuthenticated = res.NotAuthorized()

func registerHandler(config *Config) func(rq *res.Request) res.Responder {
	return func(rq *res.Request) res.Responder {
		info, response := config.RegisterHandler(rq)
		if info != nil {
			_, _, err := setupSession(rq, info, config)
			logVerbose(err)
		}

		return response
	}
}

func authHandler(config *Config) func(rq *res.Request) (res.Responder, context.Context) {
	return func(rq *res.Request) (res.Responder, context.Context) {
		tokenString := cookieOrBearerToken(rq, config.getAccessTokenCookieName())
		if tokenString == "" {
			return notAuthenticated, nil
		}

		user, err := parseJwt(tokenString)
		if err != nil {
			return res.NotAuthorized(err.Error()), nil
		}

		ctx := auth.SetUser(rq.Context(), user)
		return nil, ctx
	}
}

func parseJwt(tokenString string) (*auth.UserInfo, error) {
	user := &auth.UserInfo{}
	token, err := jwt.ParseWithClaims(tokenString, user, func(token *jwt.Token) (interface{}, error) {
		return jwtKey, nil
	})

	if err != nil {
		return nil, err
	}

	if !token.Valid {
		return nil, errors.New("invalid token")
	}

	return user, nil
}

func cookieOrBearerToken(rq *res.Request, name string) string {
	if value := rq.Cookie(name); value != "" {
		return value
	}

	bearerToken := rq.Request().Header.Get("Authorization")
	return strings.TrimPrefix(bearerToken, "Bearer ")
}

func refreshHandler(config *Config) res.HandlerFunc2 {
	return func(rq *res.Request) res.Responder {

		refreshToken := cookieOrBearerToken(rq, config.getRefreshTokenCookieName())
		if refreshToken == "" {
			return res.BadRequest("Invalid refresh token")
		}

		claims, err := parseJwt(refreshToken)
		if err != nil {
			return res.AppError("Access denied: " + err.Error())
		}

		if config.ValidateActiveUser != nil {
			if err := config.ValidateActiveUser(rq.Context(), claims); err != nil {
				return res.Redirect(config.LogoutPath)
			}
		}

		accessToken, accessTokenExpiry, err := createAccessToken(claims, config.AccessTokenLifeTime)
		if err != nil {
			return res.AppError("Failed to create access token: " + err.Error())
		}

		http.SetCookie(rq.Writer(), &http.Cookie{
			Secure:  !env.IsTesting,
			Name:    config.getAccessTokenCookieName(),
			Value:   accessToken,
			Expires: accessTokenExpiry,
			Path:    "/",
		})

		return res.Ok(map[string]interface{}{
			"accessToken": accessToken,
		})
	}
}

func setupSession(rq *res.Request, user *auth.UserInfo, config *Config) (accessToken string, refreshToken string, err error) {
	accessToken, accessTokenExpiry, err := createAccessToken(user, config.AccessTokenLifeTime)
	if err != nil {
		err = errors.New("Failed to create access token: " + err.Error())
		return
	}

	refreshToken, refreshTokenExpiry, err := createRefreshToken(user, config.RefreshTokenLifeTime)
	if err != nil {
		err = errors.New("Failed to create refresh token: " + err.Error())
		return
	}

	http.SetCookie(rq.Writer(), &http.Cookie{
		Secure:  !env.IsTesting,
		Name:    config.getAccessTokenCookieName(),
		Value:   accessToken,
		Expires: accessTokenExpiry,
		Path:    "/",
	})

	http.SetCookie(rq.Writer(), &http.Cookie{
		Secure:   !env.IsTesting,
		HttpOnly: true,
		Name:     config.getRefreshTokenCookieName(),
		Value:    refreshToken,
		Expires:  refreshTokenExpiry,
		Path:     "/",
	})

	return
}

func loginHandler(config *Config) res.HandlerFunc2 {
	return func(rq *res.Request) res.Responder {
		creds := &Credential{}
		if err := rq.ParseJSON(creds); err != nil {
			return res.BadRequest(err.Error())
		}

		user, err := config.CredentialChecker(rq.Context(), creds)
		if err != nil {
			return res.AppError(err.Error())
		}

		accessToken, refreshToken, err := setupSession(rq, user, config)
		if err != nil {
			return res.Error(err)
		}

		return res.Ok(map[string]interface{}{
			"accessToken":  accessToken,
			"refreshToken": refreshToken,
		})
	}
}

type Credential struct {
	Username string
	Password string
}

type CredentialChecker = func(context.Context, *Credential) (*auth.UserInfo, error)

func createRefreshToken(user *auth.UserInfo, lifetime time.Duration) (token string, expiry time.Time, err error) {

	if lifetime == 0 {
		expiry = time.Now().AddDate(0, 0, 30)
	} else {
		expiry = time.Now().Add(lifetime)
	}

	user.StandardClaims.ExpiresAt = expiry.Unix()

	tokenObj := jwt.NewWithClaims(jwt.SigningMethodHS256, user)
	token, err = tokenObj.SignedString(jwtKey)
	return
}

type Claims struct {
}

func createAccessToken(user *auth.UserInfo, lifetime time.Duration) (token string, expiry time.Time, err error) {

	if lifetime == 0 {
		expiry = time.Now().Add(30 * time.Minute)
	} else {
		expiry = time.Now().Add(lifetime)
	}

	user.StandardClaims.ExpiresAt = expiry.Unix()

	tokenObj := jwt.NewWithClaims(jwt.SigningMethodHS256, user)
	token, err = tokenObj.SignedString(jwtKey)
	return
}

func logoutHandler(config *Config) res.HandlerFunc2 {
	return func(rq *res.Request) res.Responder {

		http.SetCookie(rq.Writer(), &http.Cookie{
			Secure: !env.IsTesting,
			Name:   config.getAccessTokenCookieName(),
			MaxAge: -1,
			Path:   "/",
		})

		http.SetCookie(rq.Writer(), &http.Cookie{
			Secure: !env.IsTesting,
			Name:   config.getRefreshTokenCookieName(),
			MaxAge: -1,
			Path:   "/",
		})

		if config.LogoutRedirectTo == "" {
			return res.Redirect("/")
		}

		return res.Redirect(config.LogoutRedirectTo)
	}
}
