package za.org.grassroot.integration.authentication;

import org.springframework.http.HttpHeaders;
import za.org.grassroot.integration.PublicCredentials;

import java.util.List;

/**
 * Created by luke on 2017/05/22.
 */
public interface JwtService {

    String USER_UID_KEY = "USER_UID";
    String SYSTEM_ROLE_KEY = "SYSTEM_ROLE_KEY";
    String PERMISSIONS_KEY = "PERMISSIONS";
    String TYPE_KEY = "TYPE";

    PublicCredentials getPublicCredentials();

    String createJwt(CreateJwtTokenRequest request);

    HttpHeaders createHeadersForLambdaCall();

    /**
     * Refresh token if old token is still valid or has expired but is still within the expiration grace period.
     * @return new token if old token is still valid or has expired but is still within the expiration grace period.
     * Otherwise, return <code></code>null.
     */
    String refreshToken(String oldToken, JwtType jwtType, Long shortExpiryMillis);

    boolean isJwtTokenValid(String token);

    boolean isJwtTokenExpired(String token);

    String getUserIdFromJwtToken(String token);

    List<String> getStandardRolesFromJwtToken(String token);

    List<String> getPermissionsFromToken(String token);
}
