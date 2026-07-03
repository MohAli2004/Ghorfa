<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MainController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\LandlordMiddleware;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAmenityController;
use App\Http\Controllers\AdminRuleController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\LandlordController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TransactionController;

//-----------Pages Routes-----------------
Route::get( '/', [MainController::class,"homePage"])->name(name: 'home');
Route::prefix('profile')->middleware('auth')->group(function (): void {
    Route::get('/info', [MainController::class, 'profileInfo'])->name('profileInfo');
    Route::get('/favorites', [MainController::class, 'profileFavorites'])->name('profileFavorites');
    Route::get('/properties',  [MainController::class, 'profileProperties'])->name('profileProperties');
    Route::get('/security',  [MainController::class, 'profileSecurity'])->name('profileSecurity');
    Route::get('/transactions', [MainController::class, 'profileTransactions'])->name('profile.transactions');
    Route::put('/info', [MainController::class, 'updateProfile'])->name('profile.update');
    Route::put('/photo', [MainController::class, 'updateProfilePhoto'])->name('profile.update.photo');
});
Route::get('/profile', [MainController::class,"profilePage"])->name('profile');
Route::get('/search', [PropertyController::class, 'index'])->name('search');

//--------------Recommendation Routes-------------------
Route::get('/recommendations', [App\Http\Controllers\RecommendationController::class, 'index'])
    ->name('recommendations.index');
Route::get('/api/recommendations', [App\Http\Controllers\RecommendationController::class, 'api'])
    ->name('recommendations.api');
Route::post('/properties/{property}/track-click', [App\Http\Controllers\RecommendationController::class, 'trackClick'])
    ->name('properties.track-click')
    ->middleware('auth');
Route::post('/properties/{property}/track-contact', [App\Http\Controllers\RecommendationController::class, 'trackContact'])
    ->name('properties.track-contact')
    ->middleware('auth');

Route::get('/list-property', [MainController::class, 'propertyPage'])->name('list-property')->middleware(['auth', LandlordMiddleware::class]);
//--------------Map Routes-------------------
Route::get('/map', [MapController::class, 'index'])->name('map');
Route::post('/map/geocode', [MapController::class, 'geocode']);
Route::post('/map/reverse-geocode', [MapController::class, 'reverseGeocode'])->name('map.reverse-geocode');
Route::get('/map-test', function() { return view('map-test'); });

//--------------Authentication Routes-------------------
Route::get('/login', [AuthenticationController::class, 'loginPage'])->name( name: 'login');
Route::post('/login', [AuthenticationController::class, 'submitLogin'])->name('submit.login');

Route::get('/register', [AuthenticationController::class, 'registerPage'])->name('register');
Route::post('/register', [AuthenticationController::class, 'submitRegister'])->name('submit.register');

Route::post('/logout', [AuthenticationController::class, 'logout'])->name('logout');

Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web')->name('csrf.token');

//---------------Properties Routes--------------
Route::post('/submit-listing', [PropertyController::class, 'submitListing'])->name('submit-listing')->middleware(['auth', LandlordMiddleware::class]);
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
Route::post('/properties/{property}/like', [PropertyController::class, 'like'])->name('property.like')->middleware('auth');

// Review routes
Route::post('/properties/{property}/reviews', [App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store')->middleware('auth');
Route::get('/properties/{property}/reviews', [App\Http\Controllers\ReviewController::class, 'index'])->name('reviews.index');
Route::put('/reviews/{review}', [App\Http\Controllers\ReviewController::class, 'update'])->name('reviews.update')->middleware('auth');
Route::delete('/reviews/{review}', [App\Http\Controllers\ReviewController::class, 'destroy'])->name('reviews.destroy')->middleware('auth');


//-------------------for property setting list--------------------
Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

//----------------Search Routes--------------------------
Route::get('/filter-search', [PropertyController::class, 'filterSearch'])->name('filter-search');

//----------------Landlord Routes-------------------
Route::middleware(['auth'])->prefix('landlord')->group(function () {
    Route::get('/apply', [LandlordController::class, 'showApplyForm'])->name('landlord.apply');
    Route::post('/apply', [LandlordController::class, 'submitApplication'])->name('landlord.submit-application');
});

Route::middleware(['auth', LandlordMiddleware::class])->prefix('landlord')->group(function () {
    Route::get('/dashboard', [LandlordController::class, 'dashboard'])->name('landlord.dashboard');
    Route::post('/mark-section-seen', [LandlordController::class, 'markSectionSeen'])->name('landlord.mark-section-seen');
    Route::post('/properties/{property}/resubmit', [LandlordController::class, 'resubmitProperty'])->name('landlord.properties.resubmit');
    Route::get('/properties', [LandlordController::class, 'properties'])->name('landlord.properties');
    Route::get('/transactions/{transaction}/export-pdf', [LandlordController::class, 'exportRequestPdf'])->name('landlord.transactions.export-pdf');
});

//----------------Admin Routes-------------------
Route::middleware(['web', 'auth', AdminMiddleware::class])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/mark-section-seen', [AdminController::class, 'markSectionSeen'])->name('admin.mark-section-seen');
    Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('admin.users.show');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::get('/pending-applications', [AdminController::class, 'getPendingApplications'])->name('admin.pending-applications');
    Route::post('/landlord-applications/{application}/approve', [AdminController::class, 'approveLandlordApplication'])->name('admin.landlord.approve');
    Route::post('/landlord-applications/{application}/reject', [AdminController::class, 'rejectLandlordApplication'])->name('admin.landlord.reject');
    Route::post('/properties/{property}/approve', [AdminController::class, 'approveProperty'])->name('admin.properties.approve');
    Route::post('/properties/{property}/reject', [AdminController::class, 'rejectProperty'])->name('admin.properties.reject');

    Route::name('admin.')->group(function (): void {
        Route::resource('amenities', AdminAmenityController::class)->except(['show']);
        Route::resource('rules', AdminRuleController::class)->except(['show']);
    });
});

//----------------Notification Routes-------------------
Route::middleware(['auth'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/history', [NotificationController::class, 'history'])->name('notifications.history');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
});

//----------------Transaction Routes-------------------
Route::middleware(['auth'])->prefix('transactions')->group(function () {
    // Create new transaction request
    Route::post('/', [TransactionController::class, 'store'])->name('transactions.store');
    
    // View transaction details
    Route::get('/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    
    // Download transaction report
    Route::get('/{transaction}/download-report', [TransactionController::class, 'downloadReport'])->name('transactions.download-report');
    
    // Workflow state transitions
    Route::post('/{transaction}/generate-contract', [TransactionController::class, 'generateContract'])->name('transactions.generate-contract')->middleware(AdminMiddleware::class);
    Route::post('/{transaction}/approve-contract', [TransactionController::class, 'approveContract'])->name('transactions.approve-contract');
    Route::post('/{transaction}/reject-contract', [TransactionController::class, 'rejectContract'])->name('transactions.reject-contract');
    Route::post('/{transaction}/confirm', [TransactionController::class, 'confirm'])->name('transactions.confirm')->middleware(AdminMiddleware::class);
    Route::post('/{transaction}/confirm-payment', [TransactionController::class, 'confirmPayment'])->name('transactions.confirm-payment');
    Route::post('/{transaction}/complete', [TransactionController::class, 'complete'])->name('transactions.complete')->middleware(AdminMiddleware::class);
    Route::post('/{transaction}/cancel', [TransactionController::class, 'cancelByBuyer'])->name('transactions.cancel');
    Route::post('/{transaction}/cancel-seller', [TransactionController::class, 'cancelBySeller'])->name('transactions.cancel-seller');
    Route::post('/{transaction}/request-refund', [TransactionController::class, 'requestRefund'])->name('transactions.request-refund');
    Route::post('/{transaction}/confirm-refund', [TransactionController::class, 'confirmRefund'])->name('transactions.confirm-refund');
});