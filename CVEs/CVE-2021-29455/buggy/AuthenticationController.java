package za.org.grassroot.webapp.controller.rest.authentication;

import io.swagger.annotations.Api;
import io.swagger.annotations.ApiOperation;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.validator.routines.EmailValidator;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.core.env.Environment;
import org.springframework.core.env.Profiles;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.util.StringUtils;
import org.springframework.web.bind.annotation.*;
import za.org.grassroot.core.GrassrootApplicationProfiles;
import za.org.grassroot.core.domain.User;
import za.org.grassroot.core.dto.UserDTO;
import za.org.grassroot.core.enums.UserInterfaceType;
import za.org.grassroot.core.enums.VerificationCodeType;
import za.org.grassroot.core.util.InvalidPhoneNumberException;
import za.org.grassroot.core.util.PhoneNumberUtil;
import za.org.grassroot.integration.authentication.CreateJwtTokenRequest;
import za.org.grassroot.integration.authentication.JwtService;
import za.org.grassroot.integration.authentication.JwtType;
import za.org.grassroot.services.async.AsyncUserLogger;
import za.org.grassroot.services.exception.InvalidOtpException;
import za.org.grassroot.services.exception.NoSuchUserException;
import za.org.grassroot.services.exception.UserExistsException;
import za.org.grassroot.services.exception.UsernamePasswordLoginFailedException;
import za.org.grassroot.services.user.PasswordTokenService;
import za.org.grassroot.services.user.UserManagementService;
import za.org.grassroot.services.user.UserRegPossibility;
import za.org.grassroot.webapp.controller.rest.Grassroot2RestController;
import za.org.grassroot.webapp.enums.RestMessage;
import za.org.grassroot.webapp.model.rest.AuthorizationResponseDTO;
import za.org.grassroot.webapp.model.rest.AuthorizedUserDTO;
import za.org.grassroot.webapp.model.rest.wrappers.ResponseWrapper;
import za.org.grassroot.webapp.util.RestUtil;

import java.util.Collections;
import java.util.List;

@RestController @Grassroot2RestController @Slf4j
@Api("/v2/api/auth")
@RequestMapping("/v2/api/auth")
public class AuthenticationController {

    private static final Logger logger = LoggerFactory.getLogger(AuthenticationController.class);

    private static final List<UserInterfaceType> alphaInterfaces = Collections.singletonList(UserInterfaceType.ANDROID_2);

    private final JwtService jwtService;
    private final PasswordTokenService passwordTokenService;
    private final UserManagementService userService;
    private final AsyncUserLogger userLogger;
    private final Environment environment;

    @Autowired
    public AuthenticationController(JwtService jwtService, PasswordTokenService passwordTokenService,
                                    UserManagementService userService, AsyncUserLogger userLogger, Environment environment) {
        this.jwtService = jwtService;
        this.passwordTokenService = passwordTokenService;
        this.userService = userService;
        this.userLogger = userLogger;
        this.environment = environment;
    }

    private User findExistingUser(String username) {
        boolean isPhoneNumber = PhoneNumberUtil.testInputNumber(username);
        if (!isPhoneNumber && !EmailValidator.getInstance().isValid(username)) {
            logger.error("got a bad username, : {}", username);
            throw new NoSuchUserException("Invalid format, neither phone nor email: " + username);
        }
        User user = userService.findByUsernameLoose(username);
        if (user == null) {
            throw new NoSuchUserException("No user with phone or email: " + username);
        }
        return user;
    }

    @ExceptionHandler(NoSuchUserException.class)
    public ResponseEntity noSuchUserResponse() {
        return RestUtil.errorResponse(RestMessage.INVALID_MSISDN);
    }

    @RequestMapping(value = "/register", method = RequestMethod.POST)
    @ApiOperation(value = "Start new user registration using username, phone number and password", notes = "Short lived token is returned as a string in the 'data' property")
    public ResponseEntity<ResponseWrapper> register(@RequestParam("phoneNumber") String phoneNumber,
                                                    @RequestParam("displayName") String displayName,
                                                    @RequestParam("password") String password,
                                                    @RequestParam(required = false) UserInterfaceType type) {
        try {
            if (!ifExists(phoneNumber)) {
                phoneNumber = PhoneNumberUtil.convertPhoneNumber(phoneNumber);
                logger.info("Creating a verifier for a new user with phoneNumber ={}", phoneNumber);
                String tokenCode = temporaryTokenSend(
                        userService.generateAndroidUserVerifier(phoneNumber, displayName, password),
                        phoneNumber);

                return RestUtil.okayResponseWithData(RestMessage.VERIFICATION_TOKEN_SENT, tokenCode);
            } else {
                logger.info("Creating a verifier for user with phoneNumber ={}, user already exists.", phoneNumber);
                return RestUtil.errorResponse(HttpStatus.CONFLICT, RestMessage.USER_ALREADY_EXISTS);
            }
        } catch (InvalidPhoneNumberException e) {
            return RestUtil.errorResponse(HttpStatus.BAD_REQUEST, RestMessage.INVALID_MSISDN);
        }
    }

    @RequestMapping(value = "/register/verify/{phoneNumber}/{code}", method = RequestMethod.GET)
    @ApiOperation(value = "Finish new user registration using otp password", notes = "User data and JWT token is returned as AuthorizedUserDTO object in the 'data' property")
    public ResponseEntity<ResponseWrapper> verifyRegistration(@PathVariable("phoneNumber") String phoneNumber,
                                                              @PathVariable("code") String otpEntered,
                                                              @RequestParam(required = false) UserInterfaceType type) {
        final String msisdn = PhoneNumberUtil.convertPhoneNumber(phoneNumber);
        if (passwordTokenService.isShortLivedOtpValid(msisdn, otpEntered)) {
            logger.info("user dto and code verified, now creating user with phoneNumber={}", phoneNumber);

            UserDTO userDTO = userService.loadUserCreateRequest(msisdn);
            User user = userService.createAndroidUserProfile(userDTO);
            passwordTokenService.generateLongLivedAuthCode(user.getUid());
            passwordTokenService.expireVerificationCode(user.getUid(), VerificationCodeType.SHORT_OTP);

            CreateJwtTokenRequest tokenRequest = new CreateJwtTokenRequest(JwtType.WEB_ANDROID_CLIENT, user);

            String token = jwtService.createJwt(tokenRequest);

            // Assemble response entity
            AuthorizedUserDTO response = new AuthorizedUserDTO(user, token);

            // Return the token on the response
            return RestUtil.okayResponseWithData(RestMessage.LOGIN_SUCCESS, response);
        } else {
            logger.info("Token verification for new user failed");
            return RestUtil.errorResponse(HttpStatus.UNAUTHORIZED, RestMessage.INVALID_OTP);
        }
    }

    @RequestMapping(value = "/web/register", method = RequestMethod.POST)
    public AuthorizationResponseDTO registerWebUser(@RequestParam String name,
                                                    @RequestParam(required = false) String phone,
                                                    @RequestParam(required = false) String email,
                                                    @RequestParam(required = false) String otpEntered,
                                                    @RequestParam String password) {
        logger.info("registering, phone = {}, email = {}", phone, email);
        try {
            // first check basic parameters are valid
            if (StringUtils.isEmpty(name))
                return new AuthorizationResponseDTO(RestMessage.INVALID_DISPLAYNAME);
            else if (StringUtils.isEmpty(email) && StringUtils.isEmpty(phone))
                return new AuthorizationResponseDTO(RestMessage.INVALID_MSISDN);
            else if (StringUtils.isEmpty(password))
                return new AuthorizationResponseDTO(RestMessage.INVALID_PASSWORD);

            // note: once kill old web app, convert this (needed only because of Spring Security user profile needs on reg, it seems);
            User newUser = User.makeEmpty();

            // second, check if this phone/email can register
            final UserRegPossibility regPossibility = userService.checkUserCanRegister(phone, email);
            if (UserRegPossibility.USER_CANNOT_REGISTER.equals(regPossibility))
                return new AuthorizationResponseDTO(RestMessage.USER_REGISTRATION_FAILED);

            // third, if registration is possible but needs an otp, check the otp or tell client it's necessary
            if (UserRegPossibility.USER_REQUIRES_OTP.equals(regPossibility)) {
                if (StringUtils.isEmpty(otpEntered))
                    return new AuthorizationResponseDTO(RestMessage.OTP_REQUIRED);
            }

            if(!StringUtils.isEmpty(otpEntered)){
                String veriFyBy = StringUtils.isEmpty(phone) ? email : phone;
                log.info("Verifying otp by={}",veriFyBy);
                if (!passwordTokenService.isShortLivedOtpValid(veriFyBy, otpEntered))
                    return new AuthorizationResponseDTO(RestMessage.INVALID_OTP);
            }

            // at this point, all checks have necessarily passed, so continue
            newUser.setDisplayName(name);

            if (!StringUtils.isEmpty(phone)) {
                newUser.setPhoneNumber(PhoneNumberUtil.convertPhoneNumber(phone));
            }

            if (!StringUtils.isEmpty(email)) {
                newUser.setEmailAddress(email);
            }

            newUser.setPassword(password);

            User user = userService.createUserWebProfile(newUser);
            String token = jwtService.createJwt(new CreateJwtTokenRequest(JwtType.WEB_ANDROID_CLIENT, user));
            AuthorizedUserDTO response = new AuthorizedUserDTO(user, token);

            return new AuthorizationResponseDTO(response);

        } catch (UserExistsException userException) {
            return new AuthorizationResponseDTO(RestMessage.USER_ALREADY_EXISTS);
        } catch (InvalidPhoneNumberException phoneNumberException) {
            return new AuthorizationResponseDTO(RestMessage.INVALID_MSISDN);
        }
    }

    @RequestMapping(value = "/reset-password-request", method = RequestMethod.POST)
    @ApiOperation(value = "Reset user password request otp", notes = "Username can be either phone or email")
    public ResponseEntity resetPasswordRequest(@RequestParam("username") String passedUsername) {
        try {
            User user = findExistingUser(passedUsername);
            // note: user stored username may be different from that passed in req param (e.g., if user primarily
            // uses phone but in this case gives us their email
            String token = userService.regenerateUserVerifier(user.getUsername(), false);
            temporaryTokenSend(token, user.getUsername());
            return ResponseEntity.ok().build();
        } catch (InvalidPhoneNumberException|NoSuchUserException e) {
            logger.info("Invalid user of passed username: ", passedUsername);
            return ResponseEntity.ok().build();
        }
    }

    @RequestMapping(value = "/reset-password-validate", method = RequestMethod.POST)
    @ApiOperation(value = "Validate an OTP generated in password reset")
    public ResponseEntity validateOtp(@RequestParam("username") String passedUsername, @RequestParam String otp) {
        User user = findExistingUser(passedUsername);
        passwordTokenService.validateOtp(user.getUsername(), otp);
        return ResponseEntity.ok().build();
    }

    @RequestMapping(value = "/reset-password-complete", method = RequestMethod.POST)
    @ApiOperation(value = "Reset user password", notes = "New password is returned as a string in the 'data' property")
    public ResponseEntity resetPassword(@RequestParam("username") String passedUsername,
                                        @RequestParam("password") String newPassword,
                                        @RequestParam("otp") String otpCode) {
        // we return minimal information, for security purposes (i.e., to mask any possible sources of this being invalid)
        User user = findExistingUser(passedUsername);
        userService.resetUserPassword(user.getUsername(), newPassword, otpCode);
        return ResponseEntity.ok().build();
    }


    @RequestMapping(value = "/login", method = RequestMethod.GET)
    @ApiOperation(value = "Login using otp and retrieve a JWT token", notes = "The JWT token is returned as a string in the 'data' property")
    public ResponseEntity<ResponseWrapper> login(@RequestParam("phoneNumber")String phoneNumber,
                                                 @RequestParam("otp") String otp,
                                                 @RequestParam(value = "durationMillis", required = false) Long durationMillis,
                                                 @RequestParam(required = false) UserInterfaceType interfaceType) {
        try {
            final String msisdn = PhoneNumberUtil.convertPhoneNumber(phoneNumber);
            passwordTokenService.validateOtp(msisdn, otp);

            // get the user object
            User user = userService.findByInputNumber(msisdn);

            // Generate a token for the user (for the moment assuming it is Android client)
            CreateJwtTokenRequest tokenRequest = new CreateJwtTokenRequest(JwtType.WEB_ANDROID_CLIENT, user);
            if (durationMillis != null && durationMillis != 0) {
                tokenRequest.setShortExpiryMillis(durationMillis);
            }
            String token = jwtService.createJwt(tokenRequest);

            // Assemble response entity
            AuthorizedUserDTO response = new AuthorizedUserDTO(user, token);

            // log that user was active
            userLogger.logUserLogin(user.getUid(), interfaceType);

            // Return the token on the response
            return RestUtil.okayResponseWithData(RestMessage.LOGIN_SUCCESS, response);
        } catch (InvalidOtpException e) {
           logger.error("Failed to generate authentication token for:  " + phoneNumber);
            return RestUtil.errorResponse(HttpStatus.UNAUTHORIZED, RestMessage.INVALID_OTP);
        }

    }

    @ApiOperation(value = "Login using password and retrieve a JWT token", notes = "The JWT token is returned as a string in the 'data' property")
    @RequestMapping(value = "/login-password", method = RequestMethod.POST)
    public AuthorizationResponseDTO webLogin(@RequestParam("username") String username,
                                             @RequestParam("password") String password,
                                             @RequestParam(required = false) UserInterfaceType interfaceType) {
        try {
            // get the user object, with no user throwing exception
            User user = findExistingUser(username);
            passwordTokenService.validatePwdPhoneOrEmail(username, password);

            // Generate a token for the user (for the moment assuming it is Android client - Angular uses same params)
            CreateJwtTokenRequest tokenRequest = new CreateJwtTokenRequest(JwtType.WEB_ANDROID_CLIENT, user);

            String token = jwtService.createJwt(tokenRequest);
            logger.info("generate a jwt token, on server is: {}", token);

            // Assemble response entity
            AuthorizedUserDTO response = new AuthorizedUserDTO(user, token);

            // log that user was active
            userLogger.logUserLogin(user.getUid(), interfaceType);

            // Return the token on the response
            return new AuthorizationResponseDTO(response);
        } catch (UsernamePasswordLoginFailedException e) {
            logger.error("Failed to generate authentication token for:  " + username);
            return new AuthorizationResponseDTO(RestMessage.INVALID_PASSWORD);
        }
    }

    @RequestMapping(value = "/token/validate", method = RequestMethod.GET)
    @ApiOperation(value = "Validate whether a JWT token is available", notes = "Returns TOKEN_STILL_VALID in 'message', or " +
            "else 'INVALID_TOKEN'")
    public ResponseEntity<ResponseWrapper> validateToken(@RequestParam String token,
                                                         @RequestParam(required = false) String requiredRole) {
        boolean isJwtTokenValid = jwtService.isJwtTokenValid(token);
        final ResponseEntity<ResponseWrapper> validResponse = RestUtil.messageOkayResponse(RestMessage.TOKEN_STILL_VALID);
        final ResponseEntity<ResponseWrapper> invalidResponse = RestUtil.errorResponse(HttpStatus.EXPECTATION_FAILED, RestMessage.INVALID_TOKEN);
        if (isJwtTokenValid) {
            List<String> userRoles = jwtService.getStandardRolesFromJwtToken(token);
            return StringUtils.isEmpty(requiredRole) ? validResponse :
                    userRoles.contains(requiredRole) ? validResponse : invalidResponse;
        } else {
            return invalidResponse;
        }
    }

    @RequestMapping(value = "/token/refresh", method = RequestMethod.GET)
    @ApiOperation(value = "Refresh JWT token", notes = "Try to refresh an old or expired token, responds with " +
            "a new token as a string (in the 'data' property) if the old token is within the refresh window, or a bad request " +
            "if the token is still old")
    public ResponseEntity<ResponseWrapper> refreshToken(@RequestParam("oldToken")String oldToken,
                                                        @RequestParam(value = "durationMillis", required = false) Long durationMillis) {
        String newToken = jwtService.refreshToken(oldToken, JwtType.WEB_ANDROID_CLIENT, durationMillis);
        if (newToken != null) {
            return RestUtil.okayResponseWithData(RestMessage.LOGIN_SUCCESS, newToken);
        } else {
            return RestUtil.errorResponse(HttpStatus.BAD_REQUEST, RestMessage.TOKEN_EXPIRED);
        }
    }

    private String temporaryTokenSend(String token, String numberOrEmail) {
        if (environment.acceptsProfiles(Profiles.of(GrassrootApplicationProfiles.PRODUCTION))) {
            passwordTokenService.triggerOtp(userService.findByUsernameLoose(numberOrEmail));
            return "";
        } else {
            logger.info("returning token: {}", token);
            return token;
        }
    }

    private boolean ifExists(String phoneNumber) {
        return userService.userExist(PhoneNumberUtil.convertPhoneNumber(phoneNumber));
    }
}