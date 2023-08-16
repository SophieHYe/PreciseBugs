package com.nexblocks.authguard.basic;


import com.nexblocks.authguard.basic.passwords.SecurePassword;
import com.nexblocks.authguard.basic.passwords.SecurePasswordProvider;
import com.nexblocks.authguard.service.AccountsService;
import com.nexblocks.authguard.service.CredentialsService;
import com.nexblocks.authguard.service.exceptions.ServiceAuthorizationException;
import com.nexblocks.authguard.service.exceptions.ServiceException;
import com.nexblocks.authguard.service.model.*;
import io.vavr.control.Either;
import org.apache.commons.lang3.RandomStringUtils;
import org.jeasy.random.EasyRandom;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.TestInstance;
import org.mockito.Mockito;

import java.util.Base64;
import java.util.Optional;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.mockito.ArgumentMatchers.eq;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
class BasicAuthProviderTest {
    private AccountsService accountsService;
    private CredentialsService credentialsService;
    private SecurePassword securePassword;

    private BasicAuthProvider basicAuth;

    private final static EasyRandom RANDOM = new EasyRandom();

    @BeforeAll
    void setup() {
        accountsService = Mockito.mock(AccountsService.class);
        credentialsService = Mockito.mock(CredentialsService.class);
        securePassword = Mockito.mock(SecurePassword.class);

        final SecurePasswordProvider securePasswordProvider = Mockito.mock(SecurePasswordProvider.class);

        Mockito.when(securePasswordProvider.get()).thenReturn(securePassword);

        basicAuth = new BasicAuthProvider(credentialsService, accountsService, securePasswordProvider);
    }

    @AfterEach
    void resetMocks() {
        Mockito.reset(accountsService);
        Mockito.reset(credentialsService);
    }

    @Test
    void authenticate() {
        final String username = "username";
        final String password = "password";
        final String authorization = Base64.getEncoder().encodeToString((username + ":" + password).getBytes());

        final AccountBO account = RANDOM.nextObject(AccountBO.class);
        final CredentialsBO credentials = RANDOM.nextObject(CredentialsBO.class)
                .withIdentifiers(UserIdentifierBO.builder()
                        .identifier(username)
                        .type(UserIdentifier.Type.USERNAME)
                        .active(true)
                        .build());
        final HashedPasswordBO hashedPasswordBO = HashedPasswordBO.builder()
                .password(credentials.getHashedPassword().getPassword())
                .salt(credentials.getHashedPassword().getSalt())
                .build();

        Mockito.when(credentialsService.getByUsernameUnsafe(username)).thenReturn(Optional.of(credentials));
        Mockito.when(accountsService.getById(credentials.getAccountId())).thenReturn(Optional.of(account));
        Mockito.when(securePassword.verify(eq(password), eq(hashedPasswordBO))).thenReturn(true);

        final Either<Exception, AccountBO> result = basicAuth.authenticateAndGetAccount(authorization);

        assertThat(result.get()).isEqualTo(account);
    }

    @Test
    void authenticateInactiveIdentifier() {
        final String username = "username";
        final String password = "password";
        final String authorization = Base64.getEncoder().encodeToString((username + ":" + password).getBytes());

        final CredentialsBO credentials = RANDOM.nextObject(CredentialsBO.class)
                .withIdentifiers(UserIdentifierBO.builder()
                        .identifier(username)
                        .type(UserIdentifier.Type.USERNAME)
                        .active(false)
                        .build());

        Mockito.when(credentialsService.getByUsernameUnsafe(username)).thenReturn(Optional.of(credentials));

        final Either<Exception, AccountBO> result = basicAuth.authenticateAndGetAccount(authorization);

        assertThat(result.isLeft()).isTrue();
        assertThat(result.getLeft()).isInstanceOf(ServiceAuthorizationException.class);
    }

    @Test
    void authenticateNotFound() {
        final String username = "username";
        final String password = "password";
        final String authorization = Base64.getEncoder().encodeToString((username + ":" + password).getBytes());

        Mockito.when(credentialsService.getByUsername(username)).thenReturn(Optional.empty());

        assertThat(basicAuth.authenticateAndGetAccount(authorization)).isEmpty();
    }

    @Test
    void authenticateWrongPassword() {
        final String username = "username";
        final String password = "password";
        final String authorization = Base64.getEncoder().encodeToString((username + ":" + password).getBytes());

        final CredentialsBO credentials = RANDOM.nextObject(CredentialsBO.class)
                .withIdentifiers(UserIdentifierBO.builder()
                        .identifier(username)
                        .type(UserIdentifier.Type.USERNAME)
                        .build());
        final HashedPasswordBO hashedPasswordBO = HashedPasswordBO.builder()
                .password(credentials.getHashedPassword().getPassword())
                .salt(credentials.getHashedPassword().getSalt())
                .build();

        Mockito.when(credentialsService.getByUsernameUnsafe(username)).thenReturn(Optional.of(credentials));
        Mockito.when(securePassword.verify(eq(password), eq(hashedPasswordBO))).thenReturn(false);

        final Either<Exception, AccountBO> result = basicAuth.authenticateAndGetAccount(authorization);

        assertThat(result.isLeft()).isTrue();
        assertThat(result.getLeft()).isInstanceOf(ServiceAuthorizationException.class);
    }

    @Test
    void authenticateBadAuthorization() {
        final String authorization = RandomStringUtils.randomAlphanumeric(20);
        assertThatThrownBy(() -> basicAuth.authenticateAndGetAccount(authorization)).isInstanceOf(ServiceException.class);
    }

    @Test
    void authenticateBadBasicScheme() {
        final String authorization = "dGhpc2RvbmVzbid0Zmx5aW5vdXJjaXR5";
        assertThatThrownBy(() -> basicAuth.authenticateAndGetAccount(authorization)).isInstanceOf(ServiceException.class);
    }
}