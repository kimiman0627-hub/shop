<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\Payment\DepositController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductQuestionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\Setting\AdminController;
use App\Http\Controllers\Admin\Setting\BankAccountController;
use App\Http\Controllers\Admin\Setting\RoleController;
use App\Http\Controllers\Admin\Setting\ShippingPolicyController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\StatController;
use Illuminate\Support\Facades\Route;

/*
| 관리자 전용 라우트 (CLAUDE.md §7.4).
|
| bootstrap/app.php 에서 prefix('admin') + name('admin.') + middleware('web') 로 등록된다.
| 그룹 안에서 'admin.' 을 다시 붙이지 않는다 — admin.admin. 이 된다.
|
| 라우트가 늘어나면 routes/admin/ 폴더로 쪼개고 여기서 require 한다.
*/

// 인증 전 — 로그인 화면만. auth:admin 을 걸면 무한 리다이렉트가 된다.
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

// 인증 후 — 나머지 전부.
Route::middleware('auth:admin')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // 대시보드와 본인 계정 관리는 메뉴 권한과 무관하다.
    // 권한이 없는 관리자도 자기 비밀번호는 바꿀 수 있어야 한다.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    /*
    | 관리자설정. menu_code 는 config/admin/menu.php 에 정의된 값이어야 한다.
    | 미들웨어 없는 관리자 라우트를 만들지 않는다.
    */
    Route::prefix('settings')->name('settings.')->group(function () {

        // 배송비설정 (SETTING_SHIPPING)
        Route::controller(ShippingPolicyController::class)->prefix('shipping')->name('shipping.')->group(function () {
            Route::middleware('admin.permission:SETTING_SHIPPING,READ')
                ->get('/', 'index')->name('index');

            Route::middleware('admin.permission:SETTING_SHIPPING,WRITE')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::put('{policy}', 'update')->name('update');
                Route::delete('{policy}', 'destroy')->name('destroy');
            });
        });

        // 입금계좌설정 (SETTING_BANK)
        Route::controller(BankAccountController::class)->prefix('bank')->name('bank.')->group(function () {
            Route::middleware('admin.permission:SETTING_BANK,READ')
                ->get('/', 'index')->name('index');

            Route::middleware('admin.permission:SETTING_BANK,WRITE')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::put('{account}', 'update')->name('update');
                Route::delete('{account}', 'destroy')->name('destroy');
            });
        });

        // 권한설정 (SETTING_ROLE)
        Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function () {
            Route::middleware('admin.permission:SETTING_ROLE,READ')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{role}/edit', 'edit')->name('edit');
            });

            Route::middleware('admin.permission:SETTING_ROLE,WRITE')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::put('{role}', 'update')->name('update');
                Route::put('{role}/permissions', 'updatePermissions')->name('permissions');
                Route::delete('{role}', 'destroy')->name('destroy');
            });
        });

        // 관리자관리 (SETTING_ADMIN)
        Route::controller(AdminController::class)->prefix('admins')->name('admins.')->group(function () {
            Route::middleware('admin.permission:SETTING_ADMIN,READ')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::get('{admin}/edit', 'edit')->name('edit');
            });

            Route::middleware('admin.permission:SETTING_ADMIN,WRITE')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::put('{admin}', 'update')->name('update');
                Route::put('{admin}/password', 'resetPassword')->name('password');
                Route::delete('{admin}', 'suspend')->name('suspend');
            });
        });
    });

    // 상품관리 > 카테고리 (PRODUCT_CATEGORY)
    Route::controller(CategoryController::class)->prefix('categories')->name('categories.')->group(function () {
        Route::middleware('admin.permission:PRODUCT_CATEGORY,READ')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{category}/edit', 'edit')->name('edit');
        });

        Route::middleware('admin.permission:PRODUCT_CATEGORY,WRITE')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::put('{category}', 'update')->name('update');
            Route::delete('{category}', 'destroy')->name('destroy');
        });
    });

    // 상품관리 > 상품목록 (PRODUCT_LIST)
    Route::controller(ProductController::class)->prefix('products')->name('products.')->group(function () {
        Route::middleware('admin.permission:PRODUCT_LIST,READ')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::get('{product}/edit', 'edit')->name('edit');
        });

        Route::middleware('admin.permission:PRODUCT_LIST,WRITE')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::put('{product}', 'update')->name('update');
            Route::delete('{product}', 'destroy')->name('destroy');
        });
    });

    // 상품관리 > 상품후기 (PRODUCT_REVIEW)
    Route::controller(ReviewController::class)->prefix('reviews')->name('reviews.')->group(function () {
        Route::middleware('admin.permission:PRODUCT_REVIEW,READ')
            ->get('/', 'index')->name('index');

        Route::middleware('admin.permission:PRODUCT_REVIEW,WRITE')->group(function () {
            Route::put('{review}/status', 'status')->name('status');
            Route::put('{review}/reply', 'reply')->name('reply');
        });
    });

    // 상품관리 > 상품문의 (PRODUCT_QNA)
    Route::controller(ProductQuestionController::class)->prefix('questions')->name('questions.')->group(function () {
        Route::middleware('admin.permission:PRODUCT_QNA,READ')
            ->get('/', 'index')->name('index');

        Route::middleware('admin.permission:PRODUCT_QNA,WRITE')
            ->put('{question}/answer', 'answer')->name('answer');
    });

    // 주문관리 > 주문목록 (ORDER_LIST)
    Route::controller(OrderController::class)->prefix('orders')->name('orders.')->group(function () {
        Route::middleware('admin.permission:ORDER_LIST,READ')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{order}', 'show')->name('show');
        });

        Route::middleware('admin.permission:ORDER_LIST,WRITE')
            ->put('{order}/cancel', 'cancel')->name('cancel');
    });

    // 주문관리 > 배송관리 (ORDER_SHIPMENT)
    Route::controller(ShipmentController::class)
        ->prefix('shipments')->name('shipments.')->group(function () {
            Route::middleware('admin.permission:ORDER_SHIPMENT,READ')
                ->get('/', 'index')->name('index');

            Route::middleware('admin.permission:ORDER_SHIPMENT,WRITE')->group(function () {
                Route::put('{order}/prepare', 'prepare')->name('prepare');
                Route::put('{order}/ship', 'ship')->name('ship');
                Route::put('{order}/deliver', 'deliver')->name('deliver');
                Route::put('{order}/revert', 'revert')->name('revert');
            });
        });

    // 주문관리 > 반품·교환 (ORDER_RETURN)
    Route::controller(ReturnController::class)->prefix('returns')->name('returns.')->group(function () {
        Route::middleware('admin.permission:ORDER_RETURN,READ')
            ->get('/', 'index')->name('index');

        Route::middleware('admin.permission:ORDER_RETURN,WRITE')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::put('{return}/approve', 'approve')->name('approve');
            Route::put('{return}/reject', 'reject')->name('reject');
            Route::put('{return}/pickup', 'pickup')->name('pickup');
            Route::put('{return}/receive', 'receive')->name('receive');
            Route::put('{return}/complete', 'complete')->name('complete');
        });
    });

    // 통계 > 매출통계 (STAT_SALES)
    Route::middleware('admin.permission:STAT_SALES,READ')
        ->get('stats/sales', [StatController::class, 'sales'])->name('stats.sales');

    // 회원관리 (MEMBER_LIST)
    Route::controller(MemberController::class)->prefix('members')->name('members.')->group(function () {
        Route::middleware('admin.permission:MEMBER_LIST,READ')
            ->get('/', 'index')->name('index');

        Route::middleware('admin.permission:MEMBER_LIST,WRITE')->group(function () {
            Route::put('{member}', 'update')->name('update');
            Route::post('{member}/memos', 'storeMemo')->name('memos.store');
            Route::delete('{member}/memos/{memo}', 'destroyMemo')->name('memos.destroy');
            // 문의 답변도 회원관리 권한을 따른다 — 회원 상세 안에서 처리하기 때문이다.
            Route::put('inquiries/{inquiry}/answer', 'answerInquiry')->name('inquiries.answer');
        });
    });

    // 결제관리 > 무통장처리 (PAYMENT_DEPOSIT)
    Route::controller(DepositController::class)
        ->prefix('payments/deposits')->name('payments.deposits.')->group(function () {
            Route::middleware('admin.permission:PAYMENT_DEPOSIT,READ')
                ->get('/', 'index')->name('index');

            Route::middleware('admin.permission:PAYMENT_DEPOSIT,WRITE')->group(function () {
                Route::put('{payment}/confirm', 'confirm')->name('confirm');
                Route::put('{payment}/cancel', 'cancel')->name('cancel');
            });
        });

    // 프로모션 > 쿠폰관리 (COUPON_LIST)
    Route::controller(CouponController::class)->prefix('coupons')->name('coupons.')->group(function () {
        Route::middleware('admin.permission:COUPON_LIST,READ')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::get('{coupon}/edit', 'edit')->name('edit');
        });

        Route::middleware('admin.permission:COUPON_LIST,WRITE')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::put('{coupon}', 'update')->name('update');
            // 삭제가 아니라 '중지' 다. 쿠폰은 지우지 않는다 (schema-draft.md §8.2).
            Route::delete('{coupon}', 'destroy')->name('destroy');
        });
    });

    // 상품 이미지. 상품 권한을 그대로 따른다 — 별도 메뉴가 아니다.
    Route::controller(ProductImageController::class)
        ->prefix('products/{product}/images')
        ->name('products.images.')
        ->middleware('admin.permission:PRODUCT_LIST,WRITE')
        ->group(function () {
            Route::post('/', 'store')->name('store');
            Route::put('order', 'reorder')->name('order');
            Route::delete('{image}', 'destroy')->name('destroy');
        });
});
