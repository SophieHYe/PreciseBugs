# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#    http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
# implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#
# Pablo Llopis 2011

import mock
from swauth import authtypes
import unittest


class TestValidation(unittest.TestCase):
    def test_validate_creds(self):
        creds = 'plaintext:keystring'
        creds_dict = dict(type='plaintext', salt=None, hash='keystring')
        auth_encoder, parsed_creds = authtypes.validate_creds(creds)
        self.assertEqual(parsed_creds, creds_dict)
        self.assertTrue(isinstance(auth_encoder, authtypes.Plaintext))

        creds = 'sha1:salt$d50dc700c296e23ce5b41f7431a0e01f69010f06'
        creds_dict = dict(type='sha1', salt='salt',
                          hash='d50dc700c296e23ce5b41f7431a0e01f69010f06')
        auth_encoder, parsed_creds = authtypes.validate_creds(creds)
        self.assertEqual(parsed_creds, creds_dict)
        self.assertTrue(isinstance(auth_encoder, authtypes.Sha1))

        creds = ('sha512:salt$482e73705fac6909e2d78e8bbaf65ac3ca1473'
                 '8f445cc2367b7daa3f0e8f3dcfe798e426b9e332776c8da59c'
                 '0c11d4832931d1bf48830f670ecc6ceb04fbad0f')
        creds_dict = dict(type='sha512', salt='salt',
                          hash='482e73705fac6909e2d78e8bbaf65ac3ca1473'
                               '8f445cc2367b7daa3f0e8f3dcfe798e426b9e3'
                               '32776c8da59c0c11d4832931d1bf48830f670e'
                               'cc6ceb04fbad0f')
        auth_encoder, parsed_creds = authtypes.validate_creds(creds)
        self.assertEqual(parsed_creds, creds_dict)
        self.assertTrue(isinstance(auth_encoder, authtypes.Sha512))

    def test_validate_creds_fail(self):
        # wrong format, missing `:`
        creds = 'unknown;keystring'
        self.assertRaisesRegexp(ValueError, "Missing ':' in .*",
                                authtypes.validate_creds, creds)
        # unknown auth_type
        creds = 'unknown:keystring'
        self.assertRaisesRegexp(ValueError, "Invalid auth_type: .*",
                                authtypes.validate_creds, creds)
        # wrong plaintext keystring
        creds = 'plaintext:'
        self.assertRaisesRegexp(ValueError, "Key must have non-zero length!",
                                authtypes.validate_creds, creds)
        # wrong sha1 format, missing `$`
        creds = 'sha1:saltkeystring'
        self.assertRaisesRegexp(ValueError, "Missing '\$' in .*",
                                authtypes.validate_creds, creds)
        # wrong sha1 format, missing salt
        creds = 'sha1:$hash'
        self.assertRaisesRegexp(ValueError, "Salt must have non-zero length!",
                                authtypes.validate_creds, creds)
        # wrong sha1 format, missing hash
        creds = 'sha1:salt$'
        self.assertRaisesRegexp(ValueError, "Hash must have 40 chars!",
                                authtypes.validate_creds, creds)
        # wrong sha1 format, short hash
        creds = 'sha1:salt$short_hash'
        self.assertRaisesRegexp(ValueError, "Hash must have 40 chars!",
                                authtypes.validate_creds, creds)
        # wrong sha1 format, wrong format
        creds = 'sha1:salt$' + "z" * 40
        self.assertRaisesRegexp(ValueError, "Hash must be hexadecimal!",
                                authtypes.validate_creds, creds)
        # wrong sha512 format, missing `$`
        creds = 'sha512:saltkeystring'
        self.assertRaisesRegexp(ValueError, "Missing '\$' in .*",
                                authtypes.validate_creds, creds)
        # wrong sha512 format, missing salt
        creds = 'sha512:$hash'
        self.assertRaisesRegexp(ValueError, "Salt must have non-zero length!",
                                authtypes.validate_creds, creds)
        # wrong sha512 format, missing hash
        creds = 'sha512:salt$'
        self.assertRaisesRegexp(ValueError, "Hash must have 128 chars!",
                                authtypes.validate_creds, creds)
        # wrong sha512 format, short hash
        creds = 'sha512:salt$short_hash'
        self.assertRaisesRegexp(ValueError, "Hash must have 128 chars!",
                                authtypes.validate_creds, creds)
        # wrong sha1 format, wrong format
        creds = 'sha512:salt$' + "z" * 128
        self.assertRaisesRegexp(ValueError, "Hash must be hexadecimal!",
                                authtypes.validate_creds, creds)


class TestPlaintext(unittest.TestCase):

    def setUp(self):
        self.auth_encoder = authtypes.Plaintext()

    def test_plaintext_encode(self):
        enc_key = self.auth_encoder.encode('keystring')
        self.assertEqual('plaintext:keystring', enc_key)

    def test_plaintext_valid_match(self):
        creds = 'plaintext:keystring'
        match = self.auth_encoder.match('keystring', creds)
        self.assertEqual(match, True)

    def test_plaintext_invalid_match(self):
        creds = 'plaintext:other-keystring'
        match = self.auth_encoder.match('keystring', creds)
        self.assertEqual(match, False)


class TestSha1(unittest.TestCase):

    def setUp(self):
        self.auth_encoder = authtypes.Sha1()
        self.auth_encoder.salt = 'salt'

    @mock.patch('swauth.authtypes.os')
    def test_sha1_encode(self, os):
        os.urandom.return_value.encode.return_value.rstrip \
            .return_value = 'salt'
        enc_key = self.auth_encoder.encode('keystring')
        self.assertEqual('sha1:salt$d50dc700c296e23ce5b41f7431a0e01f69010f06',
                         enc_key)

    def test_sha1_valid_match(self):
        creds = 'sha1:salt$d50dc700c296e23ce5b41f7431a0e01f69010f06'
        creds_dict = dict(type='sha1', salt='salt',
                          hash='d50dc700c296e23ce5b41f7431a0e01f69010f06')
        match = self.auth_encoder.match('keystring', creds, **creds_dict)
        self.assertEqual(match, True)

    def test_sha1_invalid_match(self):
        creds = 'sha1:salt$deadbabedeadbabedeadbabec0ffeebadc0ffeee'
        creds_dict = dict(type='sha1', salt='salt',
                          hash='deadbabedeadbabedeadbabec0ffeebadc0ffeee')
        match = self.auth_encoder.match('keystring', creds, **creds_dict)
        self.assertEqual(match, False)

        creds = 'sha1:salt$d50dc700c296e23ce5b41f7431a0e01f69010f06'
        creds_dict = dict(type='sha1', salt='salt',
                          hash='d50dc700c296e23ce5b41f7431a0e01f69010f06')
        match = self.auth_encoder.match('keystring2', creds, **creds_dict)
        self.assertEqual(match, False)


class TestSha512(unittest.TestCase):

    def setUp(self):
        self.auth_encoder = authtypes.Sha512()
        self.auth_encoder.salt = 'salt'

    @mock.patch('swauth.authtypes.os')
    def test_sha512_encode(self, os):
        os.urandom.return_value.encode.return_value.rstrip \
            .return_value = 'salt'
        enc_key = self.auth_encoder.encode('keystring')
        self.assertEqual('sha512:salt$482e73705fac6909e2d78e8bbaf65ac3ca1473'
                         '8f445cc2367b7daa3f0e8f3dcfe798e426b9e332776c8da59c'
                         '0c11d4832931d1bf48830f670ecc6ceb04fbad0f', enc_key)

    def test_sha512_valid_match(self):
        creds = ('sha512:salt$482e73705fac6909e2d78e8bbaf65ac3ca14738f445cc2'
                 '367b7daa3f0e8f3dcfe798e426b9e332776c8da59c0c11d4832931d1bf'
                 '48830f670ecc6ceb04fbad0f')
        creds_dict = dict(type='sha512', salt='salt',
                          hash='482e73705fac6909e2d78e8bbaf65ac3ca14738f445cc2'
                               '367b7daa3f0e8f3dcfe798e426b9e332776c8da59c0c11'
                               'd4832931d1bf48830f670ecc6ceb04fbad0f')
        match = self.auth_encoder.match('keystring', creds, **creds_dict)
        self.assertEqual(match, True)

    def test_sha512_invalid_match(self):
        creds = ('sha512:salt$deadbabedeadbabedeadbabedeadbabedeadbabedeadba'
                 'bedeadbabedeadbabedeadbabedeadbabedeadbabedeadbabedeadbabe'
                 'c0ffeebadc0ffeeec0ffeeba')
        creds_dict = dict(type='sha512', salt='salt',
                          hash='deadbabedeadbabedeadbabedeadbabedeadbabedeadba'
                               'bedeadbabedeadbabedeadbabedeadbabedeadbabedead'
                               'babedeadbabec0ffeebadc0ffeeec0ffeeba')
        match = self.auth_encoder.match('keystring', creds, **creds_dict)
        self.assertEqual(match, False)

        creds = ('sha512:salt$482e73705fac6909e2d78e8bbaf65ac3ca14738f445cc2'
                 '367b7daa3f0e8f3dcfe798e426b9e332776c8da59c0c11d4832931d1bf'
                 '48830f670ecc6ceb04fbad0f')
        creds_dict = dict(type='sha512', salt='salt',
                          hash='482e73705fac6909e2d78e8bbaf65ac3ca14738f445cc2'
                               '367b7daa3f0e8f3dcfe798e426b9e332776c8da59c0c11'
                               'd4832931d1bf48830f670ecc6ceb04fbad0f')
        match = self.auth_encoder.match('keystring2', creds, **creds_dict)
        self.assertEqual(match, False)


if __name__ == '__main__':
    unittest.main()
