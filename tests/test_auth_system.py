import tempfile
import unittest
from pathlib import Path

from auth_system import AuthSystem


class AuthSystemTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tmpdir = tempfile.TemporaryDirectory()
        self.db_path = Path(self.tmpdir.name) / "users.txt"
        self.auth = AuthSystem(str(self.db_path))

    def tearDown(self) -> None:
        self.tmpdir.cleanup()

    def test_register_user_success(self) -> None:
        self.assertTrue(self.auth.register_user("alice", "secret"))

    def test_register_user_duplicate_fails(self) -> None:
        self.assertTrue(self.auth.register_user("alice", "secret"))
        self.assertFalse(self.auth.register_user("alice", "another"))

    def test_login_success(self) -> None:
        self.auth.register_user("alice", "secret")
        self.assertTrue(self.auth.login("alice", "secret"))
        self.assertTrue(self.auth.is_logged_in("alice"))

    def test_login_wrong_password_fails(self) -> None:
        self.auth.register_user("alice", "secret")
        self.assertFalse(self.auth.login("alice", "wrong"))
        self.assertFalse(self.auth.is_logged_in("alice"))

    def test_login_nonexistent_user_fails(self) -> None:
        self.assertFalse(self.auth.login("ghost", "secret"))
        self.assertFalse(self.auth.is_logged_in("ghost"))

    def test_login_empty_username_fails(self) -> None:
        self.assertFalse(self.auth.login("", "secret"))

    def test_login_empty_password_fails(self) -> None:
        self.auth.register_user("alice", "secret")
        self.assertFalse(self.auth.login("alice", ""))

    def test_logout_success(self) -> None:
        self.auth.register_user("alice", "secret")
        self.auth.login("alice", "secret")
        self.assertTrue(self.auth.logout("alice"))
        self.assertFalse(self.auth.is_logged_in("alice"))

    def test_logout_when_not_logged_in_fails(self) -> None:
        self.auth.register_user("alice", "secret")
        self.assertFalse(self.auth.logout("alice"))

    def test_register_user_empty_username_fails(self) -> None:
        self.assertFalse(self.auth.register_user("", "secret"))

    def test_register_user_empty_password_fails(self) -> None:
        self.assertFalse(self.auth.register_user("alice", ""))


if __name__ == "__main__":
    unittest.main()
