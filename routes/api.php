<?php

use App\Http\Controllers\Api\V1\ConnectionRequestController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api automatically.
| API Version 1 routes are grouped under /api/v1
|
*/

Route::prefix('v1')->group(function () {
    Route::post('/signup', [ProfileController::class, 'signup'])->name('signup');
    Route::post('/verify-email', [ProfileController::class, 'verifyEmail'])->name('verify-email');
    Route::post('/resend-verification', [ProfileController::class, 'resendVerification']);
    Route::post('/login', [LoginController::class, 'login'])->name('login'); // Move outside auth middleware
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('find-your-life-partner', [ProfileController::class, 'findYourLifePartner'])->name('find-matches');
        Route::get('get-all-profiles', [ProfileController::class, 'getAllProfiles'])->name('get-all-profiles');
        Route::get('/my-profile', [ProfileController::class, 'myProfile'])->name('profile');
        Route::post('/update-profile', [ProfileController::class, 'updateProfile'])->name('update-profile');
        Route::post('/password-change', [LoginController::class, 'changePassword'])->name('password-change');
        Route::post('send-connection-request', [ConnectionRequestController::class, 'sendConnectionRequest'])->name('send-connection-request');
        Route::get('get-connection-requests', [ConnectionRequestController::class, 'getConnectionRequests'])->name('connection-requests');
        Route::post('respond-connection-request', [ConnectionRequestController::class, 'respondConnectionRequest'])->name('respond-connection-request');
        Route::post('show-other-user-profile-details/{id}', [ConnectionRequestController::class, 'showOtherUserProfileDetails'])->name('show-other-user-profile-details');
        Route::get('get-my-connections', [ConnectionRequestController::class, 'getMyConnections'])->name('my-connections');

        Route::post('posts', [PostController::class, 'createPost'])->name('posts.create');
        Route::get('posts/connections', [PostController::class, 'getConnectionsPosts'])->name('posts.connections');
        Route::get('posts/my', [PostController::class, 'getMyPosts'])->name('posts.my');
        Route::get('posts/{id}', [PostController::class, 'getPost'])->name('posts.show');
        Route::delete('posts/{id}', [PostController::class, 'deletePost'])->name('posts.delete');

        Route::post('posts/{id}/like', [PostController::class, 'toggleLike'])->name('posts.like');
        Route::get('posts/{id}/likes', [PostController::class, 'getLikes'])->name('posts.likes');

        Route::post('posts/{id}/comments', [PostController::class, 'addComment'])->name('posts.comments.add');
        Route::get('posts/{id}/comments', [PostController::class, 'getComments'])->name('posts.comments.list');
        Route::delete('posts/{postId}/comments/{commentId}', [PostController::class, 'deleteComment'])->name('posts.comments.delete');
    });
});
