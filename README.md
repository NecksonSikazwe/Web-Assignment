# AgroNest – Agricultural Information and Services Website

**Group:** COFFEESCRIPT
**Academic Year:** 2026
**Project Type:** Web-Based Application

## 1. Project Overview

AgroNest is an agricultural information and services website designed to connect farmers with potential customers while providing access to agricultural products, services and information.

The platform enables registered farmers to showcase their agricultural produce and allows customers to discover available products and contact farmers directly.

AgroNest aims to improve the visibility of agricultural products and make agricultural information and services more accessible through a digital platform.

## 2. Project Objectives

- Provide a digital platform for showcasing agricultural products.
- Improve access to agricultural information and services.
- Enable farmers to register and manage their farm information.
- Allow farmers to publish and manage agricultural products.
- Enable customers to discover available agricultural produce.
- Improve communication between farmers and potential customers.

## 3. Main System Features

### Farmer Management

- Farmer registration.
- Administrator approval of farmer accounts.
- Secure farmer authentication.
- Farmer dashboard.
- Farm management.
- Product management.

### Product Marketplace

- Display available agricultural products.
- Organise products into categories.
- Display product information and images.
- Allow customers to view product details.
- Exclude sold-out products from available listings.

### Administrator Management

- Separate administrator authentication.
- Farmer account management.
- Farmer approval functionality.

### Public Information

- Agricultural information and resources.
- Agricultural products and services.
- Contact information.
- Public marketplace access.

## 4. Target Users

AgroNest is designed for:

- Farmers.
- Schools and institutions.
- Restaurants.
- Traders and retailers.
- Business partners and investors.

Farmers register to manage their farms and products. Customers can browse the public platform without creating accounts.

## 5. Technologies Used

| Technology | Purpose                               |
| ---------- | ------------------------------------- |
| HTML5      | Website structure                     |
| CSS3       | Website styling and layout            |
| JavaScript | Client-side functionality             |
| PHP        | Server-side application logic         |
| MySQL      | Database management                   |
| MySQLi     | PHP-to-MySQL database connectivity    |
| Git        | Version control                       |
| GitHub     | Source code hosting and collaboration |

## 6. Database Structure

The application uses a MySQL database named `agronest`.

The main database tables are:

1. `farmers` – Stores farmer account information.
2. `farms` – Stores farm information.
3. `categories` – Stores product categories.
4. `products` – Stores agricultural product information.
5. `product_images` – Stores product image records.
6. `administrators` – Stores administrator account information.

The database export is provided in:

`database/agronest.sql`

## 7. Installation and Setup

Follow these instructions to run AgroNest on an Ubuntu computer.

### Step 1: Install the required software

```bash
sudo apt update
sudo apt install git php php-mysql mysql-server -y
```

Verify the installations:

```bash
git --version
php --version
mysql --version
```

### Step 2: Clone the repository

```bash
git clone https://github.com/NecksonSikazwe/Web-Assignment.git
```

Navigate into the project:

```bash
cd Web-Assignment
```

### Step 3: Start MySQL

```bash
sudo systemctl start mysql
```

### Step 4: Create the database

```bash
sudo mysql -e "CREATE DATABASE agronest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Step 5: Import the database

```bash
sudo mysql agronest < database/agronest.sql
```

### Step 6: Verify the database

```bash
sudo mysql
```

Execute:

```sql
USE agronest;
SHOW TABLES;
```

The six main tables should appear.

Exit MySQL:

```sql
EXIT;
```

### Step 7: Create a local application database user

Open MySQL:

```bash
sudo mysql
```

Create a dedicated application account:

```sql
CREATE USER 'agronest_app'@'localhost'
IDENTIFIED BY 'YourOwnPassword123!';

GRANT SELECT, INSERT, UPDATE, DELETE
ON agronest.*
TO 'agronest_app'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

Replace the example password with your own.

### Step 8: Configure the database connection

Open:

```bash
nano config/database.php
```

Configure the MySQLi connection with the local credentials created above.

**Important:** Do not commit real database passwords to GitHub. Each developer should keep their local credentials out of version control.

### Step 9: Start the PHP development server

From the project root directory, execute:

```bash
php -S localhost:8000
```

### Step 10: Access AgroNest

Open your browser:

`http://localhost:8000/`

If necessary, access the homepage directly:

`http://localhost:8000/index.html`

## 8. Version Control and Collaboration

AgroNest uses Git and GitHub for source code management and collaboration.

Repository:

[https://github.com/NecksonSikazwe/Web-Assignment](https://github.com/NecksonSikazwe/Web-Assignment)

To obtain the latest project version:

```bash
git pull origin main
```

To upload changes:

```bash
git add .
git commit -m "Describe your changes"
git push
```

Team members should communicate before editing shared files and avoid overwriting one another's work.

## 9. Security Considerations

The application incorporates security measures including:

- Password hashing.
- Prepared SQL statements.
- Authentication and authorization.
- Session management.
- CSRF protection.
- Output escaping.
- Farmer ownership checks on protected operations.

Database credentials should remain local and must not be exposed in the public repository.

## 10. Development Environment

AgroNest is configured for local development using PHP's built-in development server and MySQL.

To stop the server, press:

`Ctrl + C`

## 11. Project Team

**Group:** COFFEESCRIPT

**Project:** AgroNest – Agricultural Information and Services Website

**Academic Year:** 2026

