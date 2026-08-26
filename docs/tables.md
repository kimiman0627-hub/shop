# 테이블 레퍼런스

> **실제 DB 스키마에서 뽑아 정리했다.** 설계 의도와 "왜 그렇게 했는가" 는
> [`schema-draft.md`](schema-draft.md) 에 있다. 이 문서는 **지금 무엇이 있는가** 만 다룬다.
>
> 스키마를 바꾸면 이 문서도 같이 고친다. 안 고치면 금방 거짓말이 된다.

**앱 테이블 32개** (Laravel 기본 테이블 8개 — `migrations` `sessions` `cache` `cache_locks` `jobs` `job_batches` `failed_jobs` `password_reset_tokens` — 는 제외)

---

## 읽는 법

모든 테이블에 `id`(bigint, auto increment)와 `created_at` / `updated_at`(timestamp, nullable)이 있다. 표에서는 생략했다.

| 기호 | 뜻 |
|---|---|
| ★ | **스냅샷 컬럼.** 원본이 바뀌어도 이 값은 변하지 않는다. FK 로 조인해서 화면에 찍으면 안 된다 |
| ⚙ | **시스템이 관리한다.** 폼·요청에서 받지 않는다. 모델 `Fillable` 에서 일부러 뺐거나 라이브러리만 건드린다 |

### 전 테이블 공통 규칙

- **금액은 원 단위 정수**(`bigint`). `float`/`decimal` 을 쓰지 않는다 — KRW 는 소수점이 없고 반올림 오차를 원천 차단한다.
- **시각은 UTC 로 저장**한다. 화면 표시와 통계의 날짜 경계는 `config('shop.timezone')`(Asia/Seoul)로 바꿔 쓴다.
- **상태값은 대문자 문자열**이고 PHP enum 으로 캐스팅한다. DB `enum` 타입은 쓰지 않는다 (값 추가가 번거롭다).
- **소프트 삭제(`deleted_at`)를 쓰지 않는다.** 대신 이력이 있는 행은 삭제를 막고 상태로 내린다.

---

## 1. 계정

관리자와 고객은 **테이블부터 가드까지 완전히 분리**되어 있다. `users.is_admin` 같은 플래그를 쓰지 않는다.

### `users` — 고객

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `name` | varchar(255) | 이름 |
| `email` | varchar(255) | 로그인 식별자. **unique** |
| `email_verified_at` | timestamp? | 이메일 인증 완료 시각. null 이면 미인증 |
| `password` | varchar(255) | 모델 `casts` 의 `hashed` 가 처리. 평문 저장·로깅 금지 |
| `remember_token` | varchar(100)? | 자동 로그인 |
| `phone` | varchar(20)? | 연락처. 가입·"내 정보"에서 선택 입력. 주문서 주문자 연락처를 자동으로 채운다 |
| `marketing_email_agreed_at` | timestamp? | ⚙ 이메일 수신동의 **시각**. null = 미동의. 동의 여부가 아니라 시각을 저장한다 — 정보통신망법상 "언제 동의했는지" 증빙이 필요하다 |
| `marketing_sms_agreed_at` | timestamp? | ⚙ SMS 수신동의 시각. 위와 같은 이유 |
| `last_login_at` | timestamp? | ⚙ 마지막 로그인 시각. `RecordUserLogin` 리스너가 `web` 가드 로그인 때만 찍는다 |

Fortify 가 쓰는 기본 구조에 회원 프로필을 확장했다. 이메일을 바꾸면 `email_verified_at` 이 null 로 돌아간다(새 주소는 확인된 적이 없다).

**`marketing_*_agreed_at` / `last_login_at` 은 `Fillable` 에서 뺐다.** 폼 mass assignment 로 못 건드리고,
`UpdateUserProfileInformation`(동의)·`RecordUserLogin`(로그인)만 `forceFill` 로 쓴다 — `Admin.last_login_at` 과 같은 규칙이다.

### `admins` — 관리자

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `login_id` | varchar(50) | 로그인 ID. **unique**. 관리자는 이메일이 아니라 이걸로 로그인한다 |
| `name` | varchar(50) | 이름 |
| `email` | varchar(255)? | 연락처 용도. 로그인에 쓰지 않는다 |
| `password` | varchar(255) | `hashed` 캐스팅 |
| `admin_role_id` | bigint → `admin_roles.id` (restrict) | 관리자 1명 = 역할 1개 |
| `status` | varchar(20) | `AdminStatus`: ACTIVE / SUSPENDED |
| `last_login_at` | timestamp? | ⚙ 로그인 시 갱신 |
| `remember_token` | varchar(100)? | |

- **인덱스:** unique(`login_id`), index(`status`)
- 관리자는 **물리 삭제하지 않는다.** 주문·상품 이력이 관리자를 참조하므로 `status = SUSPENDED` 로 내린다.
- 역할 삭제는 `restrict` — 소속 관리자가 있으면 DB 가 막는다.

### `admin_roles` — 역할

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `code` | varchar(50) | **unique**. 대문자(`SUPER_ADMIN` `MANAGER` …). 한번 정하면 바꾸지 않는다 |
| `name` | varchar(50) | 화면에 보이는 이름 |
| `description` | varchar(255)? | 설명 |

`SUPER_ADMIN` 은 권한 검사를 **전부 통과**하는 하드코딩 예외다. 권한 레코드에 의존하지 않는다.

### `admin_role_permissions` — 역할 × 메뉴 권한

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `admin_role_id` | bigint → `admin_roles.id` (cascade) | |
| `menu_code` | varchar(50) | `config/admin/menu.php` 의 메뉴 코드 |
| `can_read` | bool | 페이지 조회 |
| `can_write` | bool | 생성·수정·삭제. **조회를 전제로 한다** (쓰기만 켜는 조합은 막는다) |

- **인덱스:** unique(`admin_role_id`, `menu_code`)
- **메뉴 목록은 DB 가 아니라 config 가 원천이다.** 여기는 "어떤 역할이 어떤 코드에 접근 가능한가" 만 저장한다.
- **레코드가 없으면 차단**이 기본값이다(allow-list). 그래서 **메뉴를 새로 추가하면 기존 역할에는 권한이 없다** — 시드를 다시 돌리거나 권한설정 화면에서 켜야 한다.

---

## 2. 상품

### `categories` — 카테고리 (최대 3단계)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `parent_id` | bigint? → `categories.id` (set null) | 최상위는 null |
| `name` | varchar(50) | |
| `slug` | varchar(80) | **unique**. 한글 이름은 `Str::slug` 가 다 버리므로 한글 보존 폴백을 쓴다 |
| `depth` | smallint | ⚙ 부모에서 파생되지만 비정규화. 트리 조회를 매번 재귀하지 않으려는 것 |
| `sort_order` | int | 진열 순서 |
| `is_active` | bool | |

- **인덱스:** unique(`slug`), index(`parent_id`,`sort_order`), index(`is_active`,`sort_order`)
- DB 는 `set null` 이지만 **앱에서 "하위 있으면 삭제 불가" 를 막는다.** 자식이 최상위로 조용히 올라가는 건 사고다.
- 부모를 옮기면 자손 `depth` 를 통째로 밀어준다.

### `products` — 상품

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `category_id` | bigint → `categories.id` (restrict) | |
| `name` | varchar(200) | |
| `slug` | varchar(220) | **unique**. URL 에 쓴다 (한글 유지) |
| `summary` | varchar(255)? | 목록·상세 상단 한 줄 |
| `description` | text? | 상세 설명 본문 |
| `base_price` | bigint | 정가 |
| `sale_price` | bigint? | 할인가. null 이면 정가로 판다 |
| `status` | varchar(20) | `ProductStatus`: DRAFT / ON_SALE / SOLD_OUT / HIDDEN |
| `shipping_fee_type` | varchar(20) | `ShippingFeeType`: FREE / PAID |
| `shipping_policy_id` | bigint? → `shipping_policies.id` (restrict) | 무료배송 상품은 null 이 된다 |
| `thumbnail_path` | varchar(255)? | ⚙ 대표 이미지 경로. 목록에서 이미지 테이블을 조인하지 않으려는 비정규화 |
| `sort_order` | int | 진열 순서 |
| `review_count` | int | ⚙ **노출 중인** 후기 수 |
| `rating_sum` | int | ⚙ **노출 중인** 후기의 별점 합. 평균 = `rating_sum / review_count` |

- **인덱스:** unique(`slug`), index(`status`), index(`status`,`sort_order`), index(`category_id`,`status`)
- **재고 컬럼이 없다.** 재고는 `product_variants` 한 곳에만 둔다 — 양쪽에 두면 반드시 어긋난다.
- **`review_count` / `rating_sum` 은 `Fillable` 에서 뺐다.** 후기 등록·숨김만 건드려야 하고, 평균이 아니라 **합계**를 저장해야 후기 하나가 빠질 때 정확히 되돌릴 수 있다.
- 주문 이력이 있으면 삭제가 막힌다. 대신 `status = HIDDEN`.

### `product_options` — 옵션 축 (색상, 사이즈 …)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_id` | bigint → `products.id` (cascade) | |
| `name` | varchar(30) | 축 이름. '색상', '사이즈' |
| `sort_order` | int | **선택 단계 순서.** 1단계를 골라야 2단계 후보가 나온다 |

### `product_option_values` — 옵션 값 (빨강, M …)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_option_id` | bigint → `product_options.id` (cascade) | |
| `value` | varchar(50) | '빨강', 'M' |
| `sort_order` | int | |

### `product_variants` — 조합 (실제 판매 단위) ★중요

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_id` | bigint → `products.id` (cascade) | |
| `sku` | varchar(50) | **unique**. 재고·물류 식별자. `P{상품id}-{랜덤4}` 형태. **한번 만들면 바뀌지 않는다** |
| `additional_price` | int | 조합 추가금. 판매가 = 상품 표시가 + 이 값 |
| `stock_quantity` | int | **실물 재고** |
| `reserved_quantity` | int | ⚙ **결제 진행 중 예약분.** 주문 생성 시 늘고, 결제·취소·만료 시 준다 |
| `is_active` | bool | 조합 단위 판매 중지 |

- **인덱스:** unique(`sku`), index(`product_id`,`is_active`)
- **판매가능 = `stock_quantity` − `reserved_quantity`.** 이 값을 컬럼으로 저장하지 않는다.
- **`reserved_quantity` 는 폼에서 절대 받지 않는다.** 시스템(주문/결제)이 관리한다. 대신 실물을 예약분보다 적게 줄이는 것은 막는다.
- 옵션이 없는 상품도 조합이 1개 생긴다. 주문은 항상 조합 단위다.

### `product_variant_values` — 조합 ↔ 옵션값 (피벗)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_variant_id` | bigint → `product_variants.id` (cascade) | |
| `product_option_value_id` | bigint → `product_option_values.id` (cascade) | |

- **인덱스:** unique(두 컬럼), index(`product_option_value_id`)
- 타임스탬프가 없는 순수 피벗이다.
- ⚠ 옵션 값을 지우면 이 행이 cascade 로 끊긴다. **조합 이름은 옵션을 손대기 전에 계산해둬야 한다** — 안 그러면 `빨강 / S` 가 `S` 로 나온다.

### `product_images` — 상품 이미지

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_id` | bigint → `products.id` (cascade) | |
| `path` | varchar(255) | **상대 경로만** 저장. URL 은 `Storage::disk()->url()` 로 만든다 (S3 전환 대비) |
| `alt` | varchar(100)? | 대체 텍스트 |
| `sort_order` | int | 노출 순서 |
| `is_primary` | bool | 대표 이미지. **`GALLERY` 에만 의미가 있다** |
| `type` | varchar(20) | `ProductImageType`: GALLERY(상단 갤러리) / DETAIL(상세 설명 이미지) |

- **인덱스:** index(`product_id`,`sort_order`), index(`product_id`,`type`,`sort_order`)
- **업로드·정렬에 용도를 반드시 함께 넘긴다.** 안 그러면 상세 이미지가 대표로 지정되어 목록 썸네일이 바뀐다.
- 장수 제한은 용도별로 센다 (갤러리 10 / 상세 20, `config/shop.php`).
- DB 행을 지워도 **파일은 앱이 지운다.** 상품 삭제 시 디렉터리째 정리한다.

---

## 3. 상품 후기 · 문의

### `product_reviews` — 상품 후기

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_id` | bigint → `products.id` (restrict) | |
| `user_id` | bigint → `users.id` (cascade) | 작성자 |
| `order_item_id` | bigint → `order_items.id` (restrict) | **unique.** 구매자만 쓸 수 있게 하는 근거이자 주문 항목당 1건 제한 |
| `rating` | smallint | 1~5 |
| `content` | varchar(2000) | 본문 |
| `status` | varchar(20) | `ReviewStatus`: PUBLISHED / HIDDEN |
| `admin_reply` | varchar(1000)? | 판매자 답글 |
| `replied_by_admin_id` | bigint? → `admins.id` (set null) | |
| `replied_at` | timestamp? | |

- **인덱스:** unique(`order_item_id`), index(`product_id`,`status`,`id`), index(`user_id`)
- **후기는 삭제하지 않고 숨긴다.** 고객이 '삭제' 를 눌러도 `HIDDEN` 이다. 평점 이력이 구매 이력과 엮여 있다.
- 그 주문이 `DELIVERED` 여야 쓸 수 있다. 물건을 받아봐야 후기다.
- **숨기면 `products.rating_sum` / `review_count` 에서도 빠진다.** 이 가감은 `ProductReviewLibrary` 만 한다.
- 고객 화면에는 작성자 이름을 가려 내린다(`김서연` → `김*연`). 관리자 화면은 실명.

### `product_questions` — 상품 문의 (Q&A)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_id` | bigint → `products.id` (restrict) | |
| `user_id` | bigint → `users.id` (cascade) | |
| `content` | varchar(1000) | 질문 |
| `is_secret` | bool | 비밀글. **작성자와 관리자만 내용을 본다** |
| `status` | varchar(20) | `QuestionStatus`: PENDING / ANSWERED |
| `answer` | varchar(1000)? | 답변 |
| `answered_by_admin_id` | bigint? → `admins.id` (set null) | |
| `answered_at` | timestamp? | |

- **인덱스:** index(`product_id`,`id`), index(`status`)
- **1:1 문의(`inquiries`)와 다르다.** 그쪽은 주문에 붙고 비공개, 이쪽은 상품에 붙고 기본 공개다. **구매를 요구하지 않는다** — 사기 전에 묻는 게 문의다.
- **비밀글은 서버가 `content`·`answer` 를 null 로 지워 내린다.** 화면에서 가리면 개발자도구로 다 보인다. 행 자체는 남긴다(안 그러면 답변 대기 건수가 안 맞는다).
- 답변이 달리면 작성자도 못 지운다 — 답변이 고아가 된다.

---

## 4. 장바구니

### `carts`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `user_id` | bigint? → `users.id` (cascade) | 회원 장바구니 |
| `session_token` | varchar(64)? | 비회원 장바구니 |

- **인덱스:** unique(`user_id`), unique(`session_token`) — 각각 하나씩만
- **가격을 저장하지 않는다.** 장바구니는 항상 현재 가격을 보여준다. 스냅샷은 주문 시점에만 뜬다.
- 로그인하면 비회원 장바구니를 회원 쪽으로 병합하고 비회원 행은 지운다.

### `cart_items`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `cart_id` | bigint → `carts.id` (cascade) | |
| `product_variant_id` | bigint → `product_variants.id` (cascade) | 조합 단위로 담는다 |
| `quantity` | int | |

- **인덱스:** unique(`cart_id`,`product_variant_id`) — 같은 조합은 두 줄로 담지 않고 수량을 합친다

---

## 5. 주문 ★ 스냅샷이 핵심

### `orders`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `order_no` | varchar(30) | **unique.** 고객에게 보여주는 주문번호 (`ORD…`) |
| `user_id` | bigint? → `users.id` (set null) | **null 이면 비회원 주문** |
| `guest_password` | varchar(255)? | 비회원 주문조회 비밀번호. `hashed` 캐스팅 — 평문 저장 금지 |
| `status` | varchar(20) | `OrderStatus`: PENDING / PAID / PREPARING / SHIPPING / DELIVERED / CANCELED / REFUNDED |
| `items_total` | bigint | 상품 합계 |
| `discount_total` | bigint | 쿠폰 할인 |
| `shipping_fee` | bigint | ★ 배송비. **주문 시점에 확정 저장** — 정책이 바뀌어도 과거 주문 금액은 불변 |
| `total_amount` | bigint | 실제 결제액 = 상품 − 할인 + 배송비 |
| `user_coupon_id` | bigint? → `user_coupons.id` (set null) | 사용한 쿠폰 |
| `orderer_name` / `orderer_phone` / `orderer_email` | varchar | ★ 주문자 |
| `receiver_name` / `receiver_phone` | varchar | ★ 수령인 |
| `postcode` / `address1` / `address2` | varchar | ★ 배송지 |
| `delivery_memo` | varchar(255)? | ★ 배송 요청사항 |
| `ordered_at` | timestamp | 주문 시각 |
| `paid_at` | timestamp? | 결제 완료 시각. **매출 통계의 기준** |
| `canceled_at` | timestamp? | 취소·환불 시각 |
| `stock_released_at` | timestamp? | ⚙ 예약 해제 시각. **해제 멱등성의 근거** — 이게 있으면 다시 안 푼다 |
| `payment_due_at` | timestamp? | ⚙ 결제 기한. 수단마다 다르므로 주문 시점에 확정 저장. 스케줄러가 이 시각을 본다 |

- **인덱스:** unique(`order_no`), index(`status`), index(`ordered_at`), index(`payment_due_at`), index(`status`,`ordered_at`), index(`user_id`), index(`user_id`,`ordered_at`)
- **수령인·주소·연락처는 전부 스냅샷이다.** 회원 정보에서 조인하지 않는다 — 회원이 주소를 바꿔도 과거 주문서는 그대로여야 한다.
- ⚠ `payment_due_at` 은 한때 모델 `Fillable` 에 없어서 **조용히 누락**됐고, 그 주문들이 영원히 만료되지 않았다. 컬럼을 더하면 `Fillable` 과 `casts` 를 같이 본다.

### `order_items`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `order_id` | bigint → `orders.id` (cascade) | |
| `product_id` | bigint? → `products.id` (set null) | 링크용. **화면에 찍는 값의 출처가 아니다** |
| `product_variant_id` | bigint? → `product_variants.id` (set null) | 링크용 |
| `product_name` | varchar(200) | ★ 주문 당시 상품명 |
| `variant_name` | varchar(100)? | ★ 주문 당시 조합명 (`빨강 / M`) |
| `sku` | varchar(50)? | ★ 주문 당시 SKU |
| `unit_price` | bigint | ★ 주문 당시 단가 |
| `quantity` | int | |
| `subtotal` | bigint | ★ `unit_price × quantity` |
| `shipping_fee_type` | varchar(20) | ★ 주문 당시 배송비 유형 |

- **인덱스:** index(`order_id`), index(`product_variant_id`)
- **스냅샷이 원본이다.** 상품이 수정·삭제돼도 주문서는 변하지 않는다. FK 는 "지금도 있으면 링크를 걸어주는" 용도일 뿐이다.

---

## 6. 결제

### `payments`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `order_id` | bigint → `orders.id` (restrict) | 주문당 여러 건이 될 수 있다(재시도) |
| `method` | varchar(20) | `PaymentMethod`: BANK_TRANSFER / CARD / VIRTUAL_ACCOUNT |
| `status` | varchar(20) | `PaymentStatus`: READY / PAID / FAILED / CANCELED / REFUNDED |
| `amount` | bigint | 결제 요청 금액 |
| `bank_name` / `account_number` / `holder_name` | varchar? | ★ 무통장 계좌 **스냅샷**. 계좌를 바꿔도 기존 결제 안내는 그대로 |
| `depositor_name` | varchar(50)? | 입금자명. 주문자와 달라도 된다 |
| `pg_provider` / `pg_transaction_id` | varchar? | PG 연동용. **현재 미사용** |
| `raw_response` | json? | PG 원본 응답 보관용. **현재 미사용** |
| `confirmed_by_admin_id` | bigint? → `admins.id` (set null) | 무통장 입금을 확인한 관리자 |
| `memo` | varchar(255)? | 처리 메모 |
| `requested_at` | timestamp | 결제 요청 시각 |
| `paid_at` / `canceled_at` | timestamp? | |

- **인덱스:** index(`order_id`), index(`status`,`method`), index(`pg_transaction_id`)
- 지금은 **무통장입금만 동작한다.** PG 컬럼은 자리만 잡아뒀다.

### `bank_accounts` — 입금 계좌 설정

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `bank_name` | varchar(30) | 은행명 |
| `account_number` | varchar(40) | 계좌번호 |
| `holder_name` | varchar(50) | 예금주 |
| `is_default` | bool | 기본 계좌. **활성 기본 계좌는 항상 정확히 1개** (앱에서 강제) |
| `is_active` | bool | |
| `sort_order` | int | |

- **인덱스:** index(`is_active`,`is_default`)
- 계좌가 하나도 없으면 무통장 주문을 받을 수 없다. 주문서에서 미리 알린다.
- 기본 계좌 삭제·비활성화·기본 해제는 막혀 있다. 옮기려면 다른 계좌를 기본으로 지정한다.
- ⚠ 시드 계좌는 **가짜다** (`국민은행 123456-01-123456`). 운영 전 교체.

---

## 7. 배송

### `shipping_policies` — 배송비 정책

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `name` | varchar(50) | 정책 이름 |
| `base_fee` | bigint | 기본 배송비. `0` 이면 항상 무료 |
| `free_threshold` | bigint? | 이 금액 이상이면 무료. null 이면 무료 기준 없음 |
| `is_default` | bool | 기본 정책. **활성 기본 정책은 항상 정확히 1개** |
| `is_active` | bool | |

- **인덱스:** index(`is_active`,`is_default`)
- **무료배송 기준은 "유료배송 상품 합계" 기준이다.** 주문 총액이 아니다 — 무료배송 상품이 임계금액을 채워주면 정책 의도와 어긋난다.
- 정책이 섞이면 `base_fee` 가 가장 비싼 정책을 적용한다. 주문당 배송비는 1건이다.
- 기본 정책이 0개가 되면 배송비 계산이 예외를 던진다. **의도된 동작이다** — 조용히 0원 처리하면 틀린 금액이 쌓인다.

### `shipments` — 배송

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `order_id` | bigint → `orders.id` (cascade) | **unique.** 주문당 1건(부분배송 없음) |
| `carrier` | varchar(30)? | 택배사 코드 (`config/shop.php` 의 `shipping.carriers`) |
| `tracking_no` | varchar(50)? | 송장번호 |
| `status` | varchar(20) | `ShipmentStatus`: READY / SHIPPING / DELIVERED |
| `shipped_at` / `delivered_at` | timestamp? | 출고·배송완료 시각 |
| `shipped_by_admin_id` | bigint? → `admins.id` (set null) | 출고 처리자 |
| `memo` | varchar(255)? | |

- **인덱스:** unique(`order_id`), index(`status`), index(`tracking_no`)
- **최초 배송만 담는다.** 반품 회수·교환 재발송 송장은 `order_returns` 에 따로 있다 — 여기에 두 번째 행을 허용하면 `Order::shipment()` 부터 의미가 흔들린다.
- `delivered_at` 은 **반품 신청 기한의 기산점**이다.

---

## 8. 재고 이력

### `stock_movements`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `product_variant_id` | bigint → `product_variants.id` (restrict) | |
| `type` | varchar(20) | `StockMovementType`: RESERVE / RELEASE / SELL / RESTOCK / MANUAL_IN / MANUAL_OUT / ADJUST |
| `stock_delta` | int | **실물** 증감 |
| `reserved_delta` | int | **예약** 증감 |
| `stock_after` | int | 변동 후 실물 |
| `reserved_after` | int | 변동 후 예약 |
| `order_id` | bigint? → `orders.id` (set null) | 주문에서 비롯된 변동이면 연결 |
| `admin_id` | bigint? → `admins.id` (set null) | 수동 조정이면 처리자 |
| `memo` | varchar(255)? | |

- **인덱스:** index(`product_variant_id`,`id`), index(`order_id`)
- `updated_at` 이 없다. **이력은 고치지 않는다.**
- **실물과 예약 두 축을 모두 기록한다.** 한 축만 남기면 "재고가 왜 이 숫자인가" 의 절반만 설명된다.
- `MANUAL_IN` / `ADJUST` 타입은 정의돼 있지만 **아직 그걸 만드는 관리자 화면이 없다.**

---

## 9. 쿠폰

### `coupons` — 쿠폰 마스터

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `code` | varchar(30)? | **unique.** 코드 입력형만 값이 있다 |
| `name` | varchar(50) | |
| `issue_type` | varchar(20) | `CouponIssueType`: SIGNUP(가입 자동) / MANUAL(관리자 지급) / CODE(코드 입력) |
| `discount_type` | varchar(20) | `CouponDiscountType`: FIXED(정액) / PERCENT(정률) |
| `discount_value` | bigint | 정액이면 원, 정률이면 % |
| `max_discount_amount` | bigint? | **정률의 상한.** 없으면 고가 상품에서 손실이 커진다 |
| `min_order_amount` | bigint | 최소 주문금액 |
| `valid_days` | int? | **발급일 기준** 유효일수 |
| `valid_from` / `valid_until` | timestamp? | **절대 기간** |
| `total_issue_limit` | int? | 총 발급 한도 |
| `per_user_limit` | int | 1인당 발급 한도 |
| `is_active` | bool | |

- **인덱스:** unique(`code`), index(`is_active`,`issue_type`)
- `valid_days` 와 `valid_until` 은 다른 축이다. 둘 다 있으면 **더 이른 쪽**이 만료일이 된다.
- **쿠폰은 삭제하지 않는다.** `is_active = false` 로 내린다 — 사용 이력이 매출과 엮인다.

### `user_coupons` — 발급된 쿠폰

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `coupon_id` | bigint → `coupons.id` (restrict) | |
| `user_id` | bigint → `users.id` (cascade) | |
| `issued_at` | timestamp | 발급 시각 |
| `expires_at` | timestamp | ★ **발급 시점에 확정 저장.** 마스터 설정이 바뀌어도 이미 발급된 쿠폰은 불변 |
| `used_at` | timestamp? | 사용 시각. null 이면 미사용 |
| `order_id` | bigint? → `orders.id` (set null) | 어느 주문에 썼는지 |

- **인덱스:** index(`user_id`,`used_at`), index(`coupon_id`), index(`expires_at`), index(`order_id`)
- 주문 취소 시 `used_at` 을 null 로 되돌려 복원한다. **단 만료된 쿠폰은 되살리지 않는다.**
- ⚠ `markUsed()` 로 사용 처리를 **먼저** 하고 할인액을 계산하면, `used_at` 때문에 할인이 0 이 되는 버그가 있었다. 지금은 `discountFor()` 가 `used_at` 을 보지 않는다.

---

## 10. 반품 · 교환

### `order_returns`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `order_id` | bigint → `orders.id` (restrict) | **부분 반품이라 주문당 여러 건** |
| `type` | varchar(20) | `ReturnType`: RETURN(반품) / EXCHANGE(교환) |
| `reason` | varchar(30) | `ReturnReason`: CHANGE_OF_MIND / SIZE_OR_COLOR / DEFECTIVE / WRONG_DELIVERY / DAMAGED / OTHER |
| `reason_detail` | varchar(500)? | 고객이 쓴 상세 사유 |
| `responsibility` | varchar(20) | `ReturnResponsibility`: CUSTOMER / SELLER. **배송비를 누가 내는가.** 승인 시 관리자가 확정 |
| `status` | varchar(20) | `ReturnStatus`: REQUESTED → APPROVED → PICKING → RECEIVED → COMPLETED / REJECTED |
| `items_refund` | bigint | ★ 반품 항목 상품 금액 |
| `coupon_deduction` | bigint | ★ 쿠폰 할인 안분 차감 |
| `shipping_deduction` | bigint | ★ 반품 배송비 차감(고객 귀책일 때) |
| `shipping_refund` | bigint | ★ 최초 배송비 환불(판매자 귀책 + 전량) |
| `refund_amount` | bigint | ★ 최종 환불액. **계산해서 기록만 한다 — 실제 송금은 사람이 한다** |
| `pickup_carrier` / `pickup_tracking_no` | varchar? | 회수 송장 |
| `exchange_carrier` / `exchange_tracking_no` | varchar? | 교환 재발송 송장 |
| `restock` | bool | 재판매 가능 여부. **불량·파손이면 false** → 재고를 되돌리지 않는다 |
| `reject_reason` | varchar(500)? | 반려 사유 |
| `admin_memo` | varchar(500)? | |
| `handled_by_admin_id` | bigint? → `admins.id` (set null) | |
| `requested_at` | timestamp | 접수 |
| `approved_at` / `received_at` / `completed_at` / `rejected_at` | timestamp? | 단계별 시각 |

- **인덱스:** index(`order_id`), index(`status`,`requested_at`)
- **금액은 승인 시점 스냅샷이다.** 나중에 배송비·쿠폰 정책이 바뀌어도 이미 승인한 환불액은 변하지 않는다.
- **재고와 돈은 `COMPLETED` 한 곳에서만 움직인다.** 물건을 받기(`RECEIVED`) 전에 환불하면 회수하지 못한다.
- 쿠폰은 **전량 반품일 때만** 복원한다. 부분 반품에서 되살리면 남은 주문에 할인이 적용된 채로 다시 쓴다.

### `order_return_items`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `order_return_id` | bigint → `order_returns.id` (cascade) | |
| `order_item_id` | bigint → `order_items.id` (restrict) | 어떤 주문 항목을 |
| `quantity` | int | 몇 개 |
| `exchange_variant_id` | bigint? → `product_variants.id` (set null) | 교환일 때 바꿀 조합. **같은 상품 안에서만** |

- **인덱스:** index(`order_return_id`), index(`order_item_id`)
- 남은 신청 가능 수량 = 주문 수량 − (반려되지 않은 신청들의 수량 합). **반려·고객 취소는 점유를 푼다.**

---

## 11. 배송지록

### `user_addresses` — 회원 배송지

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `user_id` | bigint → `users.id` (cascade) | |
| `label` | varchar(20)? | '집', '회사' 같은 별칭. 없어도 된다 |
| `receiver_name` | varchar(50) | |
| `receiver_phone` | varchar(20) | |
| `postcode` | varchar(10) | |
| `address1` | varchar(255) | |
| `address2` | varchar(255)? | |
| `is_default` | bool | 기본 배송지. **회원당 최대 1개** — DB 제약이 아니라 `AddressLibrary` 가 강제한다(`bank_accounts`와 같은 이유, §6 참고) |

- **인덱스:** index(`user_id`, `is_default`)
- **주문(`orders`)의 배송지와는 별개다.** 여기는 "즐겨찾기" 이고, 원본은 여전히 주문 시점 스냅샷이다 — 배송지를 나중에 고쳐도 이미 낸 주문서는 안 바뀐다.
- 회원당 **0개도 정상**이다(가입 직후). `bank_accounts`·`shipping_policies` 처럼 "기본이 반드시 있어야 한다"는 불변식이 없다 — 없으면 그냥 직접 입력하면 된다.
- 첫 배송지는 자동으로 기본이 된다. 기본을 삭제하면 남은 것 중 최근 걸 기본으로 승계한다.
- 최대 개수는 `config('shop.address.max_per_user')`(기본 10).

## 12. 고객 지원

### `inquiries` — 1:1 문의

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `user_id` | bigint → `users.id` (cascade) | **회원 전용.** 답변을 전달할 곳이 있어야 한다 |
| `order_id` | bigint? → `orders.id` (set null) | 주문 연결(선택). **본인 주문인지 확인한다** |
| `category` | varchar(30) | `InquiryCategory`: ORDER / DELIVERY / PAYMENT / PRODUCT / RETURN_EXCHANGE / ETC |
| `title` | varchar(200) | |
| `content` | text | |
| `status` | varchar(20) | `InquiryStatus`: PENDING / ANSWERED |
| `answer` | text? | |
| `answered_at` | timestamp? | |
| `answered_by_admin_id` | bigint? → `admins.id` (set null) | **고객 화면에는 답변자 이름을 내리지 않는다** |

- **인덱스:** index(`user_id`,`id`), index(`status`,`created_at`)
- **비공개다.** 상품 Q&A(`product_questions`)와 혼동하지 말 것.
- 처리 화면은 관리자 > 회원관리 > 회원 상세의 1:1문의 탭이다. 권한도 `MEMBER_LIST` 를 따른다.

### `member_memos` — 회원 관리 메모

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `user_id` | bigint → `users.id` (cascade) | |
| `admin_id` | bigint? → `admins.id` (set null) | 작성자 |
| `content` | text | |

- **인덱스:** index(`user_id`,`id`)
- **고객에게 보이지 않는다.** 관리자 내부 메모다.

## 13. 간편로그인

### `user_social_accounts` — 카카오·네이버 연동 계정

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `user_id` | bigint → `users.id` (cascade) | |
| `provider` | varchar(20) | `'kakao'` \| `'naver'` |
| `provider_user_id` | varchar(191) | 제공자가 발급하는 회원 고유 ID. 이메일이 아니다 — 이메일은 나중에 바뀔 수 있지만 이 값은 안 바뀐다 |

- **인덱스:** unique(`provider`, `provider_user_id`), index(`user_id`)
- **회원 1명 : 연동 N개.** 카카오·네이버를 둘 다 연동할 수 있다.
- `users` 컬럼은 건드리지 않는다 — 이메일·비밀번호 중심 스키마 그대로 두고 소셜 식별자만 별도 테이블에 붙인다.
- 매칭 순서는 `SocialLoginLibrary` 가 정한다: ① `findLinked()` — 이미 연동된 행이 있으면 그 회원 ② 제공자가 이메일을 줬으면 `linkWithVerifiedEmail()` ③ 안 줬으면 추가입력 화면을 거쳐 `createWithUnverifiedEmail()`.
- **이메일의 출처에 따라 처리가 갈린다. 라이브러리 메서드가 둘로 나뉜 이유다.**
  - *제공자가 검증한 이메일*(네이버): 그 주소의 주인임을 제공자가 보증하므로 **같은 이메일의 기존 회원에 연동만 추가**해도 안전하다. 새로 만들면 `email_verified_at` 을 즉시 채운다.
  - *본인이 직접 입력한 이메일*(카카오): 아무도 검증하지 않았으므로 **기존 회원에 절대 붙이지 않는다** — 남의 이메일을 적어 넣는 것만으로 계정을 가져갈 수 있게 된다. 이미 쓰는 주소면 막고, 새 회원만 만들되 `email_verified_at` 은 null 로 두어 평소의 이메일 인증 절차를 그대로 태운다.
- 카카오는 개인 개발자 앱에서 `account_email` 동의항목이 **"권한 없음"** 이라 이메일이 안 넘어온다(비즈 앱 전환 = 사업자등록 필요). 그래서 추가입력 화면(`Store/Auth/SocialComplete`)이 있다.
- 비밀번호는 본인이 모르는 임의값이라, 나중에 비밀번호 로그인을 쓰려면 비밀번호 재설정을 거쳐야 한다.

## 14. 통계 집계

### `daily_sales_stats` — 일별 매출 집계

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `stat_date` | date | **unique.** 영업 시간대(KST) 달력 날짜. 하루 = 한 행 |
| `order_count` | int | 매출로 잡히는 주문 건수 |
| `items_total` | bigint | 상품 합계 |
| `discount_total` | bigint | 쿠폰 할인 |
| `shipping_fee` | bigint | 배송비 |
| `revenue` | bigint | 결제액 합계. `items_total − discount_total + shipping_fee` 와 같아야 한다 |
| `refunded` | bigint | 환불액. **반품 처리완료일 기준** — 8월 주문을 9월에 환불하면 9월 행에 잡힌다 |

- **인덱스:** unique(`stat_date`)
- **파생 데이터다. 원본이 아니다.** 원본은 `orders`·`order_returns` 이고, 이 테이블은 언제든 다시 만들 수 있다 (`php artisan shop:aggregate-sales --all`). 숫자가 이상하면 원본을 고치고 재집계한다 — 여기를 직접 수정하지 않는다.
- **`stat_date` 는 UTC 가 아니라 KST 날짜다.** UTC 자정으로 끊으면 한국 기준 오전 9시에 날짜가 바뀐다.
- 갱신은 스케줄러가 한다: 5분마다 최근 3일, 새벽 4시에 최근 90일. 날짜별 `updateOrCreate` 라 몇 번을 돌려도 값이 누적되지 않는다.
- **매출이 없는 날은 행이 없다.** 일별 추이 화면이 빠진 날을 0 으로 채운다.
- 이게 안 돌면 주문은 정상인데 **통계 화면만 과거에 멈춘다.** 그래서 대시보드·매출통계에 "집계 기준" 시각을 같이 띄운다.
- 상품별·카테고리별 통계는 아직 이 테이블을 쓰지 않는다(실시간 집계). 상위 N개 형태라 상품×날짜 테이블이 따로 필요하다.

### `daily_product_stats` — 상품별 일자별 집계 (조회 → 구매)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `stat_date` | date | KST 달력 날짜 |
| `product_id` | bigint → `products.id` (cascade) | |
| `view_count` | int | ⚙ 상품 상세를 연 횟수. **이벤트 시점에 +1** |
| `cart_count` | int | ⚙ 장바구니에 담은 **횟수**(수량이 아니다). 이벤트 시점에 +1 |
| `order_count` | int | ⚙ 이 상품이 포함된 주문 건수. `order_items` 에서 재계산 |
| `quantity` | int | ⚙ 판매 수량. 재계산 |
| `revenue` | bigint | ⚙ 판매 금액(할인 전 상품 합계). 재계산 |

- **인덱스:** unique(`stat_date`, `product_id`), index(`product_id`, `stat_date`)
- **★ 컬럼이 두 종류다. 섞어서 쓰면 데이터가 날아간다.**
  - `view_count` / `cart_count` 는 **되살릴 수 없다.** 화면 조회와 장바구니 담기는 다른 테이블에 흔적을 남기지 않아서, 그 순간에 세지 않으면 영영 알 수 없다.
  - `order_count` / `quantity` / `revenue` 는 `order_items` 에서 **다시 만들 수 있다**(`shop:aggregate-sales`).
  - 그래서 `ProductStatLibrary` 는 두 그룹을 건드리는 메서드를 분리했다. 재집계가 `updateOrCreate` 로 전체 컬럼을 쓰면 **조회수가 0 이 된다.**
- 쓰는 쪽은 스토어프론트(`ProductController::show`, `CartLibrary::add`), 읽는 쪽은 관리자 상품분석 화면이다.
- **매출은 `daily_sales_stats.revenue` 와 기준이 다르다.** 여기는 할인 전 상품 합계(`order_items.subtotal`)라 쿠폰·배송비가 빠져 있다 — 매출통계의 '상품 합계' 와 일치한다.
- 데모 데이터는 `DemoTrafficSeeder` 가 만든다. 실제 판매량에 맞춰 조회 ≥ 장바구니 ≥ 구매 순서가 되도록 생성한다.

---

## 부록: 스키마 다시 뽑기

문서가 의심스러우면 실제 스키마와 대조한다. `Schema` 파사드를 쓰므로 DB 전용 SQL 이 없다:

```bash
php artisan tinker
```

```php
foreach (Schema::getColumns('orders') as $c) {
    printf("%-24s %-20s %s\n", $c['name'], $c['type'], $c['nullable'] ? 'NULL' : 'NOT NULL');
}

Schema::getIndexes('orders');
Schema::getForeignKeys('orders');
```
