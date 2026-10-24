<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// ── Controllers: Auth & Shared ──────────────────────────────────────────────
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventBannerController;
use App\Http\Controllers\Api\EventWizardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\OrganizerProfileController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\OrganizerOrderController;

// ── Controllers: Admin ──────────────────────────────────────────────────────
use App\Http\Controllers\Api\Admin\DashboardController        as AdminDashboardController;
use App\Http\Controllers\Api\Admin\EventController            as AdminEventController;
use App\Http\Controllers\Api\Admin\OrderController            as AdminOrderController;
use App\Http\Controllers\Api\Admin\OrganizerController        as AdminOrganizerController;
use App\Http\Controllers\Api\Admin\UserController             as AdminUserController;
use App\Http\Controllers\Api\Admin\FormTemplateController;
use App\Http\Controllers\Api\Admin\PaymentMethodController    as AdminPaymentMethodController;
use App\Http\Controllers\Api\Admin\TwoFactorController;

// ── Controllers: Finance ────────────────────────────────────────────────────
use App\Http\Controllers\Api\Finance\FinanceAnalyticsController;
use App\Http\Controllers\Api\Finance\FinanceAffiliateController;
use App\Http\Controllers\Api\Finance\FinanceArtistController;

// ── Controllers: Domain Dashboard ───────────────────────────────────────────
use App\Http\Controllers\Api\AffiliatorController;
use App\Http\Controllers\Api\ArtistController;

// ============================================================================
// HEALTH CHECK (Public, no auth)
// ============================================================================
Route::get('/health', function () {
    return response()->json([
        'status'    => 'OK',
        'timestamp' => now(),
        'database'  => DB::connection()->getPdo() ? 'connected' : 'disconnected',
    ]);
});

// ============================================================================
// WEBHOOK — Midtrans (Public, throttle ketat)
// ============================================================================
Route::middleware('throttle:webhook')->group(function () {
    Route::post('/webhook/midtrans', [WebhookController::class, 'handle'])->name('webhook.midtrans');
});

// ============================================================================
// AUTH (Public, throttle sedang)
// ============================================================================
Route::middleware('throttle:auth')->prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    Route::get('google/redirect',  [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback',  [AuthController::class, 'handleGoogleCallback']);
});

// ============================================================================
// PUBLIC ROUTES (Tanpa login)
// ============================================================================
Route::middleware('throttle:api')->group(function () {

    // Kategori & Venue
    Route::get('categories',           [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('venues',               [VenueController::class, 'index']);
    Route::get('venues/{venue}',       [VenueController::class, 'show']);

    // Events — WAJIB: rute spesifik di atas rute dynamic {event}
    Route::get('events/featured',     [EventController::class, 'featured']);
    Route::get('events/closing-soon', [EventController::class, 'closingSoon']);
    Route::get('events/latest',       [EventController::class, 'latest']);
    Route::get('events',              [EventController::class, 'index']);
    Route::get('events/{event}',      [EventController::class, 'show']);

    // Organizer invite flow
    Route::get('organizers/invite/{token}/check',  [AdminOrganizerController::class, 'checkInvite']);
    Route::post('auth/register-organizer/{token}', [AuthController::class, 'registerOrganizer']);

    // Shared magic-link data untuk EO (transparansi finance)
    Route::get('shared/organizers/{id}', [AdminOrganizerController::class, 'sharedData'])->name('api.shared.organizer');
});

// ============================================================================
// PROTECTED ROUTES (Wajib login: auth:sanctum)
// ============================================================================
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // ── Auth: me & logout ───────────────────────────────────────────────────
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // ── Profile (semua role) ────────────────────────────────────────────────
    Route::prefix('profile')->group(function () {
        Route::get('/',  [ProfileController::class, 'show']);
        Route::put('/',  [ProfileController::class, 'update']);
    });

    // ── Payment Methods (semua role, untuk halaman checkout) ────────────────
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

    // ── Orders (user/buyer) ─────────────────────────────────────────────────
    Route::prefix('orders')->group(function () {
        Route::get('/',             [OrderController::class, 'index']);
        Route::post('/',            [OrderController::class, 'store']);
        Route::get('/{id}',         [OrderController::class, 'show']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // ── Tickets (user/buyer) ────────────────────────────────────────────────
    Route::prefix('tickets')->group(function () {
        Route::get('/',              [TicketController::class, 'index']);
        Route::get('/{id}',          [TicketController::class, 'show']);
        Route::get('/{id}/qr',       [TicketController::class, 'qrCode']);
        Route::get('/{id}/qr-data',  [TicketController::class, 'qrData']);
        Route::get('/{id}/download', [TicketController::class, 'download']);
    });

    // ── Payments (user/buyer) ───────────────────────────────────────────────
    Route::prefix('payments')->group(function () {
        Route::post('/orders/{orderId}',       [PaymentController::class, 'create']);
        Route::get('/orders/{orderId}/status', [PaymentController::class, 'status'])
            ->middleware('throttle:5,1');
    });

    // ==========================================================================
    // ORGANIZER DASHBOARD
    // Guard: role 'organizer' atau 'admin', dan sudah punya profil terverifikasi
    // ==========================================================================
    Route::middleware(['role:organizer|admin', 'organizer.profile'])->group(function () {

        // Profile EO
        Route::prefix('organizer/profile')->group(function () {
            Route::get('/',  [OrganizerProfileController::class, 'show']);
            Route::post('/', [OrganizerProfileController::class, 'store']);
        });

        // Dashboard stats & data pendukung wizard
        Route::get('organizer/dashboard/stats', [OrganizerDashboardController::class, 'index']);
        Route::get('organizer/form-templates', function () {
            return response()->json(\App\Models\BuyerFormTemplate::where('is_active', true)->get());
        });
        Route::get('organizer/artists', function () {
            return response()->json([
                'success' => true,
                'data'    => \App\Models\User::role('artist')->select('id', 'name')->get(),
            ]);
        });

        // Event Management (CRUD biasa)
        Route::get('my-events',              [EventController::class, 'myEvents']);
        Route::post('events',                [EventController::class, 'store']);
        Route::put('events/{event}',         [EventController::class, 'update']);
        Route::delete('events/{event}',      [EventController::class, 'destroy']);
        Route::post('events/{event}/banner', [EventBannerController::class, 'upload']);

        // Event Wizard — WAJIB: rute wizard spesifik SEBELUM rute {id} dynamic
        Route::post('events/wizard/step-1',       [EventWizardController::class, 'saveStepOne']);
        Route::post('events/wizard/step-2',       [EventWizardController::class, 'saveStepTwo']);
        Route::post('events/wizard/step-3',       [EventWizardController::class, 'saveStepThree']);
        Route::get('events/wizard/{id}',          [EventWizardController::class, 'getWizardEvent']);
        Route::post('events/{id}/request-publish', [EventWizardController::class, 'requestPublish']);

        // Ticket Tier — manajemen lanjutan
        Route::post('events/wizard/tickets/{ticketTier}/open-now', [EventWizardController::class, 'openTicketNow']);
        Route::put('events/wizard/tickets/{ticketTier}/extend',    [EventWizardController::class, 'extendTicket']);

        // Venue
        Route::post('venues',           [VenueController::class, 'store']);
        Route::put('venues/{venue}',    [VenueController::class, 'update']);
        Route::delete('venues/{venue}', [VenueController::class, 'destroy']);

        // Orders event milik EO
        Route::get('organizer/orders',        [OrganizerOrderController::class, 'index']);
        Route::get('organizer/orders/export', [OrganizerOrderController::class, 'export']);

        // Wallet EO
        Route::get('organizer/wallet', function () {
            $user   = auth()->user();
            $wallet = $user->wallet;
            return response()->json([
                'success' => true,
                'data'    => [
                    'balance'   => $wallet ? (float) $wallet->balance : 0,
                    'mutations' => $wallet ? $wallet->mutations()->latest()->take(20)->get() : [],
                ],
            ]);
        });
    });

    // ==========================================================================
    // FINANCE DASHBOARD
    // Guard: role 'finance'
    // ==========================================================================
    Route::middleware('role:finance')->prefix('finance')->group(function () {

        // Overview & Analytics
        Route::get('/overview',            [FinanceAnalyticsController::class, 'getOverview']);
        Route::get('/marketing-metrics',   [FinanceAnalyticsController::class, 'getMarketingMetrics']);
        Route::get('/demographics',        [FinanceAnalyticsController::class, 'getDemographics']);
        Route::get('/revenue-by-category', [FinanceAnalyticsController::class, 'getRevenueByCategory']);
        Route::get('/top-affiliates',      [FinanceAnalyticsController::class, 'getTopAffiliates']);
        Route::get('/vaults',              [FinanceAnalyticsController::class, 'getVaults']);
        Route::get('/transactions',        [FinanceAnalyticsController::class, 'getTransactions']);

        // Affiliate management
        Route::post('/affiliate/{eventId}/toggle-postpaid', [FinanceAffiliateController::class, 'togglePostpaid']);
        Route::post('/affiliate/{eventId}/commission-rate', [FinanceAffiliateController::class, 'setCommissionRate']);
        Route::post('/affiliate/payout',                    [FinanceAffiliateController::class, 'payoutAffiliator']);

        // Artist transfer
        Route::post('/artist/transfer', [FinanceArtistController::class, 'transferToArtist']);

        // Platform fee per-EO
        Route::get('/platform-fee',               [FinanceAnalyticsController::class, 'getPlatformFees']);
        Route::put('/platform-fee/{organizerId}', [FinanceAnalyticsController::class, 'updatePlatformFee']);

        // Withdrawal requests
        Route::get('/withdrawals',               [FinanceAnalyticsController::class, 'getWithdrawals']);
        Route::post('/withdrawals/{id}/approve', [FinanceAnalyticsController::class, 'approveWithdrawal']);
        Route::post('/withdrawals/{id}/reject',  [FinanceAnalyticsController::class, 'rejectWithdrawal']);
    });

    // ==========================================================================
    // ARTIST DASHBOARD
    // Guard: role 'artist'
    // ==========================================================================
    Route::middleware('role:artist')->prefix('artist')->group(function () {
        Route::get('/wallet',                [ArtistController::class, 'getWallet']);
        Route::get('/stats',                 [ArtistController::class, 'getDashboardStats']);
        Route::get('/audience-demographics', [ArtistController::class, 'getAudienceDemographics']);

        // Guest list
        Route::get('/guestlist',           [ArtistController::class, 'getGuestList']);
        Route::post('/guestlist',          [ArtistController::class, 'addGuest']);
        Route::delete('/guestlist/{id}',   [ArtistController::class, 'removeGuest']);

        // Technical riders
        Route::get('/riders',         [ArtistController::class, 'getRiders']);
        Route::post('/riders',        [ArtistController::class, 'uploadRider']);
        Route::delete('/riders/{id}', [ArtistController::class, 'deleteRider']);
    });

    // ==========================================================================
    // AFFILIATOR DASHBOARD
    // Guard: role 'affiliator'
    // ==========================================================================
    Route::middleware('role:affiliator')->prefix('affiliator')->group(function () {
        Route::get('/marketplace',   [AffiliatorController::class, 'getMarketplace']);
        Route::get('/performance',   [AffiliatorController::class, 'getPerformance']);

        // Link generator (UTM tracking)
        Route::post('/links/generate', [AffiliatorController::class, 'generateLink']);
        Route::get('/links',           [AffiliatorController::class, 'getLinks']);

        // Wallet affiliator
        Route::get('/wallet', function () {
            $user   = auth()->user();
            $wallet = $user->wallet;
            return response()->json([
                'success' => true,
                'data'    => [
                    'balance'   => $wallet ? (float) $wallet->balance : 0,
                    'mutations' => $wallet ? $wallet->mutations()->latest()->take(20)->get() : [],
                ],
            ]);
        });

        // Request payout
        Route::post('/payout', [AffiliatorController::class, 'requestPayout']);
    });

    // ==========================================================================
    // ADMIN — Super Admin: 2FA
    // Guard: role 'admin' + middleware superadmin
    // ==========================================================================
    Route::middleware(['role:admin', 'superadmin'])->prefix('admin')->group(function () {
        Route::post('/2fa/generate', [TwoFactorController::class, 'generate']);
        Route::post('/2fa/verify',   [TwoFactorController::class, 'verify']);
    });

    // ==========================================================================
    // ADMIN DASHBOARD
    // Guard: role 'admin' + throttle ketat
    // ==========================================================================
    Route::middleware(['role:admin', 'throttle:admin'])->prefix('admin')->group(function () {

        // Dashboard overview
        Route::get('/dashboard',                 [AdminDashboardController::class, 'index']);
        Route::get('/dashboard/recent-orders',   [AdminDashboardController::class, 'recentOrders']);
        Route::get('/dashboard/top-events',      [AdminDashboardController::class, 'topEvents']);
        Route::get('/dashboard/revenue-chart',   [AdminDashboardController::class, 'revenueChart']);
        Route::get('/dashboard/payment-methods', [AdminDashboardController::class, 'paymentMethods']);

        // Event Management
        Route::get('/events',                          [AdminEventController::class, 'index']);
        Route::post('/events/{event}/approve',         [AdminEventController::class, 'approve']);
        Route::post('/events/{event}/reject',          [AdminEventController::class, 'reject']);
        Route::post('/events/{event}/toggle-feature',  [AdminEventController::class, 'toggleFeature']);
        Route::delete('/events/{event}',               [AdminEventController::class, 'destroy']);

        // Event drill-down: seluruh order untuk 1 event (halaman detail event admin)
        Route::get('/events/{event}/orders', [AdminOrderController::class, 'byEvent']);

        // Order Management
        Route::get('/orders',                 [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}',         [AdminOrderController::class, 'show']);
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);

        // Kategori
        Route::post('/categories',              [CategoryController::class, 'store']);
        Route::put('/categories/{category}',    [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Venue
        Route::delete('/venues/{venue}', [VenueController::class, 'destroy']);

        // Organizer Management
        Route::get('/organizers',                        [AdminOrganizerController::class, 'index']);
        Route::post('/organizers',                       [AdminOrganizerController::class, 'store']);
        Route::put('/organizers/{user}',                 [AdminOrganizerController::class, 'update']);
        Route::delete('/organizers/{user}',              [AdminOrganizerController::class, 'destroy']);
        Route::post('/organizers/{user}/toggle-active',  [AdminOrganizerController::class, 'toggleActive']);
        Route::post('/organizers/{user}/magic-link',     [AdminOrganizerController::class, 'generateMagicLink']);
        Route::post('/organizers/invite',                [AdminOrganizerController::class, 'generateInvite']);

        // User Management (suspend/ban semua role)
        Route::get('/users',                              [AdminUserController::class, 'index']);
        Route::post('/users/{user}/toggle-active',        [AdminUserController::class, 'toggleActive']);
        Route::post('/users/{user}/upgrade-to-organizer', [AdminUserController::class, 'upgradeToOrganizer']);

        // Affiliator Management (suspend/ban)
        Route::get('/affiliators',                       [AdminUserController::class, 'indexAffiliators']);
        Route::post('/affiliators/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);

        // Form Templates
        Route::get('/form-templates',         [FormTemplateController::class, 'index']);
        Route::post('/form-templates',        [FormTemplateController::class, 'store']);
        Route::put('/form-templates/{id}',    [FormTemplateController::class, 'update']);
        Route::delete('/form-templates/{id}', [FormTemplateController::class, 'destroy']);

        // Payment Methods
        Route::get('/payment-methods',                         [AdminPaymentMethodController::class, 'index']);
        Route::put('/payment-methods/{paymentMethod}',         [AdminPaymentMethodController::class, 'update']);
        Route::post('/payment-methods/{paymentMethod}/toggle', [AdminPaymentMethodController::class, 'toggleActive']);
        Route::post('/payment-methods/{paymentMethod}/ping',   [AdminPaymentMethodController::class, 'healthCheck']);
    });
});
