<?php

namespace App\Http\Middleware;

use App\Http\Support\CartOwnerResolver;
use App\Libraries\Admin\AdminMenuLibrary;
use App\Libraries\Admin\AdminPermissionLibrary;
use App\Libraries\Admin\AdminTodoLibrary;
use App\Libraries\Order\CartLibrary;
use App\Libraries\Product\CategoryLibrary;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly AdminMenuLibrary $menus,
        private readonly AdminPermissionLibrary $permissions,
        private readonly AdminTodoLibrary $todos,
        private readonly CategoryLibrary $categories,
        private readonly CartLibrary $carts,
        private readonly CartOwnerResolver $cartOwners,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $admin = $request->user('admin');

        return [
            ...parent::share($request),

            // 고객(web)과 관리자(admin)는 서로 다른 키로 내려간다. 섞지 않는다 (CLAUDE.md §7.1).
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'phone' => $user->phone,
                ],

                'admin' => $admin === null ? null : [
                    'id' => $admin->id,
                    'login_id' => $admin->login_id,
                    'name' => $admin->name,
                    'role' => $admin->role?->name,
                    'is_super_admin' => $admin->isSuperAdmin(),
                ],
            ],

            // 관리자 사이드바. 컨트롤러마다 넘기지 않고 여기서 한 번만 만든다.
            // 클로저라 관리자가 없으면 평가되지 않는다.
            'adminMenu' => $admin === null ? [] : fn () => $this->menus->visibleTree(
                $this->permissions->permissionsForRole($admin->admin_role_id),
                $admin->isSuperAdmin(),
            ),

            /*
             * 관리자 상단 알림. 대시보드의 '처리 대기' 와 **같은 라이브러리**를 쓴다 —
             * 두 곳에서 각자 세면 숫자가 어긋난다 (AdminTodoLibrary).
             *
             * 관리자 화면 어디서나 뜨므로 페이지마다 COUNT 쿼리가 몇 개 돈다.
             * 전부 인덱스 걸린 status 컬럼이라 지금 규모에선 문제없지만,
             * 느려지면 짧은 캐시(예: 30초)를 여기 씌우면 된다.
             */
            'adminTodo' => $admin === null ? [] : fn () => $this->todos->forAdmin($admin),

            // 스토어 헤더의 카테고리 네비. 관리자 화면에서는 평가되지 않는다.
            'storeCategories' => $request->is('admin', 'admin/*')
                ? []
                : fn () => $this->categories->getVisibleTree(),

            // 헤더 장바구니 뱃지. 전체 요약을 만들지 않고 개수만 센다.
            'cartCount' => $request->is('admin', 'admin/*')
                ? 0
                : fn () => $this->carts->itemCount($this->cartOwners->resolve($request)),

            /*
             * 상담 채널(네이버 톡톡·카카오 채널 등). 스토어 전역 플로팅 버튼이 쓴다.
             * url 이 비어 있으면 화면에서 버튼을 아예 안 그린다.
             */
            'supportChannel' => $request->is('admin', 'admin/*')
                ? null
                : config('shop.support.channel'),

            /*
             * 간편로그인 버튼 노출 여부. 개발자센터에 앱을 등록하고 .env 에
             * Client ID 를 넣기 전까지는 버튼 자체를 안 그린다 — 눌러도 실패할
             * 버튼을 화면에 남겨두지 않는다.
             */
            'socialLogin' => $request->is('admin', 'admin/*') ? [] : [
                'kakao' => filled(config('services.kakao.client_id')),
                'naver' => filled(config('services.naver.client_id')),
            ],

            'flash' => [
                'status' => fn () => $this->translateStatus($request->session()->get('status')),
            ],
        ];
    }

    /**
     * `session('status', ...)` 는 이 앱의 다른 컨트롤러들이 전부 완성된 한국어 문장을
     * 넣는 자리다. **Fortify 내장 컨트롤러만 예외다** — `profile-information-updated`
     * 같은 번역 안 된 원본 슬러그를 그대로 넣는다. 여기서 한 번만 바꿔두지 않으면,
     * StoreLayout 의 전역 배너가 그 슬러그를 그대로 찍는다(실제로 그렇게 노출됐다).
     *
     * 새로운 Fortify 슬러그를 마주치면 여기에 한 줄만 추가하면 된다.
     */
    private function translateStatus(?string $status): ?string
    {
        return match ($status) {
            'profile-information-updated' => '저장했습니다.',
            'password-updated' => '비밀번호를 변경했습니다.',
            default => $status,
        };
    }
}
