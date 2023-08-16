// Pitchfork ctx defines the context that is passed through Pitchfork pertaining primarily to the logged in, selected user/group
package pitchfork

import (
	"errors"
	"fmt"
	"math"
	"net"
	"strconv"
	"strings"

	useragent "github.com/mssola/user_agent"
	i18n "github.com/nicksnyder/go-i18n/i18n"
)

// ErrLoginIncorrect is used when a login is incorrect, this to hide more specific reasons
var ErrLoginIncorrect = errors.New("Login incorrect")

// PfNewUserI, NewGroupI, PfMenuI, PfAppPermsI, PfPostBecomeI are function definitions to allow overriding of these functions by application code
type PfNewUserI func() (user PfUser)
type PfNewGroupI func() (user PfGroup)
type PfMenuI func(ctx PfCtx, menu *PfMenu)
type PfAppPermsI func(ctx PfCtx, what string, perms Perm) (final bool, ok bool, err error)
type PfPostBecomeI func(ctx PfCtx)

// PfModOptsI is the interface that is implemented by PfModOptsS allowing the latter to be extended with more details
type PfModOptsI interface {
	IsModOpts() bool
}

// PfModOptsS is the base structure used to impleent PfModOptsI
type PfModOptsS struct {
	// CLI command prefix, eg 'group wiki'
	Cmdpfx string

	// URL prefix, typically System_Get().PublicURL()
	URLpfx string

	// Path Root
	Pathroot string

	// URL root, inside the hostname, eg '/group/name/wiki/'
	URLroot string
}

// IsModOpts is a simple fakeish function to cause PfModOptsS to be of type PfModOptsI
// as it requires this function to be present, which other structures will not satisfy.
func (m PfModOptsS) IsModOpts() bool {
	return true
}

// PfModOpts can be used to easily initialize a PfModOptsS.
//
// The arguments match the variables in the PfModOpts structure.
//
// The function ensures that the web_root ends in a slash ('/').
func PfModOpts(ctx PfCtx, cmdpfx string, path_root string, web_root string) PfModOptsS {
	urlpfx := System_Get().PublicURL

	web_root = URL_EnsureSlash(web_root)

	return PfModOptsS{cmdpfx, urlpfx, path_root, web_root}
}

// PfCtx is the Context Interface.
//
// PfCtxS is the default implementation.
//
// This interface is primarily intended to allow extension by an application.

// See the individual functions in PfCtxS for per function details.
type PfCtx interface {
	GetAbort() <-chan bool
	SetAbort(abort <-chan bool)
	SetTx(tx *Tx)
	GetTx() (tx *Tx)
	Err(message string)
	Errf(format string, a ...interface{})
	Log(message string)
	Logf(format string, a ...interface{})
	Dbg(message string)
	Dbgf(format string, a ...interface{})
	Init() (err error)
	SetStatus(code int)
	GetStatus() (code int)
	SetReturnCode(rc int)
	GetReturnCode() (rc int)
	GetLoc() string
	GetLastPart() string
	Become(user PfUser)
	GetToken() (tok string)
	NewToken() (err error)
	LoginToken(tok string) (expsoon bool, err error)
	Login(username string, password string, twofactor string) (err error)
	Logout()
	IsLoggedIn() bool
	IsGroupMember() bool
	IAmGroupAdmin() bool
	IAmGroupMember() bool
	GroupHasWiki() bool
	GroupHasFile() bool
	GroupHasCalendar() bool
	SwapSysAdmin() bool
	IsSysAdmin() bool
	CheckPerms(what string, perms Perm) (ok bool, err error)
	CheckPermsT(what string, permstr string) (ok bool, err error)
	TheUser() (user PfUser)
	SelectedSelf() bool
	SelectedUser() (user PfUser)
	SelectedGroup() (grp PfGroup)
	SelectedML() (ml PfML)
	SelectedEmail() (email PfUserEmail)
	HasSelectedUser() bool
	HasSelectedGroup() bool
	HasSelectedML() bool
	SelectMe()
	SelectUser(username string, perms Perm) (err error)
	SelectGroup(gr_name string, perms Perm) (err error)
	SelectML(ml_name string, perms Perm) (err error)
	SelectEmail(email string) (err error)
	SetModOpts(opts PfModOptsI)
	GetModOpts() (opts interface{})
	PDbgf(what string, perm Perm, format string, a ...interface{})
	Out(txt string)
	Outf(format string, a ...interface{})
	OutLn(format string, a ...interface{})
	SetOutUnbuffered(obj interface{}, fun string)
	OutBuffered(on bool)
	IsBuffered() bool
	Buffered() (o string)
	GetRemote() (remote string)
	SetClient(clientip net.IP, remote string, ua string)
	GetClientIP() net.IP
	GetUserAgent() (string, string, string)
	SelectObject(obj *interface{})
	SelectedObject() (obj *interface{})
	GetLanguage() string
	SetLanguage(name string)
	GetTfunc() i18n.TranslateFunc

	// User and Group creation overrides
	NewUser() (user PfUser)
	NewUserI() (i interface{})
	NewGroup() (user PfGroup)
	NewGroupI() (i interface{})

	// Menu Overrides
	MenuOverride(menu *PfMenu)

	// Menu Related (menu.go)
	Menu(args []string, menu PfMenu) (err error)
	WalkMenu(args []string) (menu *PfMEntry, err error)
	Cmd(args []string) (err error)
	CmdOut(cmd string, args []string) (msg string, err error)
	Batch(filename string) (err error)

	// Application Data
	SetAppData(data interface{})
	GetAppData() interface{}
}

// SessionClaims describe claims for a session
type SessionClaims struct {
	JWTClaims
	UserDesc   string `json:"userdesc"`
	IsSysAdmin bool   `json:"issysadmin"`
}

// PfCtxS is the default implementation of PfCtx
type PfCtxS struct {
	abort          <-chan bool        /* Abort the request */
	status         int                /* HTTP Status code */
	returncode     int                /* Command Line return code */
	loc            string             /* Command tree location */
	output         string             /* Output buffer */
	mode_buffered  bool               /* Buffering of output in effect */
	user           PfUser             /* Authenticated User */
	is_sysadmin    bool               /* Whether the user's sysadmin priveleges are enabled */
	token          string             /* The authentication token */
	token_claims   SessionClaims      /* Parsed Token Claims */
	remote         string             /* The address of the client, including X-Forwarded-For */
	client_ip      net.IP             /* Client's IP addresses */
	ua_full        string             /* The HTTP User Agent */
	ua_browser     string             /* HTTP User Agent: Browser */
	ua_os          string             /* HTTP User Agent: Operating System */
	language       string             /* User's chosen language (TODO: Allow user to select it) */
	tfunc          i18n.TranslateFunc /* Translation function populated with current language */
	sel_user       PfUser             /* Selected User */
	sel_group      PfGroup            /* Selected Group */
	sel_ml         *PfML              /* Selected Mailing List */
	sel_email      *PfUserEmail       /* Selected User email address */
	sel_obj        *interface{}       /* Selected Object (ctx + struct only) */
	mod_opts       interface{}        /* Module Options for Messages/Wiki/Files etc */
	f_newuser      PfNewUserI         /* Create a new User */
	f_newgroup     PfNewGroupI        /* Create a new Group */
	f_menuoverride PfMenuI            /* Override a menu */
	f_appperms     PfAppPermsI        /* Application Permission Check */
	f_postbecome   PfPostBecomeI      /* Post Become() */

	// Unbuffered Output */
	outunbuf_fun string   // Function name that handles unbuffered output */
	outunbuf_obj ObjFuncI // Object where the function lives */

	// Database internal
	db_Tx *Tx // Used for database transactions

	// Menu internal values (menu.go)
	menu_walkonly bool      // Set to 'true' to indicate that only walk, do not execute; used for figuring out what arguments are needed
	menu_args     []string  // Which arguments are currently requested
	menu_menu     *PfMEntry // Current menu entry being attempted

	/* Application Data */
	appdata interface{} // Application specific data
}

// PfNewCtx allows overriding the NewCtx function, thus allowing extending PfCtx
type PfNewCtx func() PfCtx

// NewPfCtx is used to initialize a new Pitchfork Context.
//
// The various arguments are all to provide the ability to replace
// standard Pitchfork functions with application specific ones that
// likely extends the Pitchfork functionality or that carry extra details.
//
// newuser is used as a function for creating new users.
//
// newgroup is used as a function for creating new groups.
//
// menuoverride is used as a function to override menu entries.
//
// appperms is used as a function to verify application specific permissions.
//
// postbecome is used as a callback after a user has changed (eg when logging in).
//
// All overrides are optional, and will be defaulted to the Pitchfork versions
// when they are provided as 'nil'.
//
// NewPfCtx is called from the constructors of PfUI and, except for testing
// should rarely be called directly as the context is already handed to a function.
func NewPfCtx(newuser PfNewUserI, newgroup PfNewGroupI, menuoverride PfMenuI, appperms PfAppPermsI, postbecome PfPostBecomeI) PfCtx {
	if newuser == nil {
		newuser = NewPfUserA
	}

	if newgroup == nil {
		newgroup = NewPfGroup
	}

	tfunc, err := i18n.Tfunc(Config.TransDefault)
	if err != nil {
		tfunc = nil
	}

	return &PfCtxS{f_newuser: newuser,
		f_newgroup: newgroup, f_menuoverride: menuoverride, f_appperms: appperms,
		f_postbecome: postbecome,
		language:     Config.TransDefault, mode_buffered: true, tfunc: tfunc}
}

// GetAbort is used to retrieve the abort channel (as used/passed-down from the HTTP handler)
//
// This channel is used to indicate, by the HTTP library, that the HTTP client has
// disconnected and that the request can be aborted as the client will never receive
// the answer of the query.
//
// Used amongst others by the search infrastructure.
func (ctx *PfCtxS) GetAbort() <-chan bool {
	return ctx.abort
}

// SetAbort is used to set the abort channel (as used/passed-down from the HTTP handler).
//
// SetAbort is called from H_root() to configure the abort channel as passed down
// from the Golang HTTP package.
func (ctx *PfCtxS) SetAbort(abort <-chan bool) {
	ctx.abort = abort
}

// GetLanguage is used to retrieve the user-selected language setting
//
// The returned string is in the form of a RFC2616 Accept-Language header.
// Typically it will be 'en-us', or sometimes 'de', 'de-DE', 'de-CH' or 'es'.
func (ctx *PfCtxS) GetLanguage() string {
	return ctx.language
}

// SetLanguage accepts a RFC2616 style Accept-Language string
// it then uses that information to determine the best language
// to return.
func (ctx *PfCtxS) SetLanguage(name string) {
	ctx.language = name
	tfunc, err := i18n.Tfunc(name, Config.TransDefault)
	if err != nil {
		// XXX: Handle properly, this crashes the goproc based on invalid Accept-Language header
		// The panic might expose information to the enduser
		panic(err.Error())
	}
	ctx.tfunc = tfunc
}

// GetTfunc returns the translation function
func (ctx *PfCtxS) GetTfunc() i18n.TranslateFunc {
	return ctx.tfunc
}

// SetAppData can be used to set the appdata of a context.
//
// Typically this is used by an application's edition of a context to store
// itself in the pitchfork context. This given that Golang does not support
// polymorphism and thus needs a place to hide the full version of itself.
func (ctx *PfCtxS) SetAppData(appdata interface{}) {
	ctx.appdata = appdata
}

// GetAppData is used for getting application specific data inside the context.
//
// Typically this is used by an application's edition of a context to retrieve
// itself from the pitchfork context. This given that Golang does not support
// polymorphism and it needs to retrieve itself from the embedded edition of itself.
func (ctx *PfCtxS) GetAppData() interface{} {
	return ctx.appdata
}

// NewUser causes a new PfUser (or extended edition) to be created.
//
// The override for NewUser, as configured at Ctx creation time is used
// thus allowing the application specific Ctx to be returned.
func (ctx *PfCtxS) NewUser() PfUser {
	return ctx.f_newuser()
}

// NewUserI is like NewUser() but returns a generic interface */
func (ctx *PfCtxS) NewUserI() interface{} {
	return ctx.f_newuser()
}

// NewGroup causes a new PfGroup to be created by calling the
// application defined edition of a NewGroup function.
func (ctx *PfCtxS) NewGroup() PfGroup {
	return ctx.f_newgroup()
}

// NewGroupI is like NewGroup() but returns a generic interface
func (ctx *PfCtxS) NewGroupI() interface{} {
	return ctx.f_newgroup()
}

// MenuOverride is called before a menu is further processed,
// allowing entries to be modified by calling the callback.
//
// As noted, it is an optional override.
func (ctx *PfCtxS) MenuOverride(menu *PfMenu) {
	if ctx.f_menuoverride != nil {
		ctx.f_menuoverride(ctx, menu)
	}
}

// SetTx is used by the database code to select the current transaction
func (ctx *PfCtxS) SetTx(tx *Tx) {
	ctx.db_Tx = tx
}

// GetTx is used by the database code to get the current transaction
func (ctx *PfCtxS) GetTx() (tx *Tx) {
	return ctx.db_Tx
}

// GetRemote retrieves the remote address of the user/connection.
//
// The address is a IPv4 or IPv6 textual representation.
func (ctx *PfCtxS) GetRemote() (remote string) {
	return ctx.remote
}

// SetClient is used for configuring the client IP, remote address and Full User Agent strings.
//
// Typically not called from an application, but from cui's SetClientIP()
// which in turn gets called from the H_root.
//
// The clientip is a pre-parsed IP address and XFF-filtered hops.
//
// Remote contains the full IP address string (including X-Forwarded-For hops).
//
// Fullua contains the HTTP User-Agent header.
//
// This function sets the variables of the Ctx (client_ip, remote) and parses
// the Fullua (Full User-Agent) variable, storing the details in Ctx.
func (ctx *PfCtxS) SetClient(clientip net.IP, remote string, fullua string) {
	ctx.client_ip = clientip
	ctx.remote = remote

	/* Split the UA in several parts */
	ua := useragent.New(fullua)
	ctx.ua_full = fullua
	if ua != nil {
		ctx.ua_browser, _ = ua.Browser()
		ctx.ua_os = ua.OS()
	} else {
		/* Did not parse as it is the CLI */
		if clientip.IsLoopback() {
			ctx.ua_browser = "Tickly"
			ctx.ua_os = "Trident"
		} else {
			ctx.ua_browser = "unknown"
			ctx.ua_os = "unknown"
		}
	}
}

// GetClientIP is used to get the client's IP address
func (ctx *PfCtxS) GetClientIP() net.IP {
	return ctx.client_ip
}

// GetUserAgent is used for retrieving the parsed User Agent; see also SetClient()
func (ctx *PfCtxS) GetUserAgent() (string, string, string) {
	return ctx.ua_full, ctx.ua_browser, ctx.ua_os
}

// SelectObject is used by the struct code (lib/struct.go) to set the
// object that it wants to keep track of during parsing.
func (ctx *PfCtxS) SelectObject(obj *interface{}) {
	ctx.sel_obj = obj
}

// SelectedObject is used by the struct code to retrieve
// the object it is currently parsing.
func (ctx *PfCtxS) SelectedObject() (obj *interface{}) {
	return ctx.sel_obj
}

// SetModOpts allows setting the options for the wiki and file modules
func (ctx *PfCtxS) SetModOpts(opts PfModOptsI) {
	ctx.mod_opts = opts
}

// GetModOpts allows getting the options for the wiki and file modules
func (ctx *PfCtxS) GetModOpts() (opts interface{}) {
	return ctx.mod_opts
}

// Perm is used for storing the OR value of permissions
//
// Note: Keep in sync with permnames && ui/ui (convenience for all the menus there).
//
// It is used as a bitfield, hence multiple perms are possible by ORing them together.
// Check access using the CheckPerms() function.
//
// The perms use the context's sel_{user|group|ml|*} variables to check if those permissions match.
//
// Being a SysAdmin overrides almost all permissions!
//
// Change the 'false' in PDbg to 'true' to see what permission decisions are being made.
//
// Application permissions are fully handled by the application.
// See the CheckPerms function for more details.
type Perm uint64

// PERM_* define the permissions in the system.
//
// Each permission tests as true when the given condition is met.
// See the per permission desciption for what condition they test for.
//
// The permissions are listed from weak (NONE) to strong (NOBODY).
//
// Permissions can be ORed together, the strongest are tested first.
//
// Not all combinations will make sense. eg combining PERM_GUEST|PERM_USER
// means that both not-loggedin and loggedin users have access, at which
// point the check can just be replaced with PERM_NONE.
//
// Application permissions our application specific.
//
// The PERM_'s marked 'Flag' are not used for checking permissions
// but used for modifying the behavior of a menu entry.

const (
	PERM_NOTHING        Perm = 0         // Nothing / empty permissions, primarily used for initialization, should not be found in code as it indicates that the Permission was not configured and thus should normally not be used
	PERM_NONE           Perm = 1 << iota // No permissions needed (authenticated or unauthenticated is okay), typically combined with the a Flag like PERM_HIDDEN or PERM_NOSUBS
	PERM_GUEST                           // Tests that the user is not authenticated: The user is a Guest of the system; does not accept authenticated sessions
	PERM_USER                            // Tests that the user is logged in: the user has authenticated
	PERM_USER_SELF                       // Tests that the selected user matches the logged in user
	PERM_USER_NOMINATE                   // Tests that the user can nominate the selected user
	PERM_USER_VIEW                       // Tests that the user can view the selected user
	PERM_GROUP_MEMBER                    // Tests that the selected user is an active member of the selected group that can see the group
	PERM_GROUP_ADMIN                     // Tests that the selected user is an Admin of the selected group
	PERM_GROUP_WIKI                      // Tests that the selected Group has the Wiki section enabled
	PERM_GROUP_FILE                      // Tests that the selected Group has the File section enabled
	PERM_GROUP_CALENDAR                  // Tests that the selected Group has the Calendar section enabled
	PERM_SYS_ADMIN                       // Tests that the user is a System Administrator
	PERM_SYS_ADMIN_CAN                   // Can be a System Administrator
	PERM_CLI                             // Tests when the CLI option is enabled in system settings
	PERM_API                             // Tests when the API option is enabled in system settings
	PERM_OAUTH                           // Tests when the OAUTH option is enabled in system settings
	PERM_LOOPBACK                        // Tests that the connection comes from loopback (127.0.0.1 / ::1 as the Client/Remote IP address)
	PERM_HIDDEN                          // Flag: The menu option is hidden
	PERM_NOCRUMB                         // Flag: Don't add a crumb for this menu
	PERM_NOSUBS                          // Flag: No sub menus for this menu entry. See the NoSubs function for more details.
	PERM_NOBODY                          // Absolutely nobody has access (highest priority, first checked)

	// Application permissions - defined by the application
	PERM_APP_0
	PERM_APP_1
	PERM_APP_2
	PERM_APP_3
	PERM_APP_4
	PERM_APP_5
	PERM_APP_6
	PERM_APP_7
	PERM_APP_8
	PERM_APP_9
)

// permnames contains the human readable names matching the permissions
var permnames []string

// init is used to initialize permnames and verify that they are correct, at least in count
func init() {
	permnames = []string{
		"nothing",
		"none",
		"guest",
		"user",
		"self",
		"user_nominate",
		"user_view",
		"group_member",
		"group_admin",
		"group_wiki",
		"group_file",
		"group_calendar",
		"sysadmin",
		"sysadmin_can",
		"cli",
		"api",
		"oauth",
		"loopback",
		"hidden",
		"nocrumb",
		"nosubs",
		"nobody",
		"app_0",
		"app_1",
		"app_2",
		"app_3",
		"app_4",
		"app_5",
		"app_6",
		"app_7",
		"app_9",
	}

	// Verify that the correct amount of permissions is present
	max := uint64(1 << uint64(len(permnames)))
	if max != uint64(PERM_APP_9) {
		fmt.Printf("Expected %d got %d\n", max, PERM_APP_9)
		panic("Invalid permnames")
	}
}

// Shortcutted commonly used HTTP error codes
const (
	StatusOK           = 200
	StatusUnauthorized = 401
)

// Debug is a Global Debug flag, used primarily for determining if debug messages should be output. Typically toggled by flags
var Debug = false

// Init is the "constructor" for Pitchfork Contexts
func (ctx *PfCtxS) Init() (err error) {
	// Default HTTP status
	ctx.status = StatusOK

	// Default Shell Return Code to 0
	ctx.returncode = 0

	return err
}

// SetStatus can be used by a h_* function to set the status of the context.
//
// The status typically matches a HTTP error (eg StatusNotFound from golang HTTP library).
//
// The final status is flushed out during UI's Flush() time.
//
// The status code is tracked in lib instead of the UI layer to allow a generic
// status code system inside Pitchfork.
func (ctx *PfCtxS) SetStatus(code int) {
	ctx.status = code
}

// GetStatus can be used to get the status of the context.
//
// Typically only called by UI Flush(), but in theory could be used
// by an application/function to check the current error code too.
func (ctx *PfCtxS) GetStatus() (code int) {
	return ctx.status
}

// SetReturnCode is used by the CLI edition of tools to return a Shell Return Code.
func (ctx *PfCtxS) SetReturnCode(rc int) {
	ctx.returncode = rc
}

// GetReturnCode is used by the CLI edition of tools to fetch the set Shell Return Code.
//
// During UI Flush() this error code is fetched and when not-0 reported as X-ReturnCode.
func (ctx *PfCtxS) GetReturnCode() (rc int) {
	return ctx.returncode
}

// GetLoc returns where in the CLI menu system our code is located (XXX: Improve naming).
//
// This function is typically called by MenuOverrides so that they can determine
// where they are and thus what they might want to change.
func (ctx *PfCtxS) GetLoc() string {
	return ctx.loc
}

// GetLastPart is used to get the last portion of the location (XXX: Improve naming).
func (ctx *PfCtxS) GetLastPart() string {
	fa := strings.Split(ctx.loc, " ")
	return fa[len(fa)-1]
}

// Become can be used to become the given user.
//
// The context code that logs in a user uses this.
// This can be used for a 'sudo' type mechanism as is cmd/setup/sudo.go.
//
// After changing users, the PostBecome function is called when configured.
// This allows an application to for instance update state or other such
// properties when the user changes.
//
// Use sparingly and after properly checking permissions to see if
// the user is really supposed to be able to become that user.
func (ctx *PfCtxS) Become(user PfUser) {
	// Use the details from the user
	ctx.user = user

	// Select one-self
	ctx.sel_user = user

	// Post Become() hook if configured
	if ctx.f_postbecome != nil {
		ctx.f_postbecome(ctx)
	}
}

// GetToken retrieves the authentication token (JWT) provided by the user, if any
func (ctx *PfCtxS) GetToken() (tok string) {
	return ctx.token
}

// NewToken causes a new JWT websession token to be generated for loggedin users
func (ctx *PfCtxS) NewToken() (err error) {
	if !ctx.IsLoggedIn() {
		return errors.New("Not authenticated")
	}

	theuser := ctx.TheUser()

	// Set some claims
	ctx.token_claims.UserDesc = theuser.GetFullName()
	ctx.token_claims.IsSysAdmin = ctx.is_sysadmin

	username := theuser.GetUserName()

	// Create the token
	token := Token_New("websession", username, TOKEN_EXPIRATIONMINUTES, &ctx.token_claims)

	// Sign and get the complete encoded token as a string
	ctx.token, err = token.Sign()
	if err != nil {
		// Invalid token when something went wrong
		ctx.token = ""
	}

	return
}

// LoginToken can be used to log in using a token.
//
// It takes a JWT encoded as a string.
// It returns a boolean indicating if the token is going to expire soon
// (and thus indicating that a new token should be sent out to the user)
// and/or an error to indicate failure.
func (ctx *PfCtxS) LoginToken(tok string) (expsoon bool, err error) {
	// No valid token
	ctx.token = ""

	// Not a SysAdmin
	ctx.is_sysadmin = false

	// Parse the provided token
	expsoon, err = Token_Parse(tok, "websession", &ctx.token_claims)
	if err != nil {
		return expsoon, err
	}

	// Who they claim they are
	user := ctx.NewUser()
	user.SetUserName(ctx.token_claims.Subject)
	user.SetFullName(ctx.token_claims.UserDesc)
	ctx.is_sysadmin = ctx.token_claims.IsSysAdmin

	// Fetch the details
	err = user.Refresh(ctx)
	if err == ErrNoRows {
		ctx.Dbgf("No such user %q", ctx.token_claims.Subject)
		return false, errors.New("No such user")
	} else if err != nil {
		ctx.Dbgf("Fetch of user %q failed: %s", ctx.token_claims.Subject, err.Error())
		return false, err
	}

	// Looking good, become the user
	ctx.Become(user)

	// Valid Token
	ctx.token = tok

	return expsoon, nil
}

// Login can be used to login using a username, password
// and optionally, when configured, a twofactor code.
//
// A userevent is logged when this function was succesful.
func (ctx *PfCtxS) Login(username string, password string, twofactor string) (err error) {
	// The new user */
	user := ctx.NewUser()

	err = user.CheckAuth(ctx, username, password, twofactor)
	if err != nil {
		/* Log the error, so that it can be looked up in the log */
		ctx.Errf("CheckAuth(%s): %s", username, err)

		/* Overwrite the error so that we do not leak too much detail */
		err = ErrLoginIncorrect
		return
	}

	// Force generation of a new token
	ctx.token = ""

	// Not a sysadmin till they swapadmin
	ctx.is_sysadmin = false

	ctx.Become(user)

	userevent(ctx, "login")
	return nil
}

// Logout can be used to log the authenticated user out of the system.
//
// The JWT token that was previously in use is added to the JWT Invalidated list
// thus denying the further use of that token.
func (ctx *PfCtxS) Logout() {
	if ctx.token != "" {
		Jwt_invalidate(ctx.token, &ctx.token_claims)
	}

	/* Invalidate user + token */
	ctx.user = nil
	ctx.token = ""
	ctx.token_claims = SessionClaims{}
}

// IsLoggedIn can be used to check if the context has a properly logged in user.
func (ctx *PfCtxS) IsLoggedIn() bool {
	if ctx.user == nil {
		return false
	}

	return true
}

// IsGroupMember can be used to check if the selected user
// is a member of the selected group and whether the user
// can see the group.
func (ctx *PfCtxS) IsGroupMember() bool {
	if !ctx.HasSelectedUser() {
		return false
	}

	if !ctx.HasSelectedGroup() {
		return false
	}

	ismember, _, state, err := ctx.sel_group.IsMember(ctx.user.GetUserName())
	if err != nil {
		ctx.Log("IsGroupMember: " + err.Error())
		return false
	}

	if !ismember {
		return false
	}

	/* Group Admins can always select users, even when blocked */
	if ctx.IAmGroupAdmin() {
		return true
	}

	/* Normal group users, it depends on whether they can see them */
	return state.can_see
}

// IAmGroupAdmin can be used to ask if the logged in user
// is a groupadmin of the selected group.
func (ctx *PfCtxS) IAmGroupAdmin() bool {
	if !ctx.IsLoggedIn() {
		return false
	}

	if !ctx.HasSelectedGroup() {
		return false
	}

	if ctx.IsSysAdmin() {
		return true
	}

	_, isadmin, _, err := ctx.sel_group.IsMember(ctx.user.GetUserName())
	if err != nil {
		return false
	}
	return isadmin
}

// IAmGroupMember can be used to check if the logged in user is a groupmember
func (ctx *PfCtxS) IAmGroupMember() bool {
	if !ctx.IsLoggedIn() {
		return false
	}

	if !ctx.HasSelectedGroup() {
		return false
	}

	ismember, _, _, err := ctx.sel_group.IsMember(ctx.user.GetUserName())
	if err != nil {
		return false
	}
	return ismember
}

// GroupHasWiki can be used to check if the selected group has a wiki module enabled
func (ctx *PfCtxS) GroupHasWiki() bool {
	if !ctx.HasSelectedGroup() {
		return false
	}

	return ctx.sel_group.HasWiki()
}

// GroupHasFile can be used to check if the selected group has a file module enabled
func (ctx *PfCtxS) GroupHasFile() bool {
	if !ctx.HasSelectedGroup() {
		return false
	}

	return ctx.sel_group.HasFile()
}

// GroupHasCalendar can be used to check if the selected group has a calendar module enabled
func (ctx *PfCtxS) GroupHasCalendar() bool {
	if !ctx.HasSelectedGroup() {
		return false
	}

	return ctx.sel_group.HasCalendar()
}

// SwapSysAdmin swaps a user's privilege between normal user and sysadmin.
func (ctx *PfCtxS) SwapSysAdmin() bool {
	/* Not logged, can't be SysAdmin */
	if !ctx.IsLoggedIn() {
		return false
	}

	/* If they cannot be one, then do not toggle either */
	if !ctx.TheUser().CanBeSysAdmin() {
		return false
	}

	/* Toggle state: SysAdmin <> Regular */
	ctx.is_sysadmin = !ctx.is_sysadmin

	/* Force generation of a new token */
	ctx.token = ""

	return true
}

// IsSysAdmin indicates if the current user is a sysadmin
// and has swapped to it, see SwapSysAdmin.
//
// The SAR (System Administation Restrictions) are checked.
// When the SAR is enabled/configured, one can only become/be
// a sysadmin when coming from the correct IP address as
// configured in th SAR list.
func (ctx *PfCtxS) IsSysAdmin() bool {
	if !ctx.IsLoggedIn() {
		return false
	}

	/* Not a SysAdmin, easy */
	if !ctx.is_sysadmin {
		return false
	}

	sys := System_Get()

	/*
	 * SysAdmin IP Restriction in effect?
	 *
	 * Loopback (127.0.0.1 / ::1) are excluded from this restriction
	 */
	if sys.sar_cache == nil || ctx.client_ip.IsLoopback() {
		return true
	}

	/* Check all the prefixes */
	for _, n := range sys.sar_cache {
		if n.Contains(ctx.client_ip) {
			/* It is valid */
			return true
		}
	}

	/* Not in the SARestrict list */
	return false
}

// FromString can be used to parse a string into a Perm object.
//
// str can be in the formats:
//  perm1
//  perm1,perm2
//  perm1,perm2,perm3
//
// When an unknown permission is encountered, this function will return an error.
func (perm Perm) FromString(str string) (err error) {
	str = strings.ToLower(str)

	perm = PERM_NOTHING

	p := strings.Split(str, ",")
	for _, pm := range p {
		if pm == "" {
			continue
		}

		found := false
		var i uint
		i = 0
		for _, n := range permnames {
			if pm == n {
				perm += 1 << i
				found = true
				break
			}
			i++
		}

		if !found {
			err = errors.New("Unknown permission: '" + pm + "'")
			return
		}
		break
	}

	err = nil
	return
}

// String returns the string representation of a Perm.
//
// This can be used for in for instance debug output.
func (perm Perm) String() (str string) {

	for i := 0; i < len(permnames); i++ {
		p := uint64(math.Pow(float64(2), float64(i)))

		if uint64(perm)&p == 0 {
			continue
		}

		if str != "" {
			str += ","
		}

		str += permnames[i]
	}

	return str
}

/* IsPerm returns whether the provided Perm is the same Perm as given */
func (perm Perm) IsPerm(perms Perm) bool {
	return perms == perm
}

/* IsSet checks if the perm is in the given set of Perms */
func (perm Perm) IsSet(perms Perm) bool {
	return perms&perm > 0
}

// CheckPerms can verify if the given permissions string is valied for the provided Perms.
//
// One of multiple permissions can be specified by OR-ing the permissions together
// thus test from least to most to see if any of them allows access.
//
// To debug permissions, toggle the code-level switch in PDbg and PDbgf().
//
// Application permissions are tested at the end when all pitchfork permissions
// still allow it to proceed.
//
// The what parameter indicates the piece of code wanting to see the permissions
// verified, this thus primarily serves as a debug help.
func (ctx *PfCtxS) CheckPerms(what string, perms Perm) (ok bool, err error) {
	/* No error yet */
	sys := System_Get()

	ctx.PDbgf(what, perms, "Text: %s", perms.String())

	if ctx.IsLoggedIn() {
		ctx.PDbgf(what, perms, "user = %s", ctx.user.GetUserName())
	} else {
		ctx.PDbgf(what, perms, "user = ::NONE::")
	}

	if ctx.HasSelectedUser() {
		ctx.PDbgf(what, perms, "sel_user = %s", ctx.sel_user.GetUserName())
	} else {
		ctx.PDbgf(what, perms, "sel_user = ::NONE::")
	}

	if ctx.HasSelectedGroup() {
		ctx.PDbgf(what, perms, "sel_group = %s", ctx.sel_group.GetGroupName())
	} else {
		ctx.PDbgf(what, perms, "sel_group = ::NONE::")
	}

	/* Nobody? */
	if perms.IsSet(PERM_NOBODY) {
		ctx.PDbgf(what, perms, "Nobody")
		return false, errors.New("Nobody is allowed")
	}

	/* No permissions? */
	if perms.IsPerm(PERM_NOTHING) {
		ctx.PDbgf(what, perms, "Nothing")
		return true, nil
	}

	/* CLI when enabled and user is authenticated */
	if perms.IsSet(PERM_CLI) {
		ctx.PDbgf(what, perms, "CLI")
		if ctx.IsLoggedIn() && sys.CLIEnabled {
			ctx.PDbgf(what, perms, "CLI - Enabled")
			return true, nil
		} else {
			err = errors.New("CLI is not enabled")
		}
	}

	/* Loopback calls can always access the API (for tcli) */
	if perms.IsSet(PERM_API) {
		ctx.PDbgf(what, perms, "API")
		if sys.APIEnabled {
			ctx.PDbgf(what, perms, "API - Enabled")
			return true, nil
		} else {
			err = errors.New("API is not enabled")
		}
	}

	/* Is OAuth enabled? */
	if perms.IsSet(PERM_OAUTH) {
		ctx.PDbgf(what, perms, "OAuth")
		if sys.OAuthEnabled {
			ctx.PDbgf(what, perms, "OAuth - Enabled")
			return true, nil
		} else {
			err = errors.New("OAuth is not enabled")
		}
	}

	/* Loopback? */
	if perms.IsSet(PERM_LOOPBACK) {
		ctx.PDbgf(what, perms, "Loopback")
		if ctx.client_ip.IsLoopback() {
			ctx.PDbgf(what, perms, "Is Loopback")
			return true, nil
		} else {
			err = errors.New("Not a Loopback")
		}
	}

	/* User must not be authenticated */
	if perms.IsSet(PERM_GUEST) {
		ctx.PDbgf(what, perms, "Guest")
		if !ctx.IsLoggedIn() {
			ctx.PDbgf(what, perms, "Guest - Not Logged In")
			return true, nil
		}

		ctx.PDbgf(what, perms, "Guest - Logged In")
		return false, errors.New("Must not be authenticated")
	}

	/* User has to have selected themselves */
	if perms.IsSet(PERM_USER_SELF) {
		ctx.PDbgf(what, perms, "User Self")
		if ctx.IsLoggedIn() {
			ctx.PDbgf(what, perms, "User Self - Logged In")
			if ctx.HasSelectedUser() {
				ctx.PDbgf(what, perms, "User Self - Has selected user")
				if ctx.sel_user.GetUserName() == ctx.user.GetUserName() {
					/* Passed the test */
					ctx.PDbgf(what, perms, "User Self - It is me")
					return true, nil
				} else {
					ctx.PDbgf(what, perms, "User Self - Other user")
					err = errors.New("Different user selected")
				}
			} else {
				err = errors.New("No user selected")
			}
		} else {
			err = errors.New("Not Authenticated")
		}
	}

	/* User has to have selected themselves */
	if perms.IsSet(PERM_USER_VIEW) {
		ctx.PDbgf(what, perms, "User View")
		if ctx.IsLoggedIn() {
			ctx.PDbgf(what, perms, "User View - Logged In")
			if ctx.HasSelectedUser() {
				ctx.PDbgf(what, perms, "User View - Has selected user")
				if ctx.sel_user.GetUserName() == ctx.user.GetUserName() {
					/* Passed the test */
					ctx.PDbgf(what, perms, "User View - It is me")
					return true, nil
				} else {
					ok, err = ctx.sel_user.SharedGroups(ctx, ctx.user)
					if ok {
						/* Passed the test */
						ctx.PDbgf(what, perms, "User View - It is in my group")
						return true, nil
					} else {
						ctx.PDbgf(what, perms, "User View - Other user")
						err = errors.New("Different user selected")
					}
				}
			} else {
				err = errors.New("No user selected")
			}
		} else {
			err = errors.New("Not Authenticated")
		}
	}

	/* User has to be a group member + Wiki enabled */
	if perms.IsSet(PERM_GROUP_WIKI) {
		ctx.PDbgf(what, perms, "Group Wiki?")
		if ctx.GroupHasWiki() {
			ctx.PDbgf(what, perms, "HasWiki - ok")
			if ctx.IsGroupMember() {
				ctx.PDbgf(what, perms, "Group member - ok")
				return true, nil
			}
			err = errors.New("Not a group member")
		} else {
			err = errors.New("Group does not have a Wiki")
			return false, err
		}
	}

	/* User has to be a group member + File enabled */
	if perms.IsSet(PERM_GROUP_FILE) {
		ctx.PDbgf(what, perms, "Group File?")
		if ctx.GroupHasFile() {
			ctx.PDbgf(what, perms, "HasFile - ok")
			if ctx.IsGroupMember() {
				ctx.PDbgf(what, perms, "Group member - ok")
				return true, nil
			}
			err = errors.New("Not a group member")
		} else {
			err = errors.New("Group does not have a File")
			return false, err
		}
	}

	/* User has to be a group member + Calendar enabled */
	if perms.IsSet(PERM_GROUP_CALENDAR) {
		ctx.PDbgf(what, perms, "Group Calendar?")
		if ctx.GroupHasCalendar() {
			ctx.PDbgf(what, perms, "HasCalendar - ok")
			if ctx.IsGroupMember() {
				ctx.PDbgf(what, perms, "Group member - ok")
				return true, nil
			}
			err = errors.New("Not a group member")
		} else {
			err = errors.New("Group does not have a Calendar")
			return false, err
		}
	}

	/* No permissions needed */
	if perms.IsSet(PERM_NONE) {
		ctx.PDbgf(what, perms, "None")
		/* Always succeeds */
		return true, nil
	}

	/* Everything else requires a login */
	if !ctx.IsLoggedIn() {
		ctx.PDbgf(what, perms, "Not Authenticated")
		err = errors.New("Not authenticated")
		return false, err
	}

	/*
	 * SysAdmin can get away with almost anything
	 *
	 * The perms only has the PERM_SYS_ADMIN bit set for clarity
	 * that that one only has access for sysadmins
	 */
	if ctx.IsSysAdmin() {
		ctx.PDbgf(what, perms, "SysAdmin?")
		return true, nil
	}
	err = errors.New("Not a SysAdmin")

	/* User has to be authenticated */
	if perms.IsSet(PERM_USER) {
		ctx.PDbgf(what, perms, "User?")
		if ctx.IsLoggedIn() {
			ctx.PDbgf(what, perms, "User - Logged In")
			return true, nil
		}

		err = errors.New("Not Authenticated")
	}

	/* User has to be a group admin */
	if perms.IsSet(PERM_GROUP_ADMIN) {
		ctx.PDbgf(what, perms, "Group admin?")
		if ctx.IAmGroupAdmin() {
			ctx.PDbgf(what, perms, "Group admin - ok")
			return true, nil
		}

		err = errors.New("Not a group admin")
	}

	/* User has to be a group member */
	if perms.IsSet(PERM_GROUP_MEMBER) {
		ctx.PDbgf(what, perms, "Group member?")
		if ctx.IsGroupMember() {
			ctx.PDbgf(what, perms, "Group member - ok")
			return true, nil
		}

		err = errors.New("Not a group member")
	}

	/* User wants to nominate somebody (even themselves) */
	if perms.IsSet(PERM_USER_NOMINATE) {
		ctx.PDbgf(what, perms, "User Nominate")
		if ctx.IsLoggedIn() {
			ctx.PDbgf(what, perms, "User Nominate - Logged In")
			if ctx.HasSelectedUser() {
				ctx.PDbgf(what, perms, "User Nominate - User Selected")
				/* Passed the test */
				return true, nil
			} else {
				err = errors.New("No user selected")
			}
		} else {
			err = errors.New("Not Authenticated")
		}
	}

	/* Can the user become a SysAdmin? */
	if perms.IsSet(PERM_SYS_ADMIN_CAN) {
		if ctx.IsLoggedIn() {
			ctx.PDbgf(what, perms, "Sys Admin Can - Logged In")
			if ctx.TheUser().CanBeSysAdmin() {
				ctx.PDbgf(what, perms, "Sys Admin Can")
				/* Passed the test */
				return true, nil
			} else {
				err = errors.New("Can't become SysAdmin")
			}
		} else {
			err = errors.New("Not Authenticated")
		}
	}

	/* Let the App Check permissions */
	if ctx.f_appperms != nil {
		final, _ok, _err := ctx.f_appperms(ctx, what, perms)
		if final {
			return _ok, _err
		}

		/* Otherwise we ignore the result as it is not a final decision */
	}

	if err == nil {
		/* Should not happen */
		panic("Invalid permission bits")
	}

	/* Default Deny + report error */
	return false, err
}

// CheckPermsT can be used to check a Textual version of permissions.
//
// Used when the caller has the textual representation of the permissions.
func (ctx *PfCtxS) CheckPermsT(what string, permstr string) (ok bool, err error) {
	var perms Perm

	err = perms.FromString(permstr)
	if err != nil {
		return
	}

	return ctx.CheckPerms(what, perms)
}

// TheUser returns the currently selected user
func (ctx *PfCtxS) TheUser() (user PfUser) {
	/* Return a copy, not a reference */
	return ctx.user
}

// SelectedSelf checks if the logged in user and the selected user are the same.
func (ctx *PfCtxS) SelectedSelf() bool {
	return ctx.IsLoggedIn() &&
		ctx.HasSelectedUser() &&
		ctx.user.GetUserName() == ctx.sel_user.GetUserName()
}

// SelectedUser returns the selected user.
func (ctx *PfCtxS) SelectedUser() (user PfUser) {
	/* Return a copy, not a reference */
	return ctx.sel_user
}

// SelectedGroup returns the selected group.
func (ctx *PfCtxS) SelectedGroup() (grp PfGroup) {
	/* Return a copy, not a reference */
	return ctx.sel_group
}

// SelectedML returns the selected mailinglist.
func (ctx *PfCtxS) SelectedML() (ml PfML) {
	/* Return a copy, not a reference */
	return *ctx.sel_ml
}

// SelectedEmail returns the selected email address.
func (ctx *PfCtxS) SelectedEmail() (email PfUserEmail) {
	/* Return a copy, not a reference */
	return *ctx.sel_email
}

// HasSelectedUser returns whether a user was selected.
func (ctx *PfCtxS) HasSelectedUser() bool {
	return ctx.sel_user != nil
}

// HasSelectedGroup returns whether a group was selected.
func (ctx *PfCtxS) HasSelectedGroup() bool {
	return ctx.sel_group != nil
}

// HasSelectedML returns whether a mailinglist was selected.
func (ctx *PfCtxS) HasSelectedML() bool {
	return ctx.sel_ml != nil
}

// SelectMe caused the user to select themselves.
func (ctx *PfCtxS) SelectMe() {
	ctx.sel_user = ctx.user
}

// SelectUser selects the user if the given permissions are matched.
func (ctx *PfCtxS) SelectUser(username string, perms Perm) (err error) {
	ctx.PDbgf("PfCtxS::SelectUser", perms, "%q", username)

	/* Nothing to select, always works */
	if username == "" {
		ctx.sel_user = nil
		return nil
	}

	/* Selecting own user? */
	theuser := ctx.TheUser()
	if theuser != nil && theuser.GetUserName() == username {
		/* Re-use and pass no username to indicate no refresh */
		ctx.sel_user = theuser
		username = ""
	} else {
		ctx.sel_user = ctx.NewUser()
	}

	err = ctx.sel_user.Select(ctx, username, perms)
	if err != nil {
		ctx.sel_user = nil
	}

	return
}

// SelectGroup selects the group, depending on the permission bits provided.
//
// After succesfully selecting, SelectedGroup can be used to retrieve the group.
func (ctx *PfCtxS) SelectGroup(gr_name string, perms Perm) (err error) {
	ctx.PDbgf("SelectGroup", perms, "%q", gr_name)

	/* Nothing to select */
	if gr_name == "" {
		ctx.sel_group = nil
		return nil
	}

	ctx.sel_group = ctx.NewGroup()
	err = ctx.sel_group.Select(ctx, gr_name, perms)
	if err != nil {
		ctx.sel_group = nil
	}

	return
}

// SelectML selects a mailinglist depending on the permissions of the logged in user
func (ctx *PfCtxS) SelectML(ml_name string, perms Perm) (err error) {
	ctx.PDbgf("SelectUserML", perms, "%q", ml_name)

	if !ctx.HasSelectedGroup() {
		return errors.New("No group selected")
	}

	/* Nothing to select */
	if ml_name == "" {
		ctx.sel_ml = nil
		return nil
	}

	ctx.sel_ml = NewPfML()
	err = ctx.sel_ml.Select(ctx, ctx.sel_group, ml_name, perms)

	if err != nil {
		ctx.sel_ml = nil
	}

	return
}

// SelectEmail selects an email address.
//
// Users can only select their own email addresses (PERM_USER_SELF).
func (ctx *PfCtxS) SelectEmail(email string) (err error) {
	perms := PERM_USER_SELF

	ctx.PDbgf("SelectEmail", perms, "%q", email)

	/* Nothing to select */
	if email == "" {
		ctx.sel_email = nil
		return nil
	}

	/* Fetch email details */
	ctx.sel_email = NewPfUserEmail()
	err = ctx.sel_email.Fetch(email)
	if err != nil {
		/* Did not work */
		ctx.sel_email = nil
		return
	}

	/* Check Permissions */
	var ok bool
	ok, _ = ctx.CheckPerms("SelectEmail", perms)
	if !ok {
		/* Nope, no access */
		ctx.sel_email = nil
	}

	return
}

// Err allows printing error messages (syslog/stdout) with details from the context.
func (ctx *PfCtxS) Err(message string) {
	ErrA(1, message)
}

// Errf allows printing formatted error messages (syslog/stdout) with details from the context.
func (ctx *PfCtxS) Errf(format string, a ...interface{}) {
	ErrA(1, format, a...)
}

// Log allows printing log messages (syslog/stdout) with details from the context
func (ctx *PfCtxS) Log(message string) {
	LogA(1, message)
}

// Logf allows printing formatted log messages (syslog/stdout) with details from the context
func (ctx *PfCtxS) Logf(format string, a ...interface{}) {
	LogA(1, format, a...)
}

// Dbg allows printing debug messages (syslog/stdout) with details from the context
func (ctx *PfCtxS) Dbg(message string) {
	DbgA(1, message)
}

// Dbgf allows printing formatted debug messages (syslog/stdout) with details from the context
func (ctx *PfCtxS) Dbgf(format string, a ...interface{}) {
	DbgA(1, format, a...)
}

// PDbgf is used for permission debugging.
//
// It needs to be enabled with a Code level Debug option.
// Change the 'false' to 'true' and every permission decision will be listed.
// Remember: sysadmin overrules most permissions, thus test with normal user.
func (ctx *PfCtxS) PDbgf(what string, perm Perm, format string, a ...interface{}) {
	if false {
		ctx.Dbgf("Perms(\""+what+"\"/"+strconv.Itoa(int(perm))+"): "+format, a...)
	}
}

// Out can be used to print a line to the output for the context (CLI or HTTP).
//
// When buffering is disabled, the txt is directly forwarded to a special
// direct output function.
//
// When buffering is enabled, the txt is accumulatd in the output buffer.
func (ctx *PfCtxS) Out(txt string) {
	if !ctx.mode_buffered {
		/* Call the function that takes care of Direct output */
		_, err := ObjFunc(ctx.outunbuf_obj, ctx.outunbuf_fun, txt)
		if err != nil {
			ctx.Errf("Unbuffered output failed: %s", err.Error())
		}
	} else {
		/* Buffered output */
		ctx.output += txt
	}
}

// Outf can be used to let the Out string be formatted first.
func (ctx *PfCtxS) Outf(format string, a ...interface{}) {
	ctx.Out(fmt.Sprintf(format, a...))
}

// OutLn ensure that the Out outputted message ends in a newline
func (ctx *PfCtxS) OutLn(format string, a ...interface{}) {
	ctx.Outf(format+"\n", a...)
}

// SetOutUnbuffered causes the Out* functions to become unbuffered.
//
// The object and function passed in are then later used for calling
// and acually performing the output of the txt with the Out() function.
func (ctx *PfCtxS) SetOutUnbuffered(obj interface{}, fun string) {
	objtrail := []interface{}{obj}
	ok, obji := ObjHasFunc(objtrail, fun)
	if !ok {
		panic("Unbuffered function " + fun + " is missing")
	}

	ctx.outunbuf_obj = obji
	ctx.outunbuf_fun = fun
}

// OutBuffered causes the Out* functions to become buffered.
func (ctx *PfCtxS) OutBuffered(on bool) {
	if !on && ctx.outunbuf_fun == "" {
		panic("Can't enable buffered mode without unbuffered function")
	}

	ctx.mode_buffered = on
}

// IsBuffered can be used to check if output is being buffered or directly outputted.
func (ctx *PfCtxS) IsBuffered() bool {
	return ctx.mode_buffered
}

// Buffered can be used to return the buffered string.
func (ctx *PfCtxS) Buffered() (o string) {
	o = ctx.output
	ctx.output = ""
	return
}
