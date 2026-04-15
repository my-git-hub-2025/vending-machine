# vending-machine

Simple PHP vending machine website with Bootstrap and Font Awesome.

## Features

- User registration and login using `data/users.txt`
- First registered account automatically becomes `admin`
- CSRF protection on all POST forms
- Basic rate limiting for login and registration attempts
- Security headers (CSP, frame protection, no-sniff, referrer policy)
- User-facing vending machine view with item, price, and slot quantity
- Admin panel to:
  - Configure machine rows, columns, and slots per column
  - Manage stock products with price and stock quantity
  - Assign stock products into machine slots with slot quantity
- Data persistence using text files (`.txt` JSON format)

## Data files

- `data/users.txt`
- `data/machine_config.txt`
- `data/products.txt`
- `data/slot_assignments.txt`

## Run locally

From repo root:

```bash
php -S localhost:8000
```

Open:

- `http://localhost:8000/register.php` to create first admin account
- `http://localhost:8000/login.php` to log in
- `http://localhost:8000/index.php` to view vending machine
- `http://localhost:8000/admin.php` for admin features

## Security notes

- Registration requires a password with at least 8 characters.
- `data/.htaccess` denies direct data-file access on Apache-based deployments.
