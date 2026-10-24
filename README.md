# TicketGo

A multi-role event ticketing marketplace. Organizers create events and ticket tiers, buyers purchase tickets through Midtrans, and the finance team handles payouts to organizers, artists, and affiliates.

## Features

- **Roles**: buyer, organizer, artist, affiliator, finance, admin, and super admin, each with its own route group and middleware.
- **Authentication**: email/password with OTP verification, password reset, Google sign-in (Socialite), and TOTP two-factor login for admins.
- **Events**: categories, venues, a multi-step event wizard, ticket tiers, banners, plus featured, latest, and closing-soon listings.
- **Orders and payments**: Midtrans payments with webhook handling, and a scheduled command that releases unpaid orders.
- **Organizers**: invite-link onboarding, profile completion, an order dashboard, and Excel export.
- **Finance**: wallets and wallet mutations, artist royalty transfers, affiliate commissions, and analytics.
- **Security**: role-scoped rate limiting and input sanitization middleware.

## Tech stack

| Part | Stack |
|---|---|
| `backend/` | Laravel 12, PostgreSQL, Sanctum, Socialite, Midtrans, Laravel Excel, bacon-qr-code |
| `frontend/` | React 19, Vite, Tailwind CSS, React Router, Axios |

## Running locally

Backend:

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed   # seeds one demo account per role (password: "password")
php artisan serve            # API at http://localhost:8000/api
```

Frontend:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev                  # http://localhost:5173
```

## Project structure

```
backend/
  app/Http/Controllers/Api/          public, buyer, and organizer endpoints
  app/Http/Controllers/Api/Admin/    admin panel (events, organizers, users, 2FA)
  app/Http/Controllers/Api/Finance/  payouts and analytics
  app/Services/PaymentService.php    Midtrans integration
  routes/api.php                     routes grouped by role
frontend/
  src/pages/                         Home, EventDetail, Login, Register, Dashboard
  src/context/AuthContext.jsx        auth state
  src/services/api.js                Axios client
```
