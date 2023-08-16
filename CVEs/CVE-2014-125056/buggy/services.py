from horus.exceptions import (
    AuthenticationException,
    UserExistsException
)


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
            user.password != password
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
