// SPDX-FileCopyrightText: 2023 froggie <legal@frogg.ie>
//
// SPDX-License-Identifier: OSL-3.0

package api

import (
	"net/http"
	"time"

	"github.com/effectindex/tripreporter/models"
	"github.com/effectindex/tripreporter/types"
	"github.com/gorilla/mux"
)

func SetupAccountEndpoints(v1 *mux.Router) {
	a1 := v1.Methods(http.MethodGet, http.MethodPatch, http.MethodDelete).Subrouter()
	a1.Use(AuthMiddleware())

	v1.HandleFunc("/account", AccountPost).Methods(http.MethodPost)
	a1.HandleFunc("/account", AccountGet).Methods(http.MethodGet)
	a1.HandleFunc("/account", AccountPatch).Methods(http.MethodPatch)
	a1.HandleFunc("/account", AccountDelete).Methods(http.MethodDelete)
	v1.HandleFunc("/account/login", AccountPostLogin).Methods(http.MethodPost)
	v1.HandleFunc("/account/validate", AccountValidate).Methods(http.MethodPost)
}

// AccountPost path is /api/v1/account
func AccountPost(w http.ResponseWriter, r *http.Request) {
	account, err := (&models.Account{Context: ctx.Context}).FromBody(r)
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	account = account.ClearImmutable()
	account.Default(account) // We don't want to let users set the ID and so on when creating an account
	account, err = account.Post()
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	// If a new user is not provided when making an account, we default to making a blank one
	if account.NewUser == nil {
		account.NewUser = &models.User{Context: ctx.Context, Unique: account.Unique}
	} else {
		account.NewUser.Unique = account.Unique
	}

	// Create the associated user for the account.
	account.NewUser, err = account.NewUser.Post()
	if err != nil {
		ctx.HandleStatus(w, r, "user: "+err.Error(), http.StatusBadRequest)
		return
	}

	// Create an auth session.
	session, err := (&models.Session{Context: ctx.Context, Unique: account.Unique}).Post()
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	expiry := time.Now().Add(time.Hour * 15) // TODO: Change this once we've implemented refreshing
	SetAuthCookie(w, types.CookieSessionID, session.Key.ID.String(), expiry)
	SetAuthCookie(w, types.CookieRefreshToken, session.Refresh, expiry)

	ctx.HandleJson(w, r, account.CopyPublic(), http.StatusCreated)
}

func AccountPostLogin(w http.ResponseWriter, r *http.Request) {
	account, err := (&models.Account{Context: ctx.Context}).FromBody(r)
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	var a1 = &models.Account{Context: ctx.Context}
	a1.FromData(account)
	a1, err = a1.Get()
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	account, err = a1.VerifyPassword(account.Password)
	if err != nil {
		ctx.HandleStatus(w, r, "Invalid username or password!", http.StatusForbidden)
		return
	}

	session, err := (&models.Session{Context: ctx.Context, Unique: account.Unique}).Post()
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	expiry := time.Now().Add(time.Hour * 15) // TODO: Change this once we've implemented refreshing
	SetAuthCookie(w, types.CookieSessionID, session.Key.ID.String(), expiry)
	SetAuthCookie(w, types.CookieRefreshToken, session.Refresh, expiry)

	ctx.HandleJson(w, r, account.CopyPublic(), http.StatusOK)
}

// AccountGet path is /api/v1/account
func AccountGet(w http.ResponseWriter, r *http.Request) {
	ctxVal, ok := ctx.GetCtxValOrHandle(w, r)
	if !ok {
		return
	}

	account, err := (&models.Account{Context: ctx.Context, Unique: models.Unique{ID: ctxVal.Account}}).Get()
	if err != nil {
		if err == types.ErrorAccountNotSpecified || err == types.ErrorAccountNotFound {
			ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		} else {
			ctx.HandleStatus(w, r, err.Error(), http.StatusInternalServerError)
		}
		return
	}

	ctx.HandleJson(w, r, account.CopyPublic(), http.StatusOK)
}

// AccountPatch path is /api/v1/account
func AccountPatch(w http.ResponseWriter, r *http.Request) {
	account, err := (&models.Account{Context: ctx.Context}).FromBody(r)
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	account = account.ClearImmutable()
	account, err = account.Patch()
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	ctx.HandleJson(w, r, account.CopyPublic(), http.StatusOK)
}

// AccountDelete path is /api/v1/account
func AccountDelete(w http.ResponseWriter, r *http.Request) {
	ctxVal, ok := ctx.GetCtxValOrHandle(w, r)
	if !ok {
		return
	}

	account, err := (&models.Account{Context: ctx.Context}).FromBody(r)
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	// Set account ID from session context
	account.Unique = models.Unique{ID: ctxVal.Account}
	account, err = account.Delete()
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	DeleteAuthCookies(w, types.CookieSessionID, types.CookieRefreshToken, types.CookieJwtToken)

	ctx.HandleJson(w, r, account.ClearAll(), http.StatusOK)
}

// AccountValidate path is /api/v1/account/validate
func AccountValidate(w http.ResponseWriter, r *http.Request) {
	account, err := (&models.Account{Context: ctx.Context}).FromBody(r)
	if err != nil {
		ctx.HandleStatus(w, r, err.Error(), http.StatusBadRequest)
		return
	}

	// Check if we have fields to validate, to avoid making an unnecessary DB GET.
	if len(account.Email) == 0 && len(account.Username) == 0 && len(account.Password) == 0 {
		ctx.HandleStatus(w, r, "Account validation data required.", http.StatusNotAcceptable)
		return
	}

	// Validate password first, because we don't need to check the DB for it.
	if len(account.Password) > 0 {
		_, err = (&models.Account{Context: ctx.Context}).ValidatePassword(account.Password, "Password")
		if err != nil {
			ctx.HandleStatus(w, r, err.Error(), http.StatusNotAcceptable)
			return
		}

		ctx.Handle(w, r, MsgOk)
		return
	}

	// Validate email
	if len(account.Email) > 0 {
		_, err = account.ValidateEmail()
		if err != nil {
			ctx.HandleStatus(w, r, err.Error(), http.StatusNotAcceptable)
			return
		}
	}

	// Validate username
	if len(account.Username) > 0 {
		_, err = account.ValidateUsername()
		if err != nil {
			ctx.HandleStatus(w, r, err.Error(), http.StatusNotAcceptable)
			return
		}
	}

	// Now that either or email or username exist and are valid, we can check if it's in use already.
	// Because we don't want to allow anyone to GET an account by its ID, we only want to copy the email and username.
	// Then, we GET the account to see if we catch any errors.
	_, err = (&models.Account{Context: ctx.Context, Email: account.Email, Username: account.Username}).Get()
	if err == nil || err != types.ErrorAccountNotFound {
		ctx.HandleStatus(w, r, "Email or username already in use!", http.StatusNotAcceptable)
		return
	}

	ctx.Handle(w, r, MsgOk)
}
