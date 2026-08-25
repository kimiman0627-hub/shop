<?php

/*
| 쇼핑몰 공통 설정 (CLAUDE.md §6.2).
| 매직넘버를 코드에 박지 않는다. 여기가 커지면 파일을 쪼개고, 더 커지면 폴더로 만든다.
*/

return [

    'per_page' => [
        'product' => 20,
        'order' => 30,
        'admin' => 20,
    ],

    'image' => [
        /*
        | 상품 이미지 저장소.
        |
        | 로컬은 'public' (storage/app/public + public/storage 링크).
        | S3 로 옮기려면 .env 에서 SHOP_IMAGE_DISK=s3 로 바꾸고 AWS_* 를 채운다.
        | DB 에는 상대 경로만 저장하므로 컬럼은 그대로다.
        |
        | S3 를 처음 쓸 때는 패키지가 필요하다:
        |   composer require league/flysystem-aws-s3-v3
        |
        | 디스크 정의는 config/filesystems.php 에 이미 있다.
        */
        'disk' => env('SHOP_IMAGE_DISK', 'public'),

        'max_kb' => 2048,
        'allowed' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_per_product' => 10,

        // 상세 설명 영역에 세로로 이어 붙이는 이미지. 갤러리와 한도를 따로 센다.
        'max_detail_per_product' => 20,
    ],

    /*
    | 영업일 기준 시간대.
    |
    | DB 에는 UTC 로 저장한다(CLAUDE.md §5.3). 하지만 '오늘 매출' 을 UTC 자정으로
    | 끊으면 한국 기준 오전 9시에 날짜가 바뀐다 — 전날 저녁 주문이 오늘로 잡힌다.
    | 통계의 날짜 경계는 **여기 시간대로** 계산한 뒤 UTC 로 바꿔 조회한다.
    */
    'timezone' => env('SHOP_TIMEZONE', 'Asia/Seoul'),

    /*
    | 상담 채널 (네이버 톡톡 · 카카오 채널 등).
    |
    | **URL 만 받는다.** 실시간 상담을 자체 구현하지 않는다 —
    | 상담원이 붙어 있어야 의미가 있고, 그건 시스템이 아니라 운영의 문제다.
    | 네이버 톡톡은 파트너센터에서 채널을 만들면 대화 링크가 나온다.
    |
    | url 이 비어 있으면 화면에 버튼이 아예 안 뜬다.
    */
    'support' => [
        'channel' => [
            'label' => env('SHOP_SUPPORT_LABEL', '톡톡 문의'),
            'url' => env('SHOP_SUPPORT_URL'),
        ],
    ],

    'address' => [
        // 회원 한 명이 저장할 수 있는 배송지 수. 무제한이면 선택 목록이 무너진다.
        'max_per_user' => 10,
    ],

    'order_no_prefix' => 'ORD',

    'shipping' => [
        /*
        | 택배사. 코드는 대문자이며 한번 정하면 바꾸지 않는다 —
        | shipments.carrier 가 이 값을 저장한다 (CLAUDE.md §6.1).
        |
        | ⚠ tracking_url 은 택배사 사정으로 바뀐다. 운영 투입 전 각 URL 을
        |   실제 송장번호로 열어 확인할 것. 비워두면 조회 링크가 안 나온다.
        |   {no} 자리에 송장번호가 들어간다.
        */
        'carriers' => [
            'CJ' => [
                'name' => 'CJ대한통운',
                'tracking_url' => 'https://trace.cjlogistics.com/next/tracking.html?wblNo={no}',
            ],
            'HANJIN' => [
                'name' => '한진택배',
                'tracking_url' => 'https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&wblnumText2={no}',
            ],
            'LOTTE' => [
                'name' => '롯데택배',
                'tracking_url' => 'https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo={no}',
            ],
            'POST' => [
                'name' => '우체국택배',
                'tracking_url' => 'https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1={no}',
            ],
            'LOGEN' => [
                'name' => '로젠택배',
                'tracking_url' => 'https://www.ilogen.com/web/personal/trace/{no}',
            ],
            'DIRECT' => [
                'name' => '직접배송',
                'tracking_url' => null,
            ],
        ],
    ],

    'return' => [
        /*
        | 반품·교환.
        |
        | days: 배송완료(DELIVERED) 시각으로부터 며칠 안에 신청할 수 있는가.
        |       전자상거래법상 단순 변심은 수령일로부터 7일이다. 하자는 더 길지만
        |       (안 날로부터 30일) 그건 시스템이 아니라 관리자 판단 영역이라
        |       기한이 지나도 관리자가 직접 접수할 수 있게 열어둔다.
        |
        | shipping_fee: 고객 귀책일 때 환불액에서 빼는 반품 배송비.
        |       왕복이므로 보통 편도 배송비의 2배다.
        |       판매자 귀책(불량·오배송)이면 차감하지 않는다.
        */
        'days' => env('SHOP_RETURN_DAYS', 7),
        'shipping_fee' => env('SHOP_RETURN_SHIPPING_FEE', 6000),
    ],

    'payment' => [
        /*
        | 결제 기한. **수단마다 다르다.**
        |
        | 주문 생성 시 orders.payment_due_at 에 확정 저장되고,
        | 스케줄러는 그 시각을 보고 예약을 해제한다.
        |
        | 무통장입금은 사람이 은행에 가야 하므로 일 단위다.
        | 카드와 같은 30분을 적용하면 무통장 주문이 전부 취소된다.
        */
        'due' => [
            // 무통장은 1일. 주문 당일 자정까지 입금하지 않으면 자동 취소된다.
            // 기간을 늘리면 재고가 그만큼 오래 잠긴다 — 회전율과의 맞교환이다.
            'BANK_TRANSFER' => ['days' => env('SHOP_BANK_TRANSFER_DUE_DAYS', 1)],
            'CARD' => ['minutes' => env('SHOP_CARD_DUE_MINUTES', 30)],
            'VIRTUAL_ACCOUNT' => ['days' => 1],
        ],

        /*
        | 입금 안내를 어떤 경로로 보낼지.
        |
        | 지금은 'log' 뿐이다 — 실제 발송 없이 storage/logs/laravel.log 에 남는다.
        | 카카오 알림톡·SMS 를 붙이면 여기에 채널을 더한다 (app/Notifications).
        */
        'notify_channels' => ['log'],
    ],

];
