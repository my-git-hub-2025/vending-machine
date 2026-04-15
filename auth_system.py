from __future__ import annotations

import hashlib
import hmac
import secrets
from pathlib import Path


class AuthSystem:
    _PBKDF2_ITERATIONS = 600_000
    _DUMMY_SALT_HEX = "0" * 32
    _DUMMY_PASSWORD = "dummy-password"

    def __init__(self, db_path: str = "users.txt") -> None:
        self.db_path = Path(db_path)
        self.logged_in_users: set[str] = set()
        dummy_derived_key = hashlib.pbkdf2_hmac(
            "sha256",
            self._DUMMY_PASSWORD.encode("utf-8"),
            bytes.fromhex(self._DUMMY_SALT_HEX),
            self._PBKDF2_ITERATIONS,
        ).hex()
        self._dummy_stored_password = f"{self._DUMMY_SALT_HEX}${dummy_derived_key}"
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self.db_path.touch(exist_ok=True)

    @staticmethod
    def _hash_password(password: str) -> str:
        salt = secrets.token_hex(16)
        derived_key = hashlib.pbkdf2_hmac(
            "sha256",
            password.encode("utf-8"),
            bytes.fromhex(salt),
            AuthSystem._PBKDF2_ITERATIONS,
        ).hex()
        return f"{salt}${derived_key}"

    @staticmethod
    def _verify_password(password: str, stored_password: str) -> bool:
        if "$" not in stored_password:
            return False
        salt, expected_derived_key = stored_password.split("$", 1)
        derived_key = hashlib.pbkdf2_hmac(
            "sha256",
            password.encode("utf-8"),
            bytes.fromhex(salt),
            AuthSystem._PBKDF2_ITERATIONS,
        ).hex()
        return hmac.compare_digest(derived_key, expected_derived_key)

    def _load_users(self) -> dict[str, str]:
        users: dict[str, str] = {}
        with self.db_path.open("r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or ":" not in line:
                    continue
                username, password_hash = line.split(":", 1)
                users[username] = password_hash
        return users

    def _save_users(self, users: dict[str, str]) -> None:
        with self.db_path.open("w", encoding="utf-8") as f:
            for username, password_hash in users.items():
                f.write(f"{username}:{password_hash}\n")

    def register_user(self, username: str, password: str) -> bool:
        if not username or not password or ":" in username:
            return False
        users = self._load_users()
        if username in users:
            return False
        users[username] = self._hash_password(password)
        self._save_users(users)
        return True

    def login(self, username: str, password: str) -> bool:
        if not username or not password:
            return False
        users = self._load_users()
        user_exists = username in users
        stored_password = users.get(username, self._dummy_stored_password)
        is_valid_password = self._verify_password(password, stored_password)
        if not (user_exists and is_valid_password):
            return False
        self.logged_in_users.add(username)
        return True

    def logout(self, username: str) -> bool:
        users = self._load_users()
        if username not in users:
            return False
        if username not in self.logged_in_users:
            return False
        self.logged_in_users.remove(username)
        return True

    def is_logged_in(self, username: str) -> bool:
        return username in self.logged_in_users
