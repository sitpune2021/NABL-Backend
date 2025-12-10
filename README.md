# 🧩 **NABL Backend API (Laravel)**

A secure and scalable **Laravel REST API** built for the NABL Admin Panel (React + Vite).
Uses **JWT authentication** and PostgreSQL database.

---

## 🚀 Features

* 🔐 JWT Token Authentication (**required**)
* 🔐 Sanctum SPA session support
* 🧑‍💼 Role-based access
* 🗄️ PostgreSQL
* ⚡ API-first modular design

---

# ⚙️ Installation

### 1️⃣ Clone the project

```bash
git clone https://github.com/your-username/nabl-backend.git
cd nabl-backend
```

---

### 2️⃣ Install dependencies

```bash
composer install
```

---

### 3️⃣ Create environment file

```bash
cp .env.example .env
```

Update `.env`:

```env
APP_URL=http://192.168.1.33:8000

DB_CONNECTION=pgsql
DB_PORT=5433
DB_DATABASE=nabl
DB_USERNAME=
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
SESSION_EXPIRE_ON_CLOSE=true
```

---

# 🔐 JWT Secret Key (**Required**)

This project **REQUIRES** a JWT key for authentication.
You must generate it before running the API.

### Generate JWT key:

```bash
php artisan jwt:secret
```

This will update your `.env` with:

```
JWT_SECRET=your_generated_key_here
```

### OR generate manually:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the result:

```
JWT_SECRET=generated_key_here
```

---

### ❗ Important (GitHub Upload)

* Never commit your real `.env`.
* In `.env.example`, always include:

```
JWT_SECRET=your_jwt_key_here
```

This ensures others know the variable is **required**.

---

# 🗄️ Database Setup

```bash
php artisan migrate
```

(Optional)

```bash
php artisan db:seed
```

---

# 🚀 Start the API

```bash
php artisan serve --host=192.168.1.33 --port=8000
```

---

# 📡 Authentication Endpoints

| Method | Endpoint      | Description                         |
| ------ | ------------- | ----------------------------------- |
| POST   | `/api/sign-in`  | Generate JWT token                  |
| POST   | `/api/sign-in` | Invalidate token                    |

---

# 🌐 CORS Configuration

Inside `config/cors.php`:

```php
'allowed_origins' => [
    'http://192.168.1.3:5175',
    'http://192.168.1.18:5175',
    'http://192.168.1.32:5175',
];  
```

---