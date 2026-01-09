# Multi-Tenant POS Backend System

A complete, production-ready **Point of Sale (POS) & Inventory Management Backend** built with **Laravel**. The system is designed with **multi-tenancy**, **role-based access control**, and **scalable architecture** suitable for SaaS use cases.

---

## Features

- **Multi-Tenant Architecture** – Strict data isolation per business
- **Role-Based Access Control** – Owner & Staff roles
- **Inventory Management** – Products with stock tracking
- **Order Processing** – Create, update, cancel orders with stock sync
- **Customer Management** – Customer records per tenant
- **Reporting Module** – Daily sales, top products, low stock
- **RESTful API** – Clean JSON APIs
- **Security** – Laravel Sanctum, validation, rate limiting
- **Performance** – Indexed DB, eager loading

---

## Technology Stack

- **PHP 8.2+**
- **Laravel 10.x**
- **MySQL 8+ / MariaDB**
- **SQLite** (testing)
- **Laravel Sanctum** (authentication)
- **PHPUnit** (testing)
- **XAMPP** (local development)

---

## Project Setup Instructions

### Prerequisites

- PHP 8.2+
- Composer
- MySQL 8+
- XAMPP / LAMP / WAMP

---

### Step 1: Clone & Install

```bash
git clone https://github.com/shakil1424/pos-backend.git
cd pos-backend
composer install
cp .env.example .env
php artisan key:generate
```

---

### Step 2: Database Configuration
- Create a MySQL database named `pos_backend`.
- Create `.env` and update the following:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_backend
DB_USERNAME=root
DB_PASSWORD=
```


---

### Step 3: Migrate & Seed

```bash
php artisan migrate
php artisan db:seed
```

---

### Step 4: XAMPP Virtual Host

**Apache vhost:**

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/pos-backend/public"
    ServerName pos-backend.test
    <Directory "C:/xampp/htdocs/pos-backend/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Hosts file:**

```
127.0.0.1 pos-backend.test
```

Restart Apache.

---

### Step 5: Application Setup

In .env file set:

```env
APP_URL=http://pos-backend.test
```

```bash
php artisan sanctum:install
php artisan storage:link
php artisan config:clear
php artisan config:cache
```

---

### Step 6: Testing

- Set up testing environment:
- Create `.env.testing` from `.env.testing.example`
- Update `.env.testing` for SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```
- Run tests using following commands:
```bash
php artisan test 
php artisan test tests/Feature/CustomerTest.php
php artisan test tests/Feature/OrderTest.php
php artisan test tests/Feature/ProductTest.php
php artisan test tests/Featests/Feature/AuthTest.php
php artisan test tests/Featests/Feature/ReportTest.php
php artisan test tests/Feature/TenantIsolationTest.php
````
- For specific feature
```bash
php artisan test tests/Feature/AuthTest.php --filter=test_user_can_register
```

---

## Architecture Overview

### Project Structure

```
pos-backend/
├── app/
│   ├── Console/Commands
│   ├── Http/
│   │   ├── Controllers/Api
│   │   ├── Middleware
│   │   ├── Requests
│   │   └── Resources
│   ├── Jobs
│   ├── Mail
│   ├── Models
│   ├── Policies
│   ├── Providers
├── config/
├── database/
│   ├── migrations
│   ├── factories
│   └── seeders
├── routes/
├── tests/
└── storage/
```

---

### Core Components

- **Tenant** – Business entity
- **User** – Belongs to tenant
- **Product** – Inventory per tenant
- **Order** – Order details per tenant
- **Customer** – CRM per tenant

---

### Request Flow

1. User authenticates using Sanctum
2. Client sends `tenant-id` in request header
3. Tenant middleware reads `tenant-id` and sets tenant context
4. Policies enforce role-based permissions within tenant scope

## Multi-Tenancy Strategy

### Tenant Hierarchy

```
Tenant
├── Users
├── Products
├── Customers
├── Orders
```

---

### Design Approach

#### 1. Single Database, Tenant ID Separation

- All tenant-owned tables contain `tenant_id`
- Foreign keys ensure referential integrity
- Physical separation is avoided for operational simplicity

---

#### 2. Tenant Identification

- Client sends `X-Tenant-ID` in every API request header
- Tenant is validated (exists + active) in middleware
- Tenant context is attached to the request and config

---

#### 3. Middleware-Based Tenant Resolution

```php
Route::middleware(['auth:sanctum', 'api.tenant'])->group(function () {
    // Tenant-aware API routes
});
```
- TenantMiddleware: Reads X-Tenant-ID

- Validates tenant status

- Injects tenant into request ($request->tenant)

- Stores tenant ID in config for shared access

- TenantScopeMiddleware (optional, explicit):
  Applies runtime global scopes inside middleware.
---

## Key Design Decisions & Trade-offs

### 1. Database Strategy
**Decision:** Single database with tenant_id

**Pros:**
- Easy maintenance
- Lower cost
- Simple backups

**Cons:**
- Scaling limits
- Shared risk surface

**Trade-off:** Acceptable for hundreds of tenants

---

### 2. Authentication
**Decision:** Laravel Sanctum

**Pros:**
- API + SPA support
- Built-in security

**Cons:**
- DB token storage

---

### 3. API Design
**Decision:** RESTful JSON APIs

**Pros:**
- Simple integration
- Predictable

**Cons:**
- Over-fetching risk

---

### 4. Testing
**Decision:** SQLite for tests

**Pros:**
- Fast execution
- CI friendly

**Cons:**
- MySQL edge cases not covered

---

### 5. Queue Strategy

**Decision:** Hybrid approach — synchronous for small datasets, asynchronous for large reports

**Rationale:**
- Reports covering a **short date range** are generated **synchronously** for immediate API response
- Reports exceeding a configurable threshold are **queued and delivered via email**
- Prevents long-running HTTP requests and timeouts
- Balances user experience with system performance

**Implementation Details:**
- Date range is evaluated at runtime
- Threshold is configurable via `reports.immediate_threshold_days`
- Laravel Jobs are used for background processing
- Queues are introduced only when computational cost justifies it


---

## API Endpoints

### Auth
- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/user`
- `GET /api/staff/register`

### Products
- `GET /api/products`
- `POST /api/products`
- `PUT /api/products/{id}`
- `GET /api/products/{id}`
- `DELETE /api/products/{id}`

### Customers
- `GET /api/customers`
- `POST /api/customers`
- `PUT /api/customers/{id}`
- `GET /api/customers/{id}`
- `DELETE /api/customers/{id}`

### Orders
- `GET /api/orders`
- `POST /api/orders`
- `PUT /api/orders/{id}`
- `DELETE /api/orders/{id}`
- `GET /api/orders/{id}`

### Reports
- `GET /api/reports/daily-sales`
- `GET /api/reports/top-products`
- `GET /api/reports/low-stock`

---

## Security Considerations

- API rate limiting
- Input validation
- SQL injection protection
- CSRF protection
- Proper CORS configuration

---

## License

Proprietary software. All rights reserved.

