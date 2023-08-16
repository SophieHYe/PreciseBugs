from horus.exceptions import (
    AuthenticationException,
    UserExistsException
)


try:
    from hmac import compare_digest as is_equal
except ImportError:
    def is_equal(lhs, rhs):
        """Returns True if the two strings are equal, False otherwise.

        The comparison is based on a common implementation found in Django.
        This version avoids a short-circuit even for unequal lengths to reveal
        as little as possible. It takes time proportional to the length of its
        second argument.
        """
        result = 0 if len(lhs) == len(rhs) else 1
        lhs = lhs.ljust(len(rhs))
        for x, y in zip(lhs, rhs):
            result |= ord(x) ^ ord(y)
        return result == 0


class AuthenticationService(object):
    def __init__(self, backend):
        self.backend = backend

    def login(self, login, password):
        """
        Authenticates the user by their login property and password.

        Will raise an `AuthenticationException` if the username or password
        are not found.
        """
        #TODO: Check if the user is activated?
        #TODO: Add a database log of authentication attempts
        #TODO: Prevent multiple attempts from same IP
        user = self.backend.get_user(login)

        if (
            user is None or
            is_equal(user.password, password) is False
        ):
            raise AuthenticationException()

        return user


class RegisterService(object):
    def __init__(self, backend):
        self.backend = backend

    def create_user(self, login, password=None, email=None):
        """
        Will create a user in the database
        """
        user = self.backend.get_user(login)

        if user is not None:
            raise UserExistsException()

        user = self.backend.create_user(
            login,
            password,
            email
        )

        return user
