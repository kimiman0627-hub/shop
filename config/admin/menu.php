<?php

/*
| 관리자 메뉴·페이지 정의 (CLAUDE.md §7.3).
|
| 권한의 원천은 이 파일이다. DB(admin_role_permissions)는 "어떤 역할이 어떤 menu_code 에
| 접근 가능한가" 만 저장한다. menu_code 는 대문자이며, 한번 정한 코드는 바꾸지 않는다.
|
| 참조: config('admin.menu')
*/

return [

    'PRODUCT' => [
        'name' => '상품관리',
        'children' => [
            'PRODUCT_LIST' => ['name' => '상품목록', 'route' => 'admin.products.index'],
            'PRODUCT_CATEGORY' => ['name' => '카테고리', 'route' => 'admin.categories.index'],
            'PRODUCT_REVIEW' => ['name' => '상품후기', 'route' => 'admin.reviews.index'],
            'PRODUCT_QNA' => ['name' => '상품문의', 'route' => 'admin.questions.index'],
        ],
    ],

    'ORDER' => [
        'name' => '주문관리',
        'children' => [
            'ORDER_LIST' => ['name' => '주문목록', 'route' => 'admin.orders.index'],
            'ORDER_SHIPMENT' => ['name' => '배송관리', 'route' => 'admin.shipments.index'],
            'ORDER_RETURN' => ['name' => '반품·교환', 'route' => 'admin.returns.index'],
        ],
    ],

    'PAYMENT' => [
        'name' => '결제관리',
        'children' => [
            'PAYMENT_DEPOSIT' => ['name' => '무통장처리', 'route' => 'admin.payments.deposits.index'],
        ],
    ],

    'MEMBER' => [
        'name' => '회원관리',
        'children' => [
            'MEMBER_LIST' => ['name' => '회원목록', 'route' => 'admin.members.index'],
        ],
    ],

    'PROMOTION' => [
        'name' => '프로모션',
        'children' => [
            'COUPON_LIST' => ['name' => '쿠폰관리', 'route' => 'admin.coupons.index'],
        ],
    ],

    'STAT' => [
        'name' => '통계',
        'children' => [
            'STAT_SALES' => ['name' => '매출통계', 'route' => 'admin.stats.sales'],
        ],
    ],

    'SETTING' => [
        'name' => '설정',
        'children' => [
            'SETTING_SHIPPING' => ['name' => '배송비설정', 'route' => 'admin.settings.shipping.index'],
            'SETTING_BANK' => ['name' => '입금계좌설정', 'route' => 'admin.settings.bank.index'],
            'SETTING_ROLE' => ['name' => '권한설정', 'route' => 'admin.settings.roles.index'],
            'SETTING_ADMIN' => ['name' => '관리자관리', 'route' => 'admin.settings.admins.index'],
        ],
    ],

];
