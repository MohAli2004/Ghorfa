# 🏠 Ghorfa – Property Rental Management Website

Ghorfa is a web-based property rental management system built with **Laravel 11** and **PHP 8.2**.  
It allows admins and property managers to easily list, manage, and track rental properties, tenants, and payments.

---

## 📌 Prerequisites

- **XAMPP** installed with:
  - PHP 8.2+
  - MySQL
  - Apache server enabled
- Composer installed  
- Git installed

---

## ⚙️ Installation

1. **👤 User Configuration (for new developers)**
Before making commits, set your Git username and email so commits are correctly attributed:
```bash
git config --global user.name "Your Name"
git config --global user.email "youremail@example.com"
```

2. Clone the repository  
```bash
git clone https://github.com/your-username/ghorfa.git
```

3. Change directory to access the root folder of the project:
```bash
cd Ghorfa
```

4. copy .env.example to .env and update mysql details
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=Ghorfa-Project-DB
DB_USERNAME=root
DB_PASSWORD=
```

5. Run composer install:
```bash
composer install
```

6. Generate application key:
```bash
php artisan key:generate
```

7. Run the migrations inside the container, and then clear the cache:
```bash
php artisan migrate
php artisan cache:clear
```

8. Run seeders in this order:
```bash
php artisan db:seed
```

You need to update the openai keys in env file. This requires admin support.
