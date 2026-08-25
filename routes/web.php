<?php

declare(strict_types=1);

use App\Http\Controllers\Store\AddressController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CouponController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\InquiryController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\ProductQuestionController;
use App\Http\Controllers\Store\ProductReviewController;
use App\Http\Controllers\Store\ProfileController;
use App\Http\Controllers\Store\ReturnController;
use App\Http\Controllers\Store\SocialLoginController;
use Illuminate\Support\Facades\Route;

/*
| 고객(스토어프론트) 전용 라우트.
| 관리자 라우트는 routes/admin.php 에 둔다 (CLAUDE.md §7.4).
|
| Fortify 가 /login, /register, /forgot-password 등을 스스로 등록한다 (§12.2).
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
| 간편로그인. /login, /register 화면(둘 다 Fortify 뷰)에서 버튼으로 여기로 온다.
| provider 는 kakao|naver 로만 열어둔다 — 라우트 밖 값은 컨트롤러까지 못 온다.
*/
Route::controller(SocialLoginController::class)->prefix('login/{provider}')
    ->whereIn('provider', ['kakao', 'naver'])
    ->name('social.')->group(function () {
        Route::get('redirect', 'redirect')->name('redirect');
        Route::get('callback', 'callback')->name('callback');
    });

/*
| 간편로그인 추가입력. 제공자가 이메일을 안 줄 때만 거치는 화면이다(카카오).
| {provider} 를 URL 에 두지 않는다 — 소셜 신원은 전부 세션에서 꺼낸다.
*/
Route::controller(SocialLoginController::class)->prefix('login/social/complete')->group(function () {
    Route::get('/', 'complete')->name('social.complete');
    Route::post('/', 'storeComplete')->name('social.complete.store');
});

Route::controller(ProductController::class)->prefix('products')->name('products.')->group(function () {
    Route::get('/', 'index')->name('index');

    // slug 는 한글을 담을 수 있다. 라우트 파라미터는 자동으로 디코딩된다.
    Route::get('{slug}', 'show')->name('show');
});

/*
| 장바구니. 비회원도 쓸 수 있으므로 auth 미들웨어를 걸지 않는다 (schema-draft.md §3.1).
| 주인 판별은 CartOwnerResolver 가 한다 — 회원이면 user_id, 아니면 세션 토큰.
*/
Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::delete('/', 'clear')->name('clear');
    Route::put('items/{item}', 'update')->name('items.update');
    Route::delete('items/{item}', 'destroy')->name('items.destroy');
});

/*
| 주문.
|
| **구매는 회원만 할 수 있다.** 예전에는 비회원 주문을 허용했지만(schema-draft.md §4.2)
| 정책이 바뀌어 결제 경로 전체에 auth 를 건다.
|
| 장바구니는 여전히 비회원도 쓴다 — 담아두고 로그인하면 그대로 넘어온다
| (MergeGuestCartOnLogin). 로그인을 요구하는 지점은 '담기' 가 아니라 '주문' 이다.
|
| `checkout` 이 GET 이라 auth 미들웨어가 intended URL 을 제대로 저장한다 —
| 로그인하면 주문서로 자동 복귀한다.
*/
Route::controller(OrderController::class)->prefix('orders')->name('orders.')->group(function () {
    /*
     * 바로구매만 auth 미들웨어를 안 건다. POST 라 intended URL 이 referer 로 잡혀
     * 부정확하고, 안내 문구도 밋밋해진다 — 컨트롤러가 직접 검사해서 '보던 상품' 으로
     * 정확히 되돌린다. 실제 차단은 뒤이어 거치는 checkout/store 의 auth 가 보장한다.
     */
    Route::post('direct', 'direct')->name('direct');

    Route::middleware('auth')->group(function () {
        Route::get('checkout', 'checkout')->name('checkout');
        Route::post('/', 'store')->name('store');
        Route::get('complete', 'complete')->name('complete');

        Route::get('/', 'index')->name('index');
        Route::delete('{order}', 'cancel')->name('cancel');
    });

    /*
     * 주문조회. **과거 비회원 주문을 위해 남겨둔다** — 회원 전용으로 바꾸기 전에
     * 접수된 주문들은 조회할 다른 방법이 없다. 새 비회원 주문은 더 이상 생기지 않으므로
     * 시간이 지나면 이 화면도 정리 대상이다.
     */
    Route::get('lookup', 'lookupForm')->name('lookup');
    Route::post('lookup', 'lookup')->name('lookup.submit');
});

/*
| 내 정보. 저장은 Fortify 의 PUT /user/profile-information 로 직접 간다 —
| GET 은 화면만 그린다(FortifyServiceProvider 에는 이 화면을 등록할 자리가 없다,
| Fortify 는 로그인 계열 뷰만 등록해준다).
*/
Route::middleware('auth')->get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

/*
| 배송지록. 회원 전용. 주문서 자동 채움과 여기서 같은 데이터를 쓴다
| (AddressLibrary — CLAUDE.md §4.2, 고객·주문서가 같은 라이브러리 공유).
*/
Route::middleware('auth')->controller(AddressController::class)
    ->prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{address}', 'update')->name('update');
        Route::delete('{address}', 'destroy')->name('destroy');
        Route::put('{address}/default', 'setDefault')->name('default');
    });

/*
| 상품 후기 · 문의.
|
| 후기는 **구매 이력으로 자격을 확인**하므로 회원 전용이다.
| 문의도 답변을 전달할 창구가 필요해 회원 전용이다 —
| 비회원은 상담 채널(config('shop.support.channel'))로 유도한다.
*/
Route::middleware('auth')->group(function () {
    Route::controller(ProductReviewController::class)->prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{review}', 'destroy')->name('destroy');
    });

    Route::controller(ProductQuestionController::class)->group(function () {
        Route::post('products/{product}/questions', 'store')->name('questions.store');
        Route::delete('questions/{question}', 'destroy')->name('questions.destroy');
    });
});

/*
| 반품·교환. 처리 결과를 전달할 창구가 필요해 회원 전용이다.
| 비회원 건은 관리자가 대행 접수한다 (Admin\ReturnController::store).
*/
Route::middleware('auth')->controller(ReturnController::class)
    ->prefix('returns')->name('returns.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('orders/{order}/create', 'create')->name('create');
        Route::post('orders/{order}', 'store')->name('store');
        Route::delete('{return}', 'cancel')->name('cancel');
    });

/*
| 1:1 문의. 답변을 전달할 곳이 있어야 하므로 회원 전용이다.
*/
Route::middleware('auth')->controller(InquiryController::class)
    ->prefix('inquiries')->name('inquiries.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
    });

/*
| 쿠폰함. 비회원은 쿠폰을 가질 수 없으므로 로그인이 필요하다 (schema-draft.md §8.3).
*/
Route::middleware('auth')->controller(CouponController::class)
    ->prefix('coupons')->name('coupons.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('redeem', 'redeem')->name('redeem');
    });
