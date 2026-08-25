<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libraries\Admin\DashboardLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 대시보드.
 *
 * 사이드바 메뉴는 HandleInertiaRequests 가 공유 데이터로 내려준다.
 *
 * **권한 검사는 라이브러리가 한다.** 대시보드는 여러 메뉴의 숫자를 한데 모으므로
 * 라우트 미들웨어 하나로는 못 거른다 — 카드마다 menu_code 를 보고 걸러 내린다.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardLibrary $dashboard) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'data' => $this->dashboard->forAdmin($request->user('admin')),
        ]);
    }
}
