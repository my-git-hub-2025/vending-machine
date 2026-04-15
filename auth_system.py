from __future__ import annotations

import hashlib
from pathlib import Path


class AuthSystem:
    def __init__(self, db_path: str = "users.txt") -> None:
        self.db_path = Path(db_path)
        self.logged_in_users: set[str] = set()
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self.db_path.touch(exist_ok=True)

    @staticmethod
    def _hash_password(password: str) -> str:
        return hashlib.sha256(password.encode("utf-8")).hexdigest()

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
        if not username or not password:
            return False
        users = self._load_users()
        if username in users:
            return False
        users[username] = self._hash_password(password)
        self._save_users(users)
        return True

    def login(self, username: str, password: str) -> bool:
        users = self._load_users()
        if username not in users:
            return False
        if users[username] != self._hash_password(password):
            return False
        self.logged_in_users.add(username)
        return True

    def logout(self, username: str) -> bool:
        if username not in self.logged_in_users:
            return False
        self.logged_in_users.remove(username)
        return True

    def is_logged_in(self, username: str) -> bool:
        return username in self.logged_in_users
