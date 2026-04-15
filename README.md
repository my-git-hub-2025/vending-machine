# vending-machine

Simple user login/logout system with a text-file database.

## Files
- `auth_system.py`: login/logout implementation
- `users.txt`: text-file user database (created automatically)
- `tests/test_auth_system.py`: unit tests

## Run tests
```bash
python -m unittest discover -s tests -p "test_*.py"
```

## Quick usage
```python
from auth_system import AuthSystem

auth = AuthSystem("users.txt")
auth.register_user("alice", "secret123")
auth.login("alice", "secret123")
auth.logout("alice")
```
