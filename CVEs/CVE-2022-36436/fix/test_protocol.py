import unittest

from vncap.vnc.protocol import VNCServerAuthenticator

class DummyTransport(object):

    buf = ""
    lost = False

    def write(self, data):
        self.buf += data

    def loseConnection(self):
        self.lost = True

    def pauseProducing(self):
        pass

class TestVNCServerAuthenticator(unittest.TestCase):

    def setUp(self):
        self.p = VNCServerAuthenticator("password", {})
        self.t = DummyTransport()
        self.p.makeConnection(self.t)

    def test_trivial(self):
        pass

    def test_connectionMade(self):
        self.assertEqual(self.t.buf, "RFB 003.008\n")

    def test_check_version(self):
        self.t.buf = ""
        self.p.check_version("RFB 003.008\n")
        self.assertEqual(self.t.buf, "\x01\x02")

    def test_check_invalid_version(self):
        self.t.buf = ""
        self.p.check_version("RFB 002.000\n")
        self.assertTrue(self.t.lost)

    def test_select_security_type_none(self):
        self.t.buf = ""
        self.p.select_security_type("\x01")
        self.assertTrue(self.t.lost)

    def test_select_security_type_vnc_auth(self):
        self.t.buf = ""
        self.p.select_security_type("\x02")
        self.assertFalse(self.t.lost)
        self.assertEqual(len(self.t.buf), 16)
