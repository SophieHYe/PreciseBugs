package za.org.grassroot.integration.authentication;

import io.jsonwebtoken.*;
import io.jsonwebtoken.impl.TextCodec;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.core.env.Environment;
import org.springframework.http.HttpHeaders;
import org.springframework.stereotype.Service;
import org.springframework.util.StringUtils;
import za.org.grassroot.integration.PublicCredentials;

import javax.annotation.PostConstruct;
import java.security.PublicKey;
import java.time.Duration;
import java.time.Instant;
import java.time.temporal.ChronoUnit;
import java.util.*;

/**
 * Created by luke on 2017/05/22.
 */
@Service
public class JwtServiceImpl implements JwtService {

    private static final Logger logger = LoggerFactory.getLogger(JwtServiceImpl.class);

    private String keyIdentifier;

    @Value("${grassroot.jwt.token-expiry-grace-period.inMilliseconds:1209600000}")
    private Long jwtTokenExpiryGracePeriodInMilliseconds;

    @Value("${grassroot.jwt.api-key-expiry.inDays:180}")
    private Long jwtApiKeyExpiryDays;

    private final Environment environment;
    private final KeyPairProvider keyPairProvider;

    @Autowired
    public JwtServiceImpl(Environment environment, KeyPairProvider keyPairProvider) {
        this.environment = environment;
        this.keyPairProvider = keyPairProvider;
    }

    @PostConstruct
    public void init() {
        PublicCredentials credentials = refreshPublicCredentials();
        logger.debug("Public credentials generated: {}", credentials);
    }

    @Override
    public PublicCredentials getPublicCredentials() {
        return createCredentialEntity(keyIdentifier, keyPairProvider.getJWTKey().getPublic());
    }

    @Override
    public String createJwt(CreateJwtTokenRequest request) {
        Instant now = Instant.now();

        long typeExpiryMillis = convertTypeToExpiryMillis(request.getJwtType());
        long passedExpiryMillis = request.getShortExpiryMillis() == null ? typeExpiryMillis :
                Math.min(typeExpiryMillis, request.getShortExpiryMillis());

        Instant exp = now.plus(passedExpiryMillis, ChronoUnit.MILLIS);
        request.getHeaderParameters().put("kid", keyIdentifier);

        return Jwts.builder()
                .setHeaderParams(request.getHeaderParameters())
                .setClaims(request.getClaims())
                .setIssuedAt(Date.from(now))
                .setExpiration(Date.from(exp))
                .signWith(
                        SignatureAlgorithm.RS256,
                        keyPairProvider.getJWTKey().getPrivate()
                )
                .compact();
    }

    @Override
    public HttpHeaders createHeadersForLambdaCall() {
        HttpHeaders headers = new HttpHeaders();
        headers.add("Authorization", "Bearer " + createJwt(CreateJwtTokenRequest.makeSystemToken()));
        return headers;
    }

    private long convertTypeToExpiryMillis(JwtType jwtType) {
        switch (jwtType) {
            case WEB_ANDROID_CLIENT:
                return Duration.ofDays(7L).toMillis();
            case GRASSROOT_MICROSERVICE:
                return Duration.ofSeconds(3).toMillis(); // occasional glitches mean 3 secs is a better trade off here at present
            case MSGING_CLIENT:
                return Duration.ofMinutes(1).toMillis();
            case API_CLIENT:
                return Duration.ofDays(jwtApiKeyExpiryDays).toMillis(); // now long lived
            default:
                return 1L;
        }
    }

    @Override
    public boolean isJwtTokenValid(String token) {
        try {
            Jwts.parser().setSigningKey(keyPairProvider.getJWTKey().getPublic()).parse(token);
            return true;
        }
        catch (ExpiredJwtException e) {
            logger.error("Token validation failed. The token is expired. Exception: {}", e.getMessage());
            return false;
        }
        catch (SignatureException e) {
            logger.error("Token validation failed, wrong signature. Exception: {}", e.getMessage());
            return false;
        }
        catch (Exception e) {
            logger.error("Unexpected token validation error.", e);
            return false;
        }
    }

    @Override
    public boolean isJwtTokenExpired(String token) {
        try {
            Jwts.parser().setSigningKey(keyPairProvider.getJWTKey().getPublic()).parse(token).getBody();
            return false;
        }
        catch (ExpiredJwtException e) {
            logger.error("The token is expired.", e);
            return true;
        }
        catch (Exception e) {
            logger.error("Unexpected token validation error.", e);
            return false;
        }
    }

    @Override
    public String getUserIdFromJwtToken(String token) {
        return extractFromToken(USER_UID_KEY, token);
    }

    @Override
    public List<String> getPermissionsFromToken(String token) {
        String permissionList = extractFromToken(PERMISSIONS_KEY, token);
        return StringUtils.isEmpty(permissionList) ? new ArrayList<>() :
                Arrays.asList(permissionList.split(","));
    }

    private String extractFromToken(String key, String token) {
        try {
            Claims claims = Jwts.parser().setSigningKey(keyPairProvider.getJWTKey().getPublic())
                    .parseClaimsJws(token).getBody();
            return claims.get(key, String.class);
        } catch (Exception e) {
            logger.error("Failed to get user id from jwt token: {}", e.getMessage());
            return null;
        }
    }

    @Override
    public List<String> getStandardRolesFromJwtToken(String token) {
        String joinedRoles = extractClaims(token).get(SYSTEM_ROLE_KEY, String.class);
        return StringUtils.isEmpty(joinedRoles) ? new ArrayList<>() : Arrays.asList(joinedRoles.split(","));
    }

    private Claims extractClaims(String token) {
        return Jwts.parser().setSigningKey(keyPairProvider.getJWTKey().getPublic())
                .parseClaimsJws(token).getBody();
    }

    @Override
    public String refreshToken(String oldToken, JwtType jwtType, Long shortExpiryMillis) {
        boolean isTokenStillValid = false;
        Date expirationTime = null;
        String newToken = null;
        String userId = null;
        String systemRoles = null;
        try {
            Jwt<Header, Claims> jwt = Jwts.parser().setSigningKey(keyPairProvider.getJWTKey().getPublic()).parseClaimsJws(oldToken);
            userId = jwt.getBody().get(USER_UID_KEY, String.class);
            systemRoles = jwt.getBody().get(SYSTEM_ROLE_KEY, String.class);
            isTokenStillValid = true;
        }
        catch (ExpiredJwtException e) {
            logger.error("Token validation failed. The token is expired.", e);
            expirationTime = e.getClaims().getExpiration();
        }
        if (isTokenStillValid || expirationTime != null
                && expirationTime.toInstant().plus(jwtTokenExpiryGracePeriodInMilliseconds, ChronoUnit.MILLIS).isAfter(new Date().toInstant())) {
            CreateJwtTokenRequest cjtRequest = new CreateJwtTokenRequest(jwtType, shortExpiryMillis, userId, systemRoles);

            newToken = createJwt(cjtRequest);
        }

        return newToken;
    }

    private PublicCredentials refreshPublicCredentials() {
        keyIdentifier = environment.getProperty("grassroot.publickey.identifier", UUID.randomUUID().toString());
        logger.debug("created KUID for main platform: {}", keyIdentifier);
        return createCredentialEntity(keyIdentifier, keyPairProvider.getJWTKey().getPublic());
    }

    private PublicCredentials createCredentialEntity(String kuid, PublicKey key) {
        return new PublicCredentials(kuid, TextCodec.BASE64.encode(key.getEncoded()));
    }
}
