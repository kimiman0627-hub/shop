# 도메인 스키마 초안

> **컬럼 목록이 필요하면 [`tables.md`](tables.md) 를 본다** — 실제 스키마에서 뽑은 레퍼런스다.
> 이 문서는 *왜 그렇게 설계했는가* 를 다루고, 앞쪽 섹션(§2~§9)은 **구현 전에** 쓴 초안이라 세부가 어긋나 있을 수 있다.

> **상태: 결정 반영 완료. 구현 착수 가능.**
> 작성 2026-08-20 / 개정 2026-08-20. 규칙은 [CLAUDE.md](../CLAUDE.md) §5(이식성) / §6(상태값)를 따른다.

전제:

- 금액은 전부 **정수(원 단위)**. `float` 금지.
- 상태값은 전부 **대문자 문자열 + PHP enum 캐스팅**. `->enum()` 컬럼 금지.
- 모든 외래키에 인덱스, 삭제 정책은 매번 명시.
- 시각은 UTC 저장.

---

## 0. 확정 사항

| # | 항목 | 결정 |
|---|---|---|
| 1 | 비회원 주문 | ~~허용~~ → **2026-08-25 정책 변경: 회원만 구매 가능.** 장바구니는 비회원도 쓴다 (§4.2) |
| 2 | 재고 차감 | **예약 방식.** 실물/예약 컬럼 분리 (§7) |
| 3 | 옵션 구조 | **조합형 3테이블.** 1단계 선택 시 2단계가 필터되어 노출 (§2.3) |
| 4 | 재고 변동 이력 | **사용** (§7.7) |
| 5 | 소프트 삭제 | **사용하지 않음.** `deleted_at` 컬럼을 두지 않는다 (§1) |
| 6 | 배송비 | **정책 테이블 + 상품별 유/무료 + 무료배송 임계금액** (§6) |
| 7 | 쿠폰 | **1차 스코프 포함.** 가입쿠폰 자동발급 지원 (§8) |

---

## 1. 소프트 삭제를 쓰지 않는 데 따른 규칙 ★

`deleted_at` 을 두지 않기로 했으므로, **삭제가 이력을 깨뜨리지 않도록 두 장치를 둔다.**

1. **주문 이력이 있는 상품은 삭제하지 않는다.** 라이브러리에서 막고, 관리자에게는 `status = 'HIDDEN'` 을 안내한다. 관리자 안전장치와 같은 방식이다 (CLAUDE.md §7.6).
2. **DB 레벨 안전망:** `order_items` 의 상품·variant FK는 `nullOnDelete` 다. 1번을 우회해 지워지더라도 주문서는 스냅샷으로 온전히 남는다.

즉 상품 라이프사이클은 `DRAFT → ON_SALE → HIDDEN` 이고, 실제 `DELETE` 는 **한 번도 팔린 적 없는 상품**에만 허용한다.

---

## 2. 상품

### 2.1 `categories`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `parent_id` | FK→`categories` nullable | `nullOnDelete`. 최상위는 null |
| `name` | string(50) | |
| `slug` | string(80) unique | |
| `depth` | unsignedTinyInteger | 0=대분류 |
| `sort_order` | integer default 0 | |
| `is_active` | boolean default true | 모델 `$casts` 필수 |
| timestamps | | |

- 인덱스: `parent_id`, `(is_active, sort_order)`
- 깊이는 **3단계까지**로 제한한다. DB가 아니라 검증에서 건다.

### 2.2 `products`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `category_id` | FK→`categories` | `restrictOnDelete` |
| `name` | string(200) | |
| `slug` | string(220) unique | |
| `summary` | string(255) nullable | |
| `description` | text nullable | |
| `base_price` | unsignedBigInteger | **원 단위 정수** |
| `sale_price` | unsignedBigInteger nullable | 할인가. null이면 미적용 |
| `status` | string(20) | `ProductStatus` (§2.5) |
| `shipping_fee_type` | string(20) | `FREE` \| `PAID` (§6) |
| `shipping_policy_id` | FK→`shipping_policies` nullable | `restrictOnDelete`. null이면 기본 정책 |
| `thumbnail_path` | string(255) nullable | |
| `sort_order` | integer default 0 | |
| timestamps | | |

- 인덱스: `category_id`, `status`, `(status, sort_order)`
- **재고 컬럼을 두지 않는다.** 재고는 `product_variants` 에만 있다.
- 노출가 = `sale_price ?? base_price`. 라이브러리 한 곳에서만 계산한다.

### 2.3 옵션 — 조합형 (네이버 스마트스토어 방식) ★

**`product_options`** — 옵션 그룹. `sort_order` 가 곧 **선택 단계**다.

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK→`products` | `cascadeOnDelete` |
| `name` | string(30) | '색상' |
| `sort_order` | integer default 0 | **0 = 1단계, 1 = 2단계** |

**`product_option_values`** — 옵션 값

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `product_option_id` | FK→`product_options` | `cascadeOnDelete` |
| `value` | string(50) | '빨강' |
| `sort_order` | integer default 0 | |

**`product_variants`** — 조합 하나 = SKU. **재고는 여기에만 있다.**

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK→`products` | `cascadeOnDelete` |
| `sku` | string(50) unique | |
| `additional_price` | integer default 0 | **부호 있음.** 조합별 추가금 |
| `stock_quantity` | integer default 0 | **실물 재고** (§7) |
| `reserved_quantity` | integer default 0 | **결제 진행중 묶인 수량** (§7) |
| `is_active` | boolean default true | 조합별 판매중지 |
| timestamps | | |

**`product_variant_values`** — variant ↔ 옵션값 피벗

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `product_variant_id` | FK | `cascadeOnDelete` |
| `product_option_value_id` | FK | `cascadeOnDelete` |
| | | unique(variant_id, option_value_id) |

**불변식 — 검증에서 강제한다:**

- **variant는 그 상품의 모든 옵션 그룹에 대해 값을 정확히 하나씩 갖는다.** 옵션이 2개면 variant마다 값이 2개다. 빠지거나 중복되면 조합 해석이 무너진다.
- **같은 조합의 variant가 둘 있을 수 없다.** DB unique로는 표현이 안 되므로 저장 시 검증한다.
- 옵션 단계는 **최대 3단계**로 제한한다.
- **옵션 없는 상품도 variant를 1개 만든다** (옵션값 0개). 주문·장바구니가 항상 variant를 가리키면 분기가 사라진다.

**계층 선택(1단계 → 2단계)은 화면 동작이지 스키마가 아니다.**

상품 상세에 variant 목록을 옵션값 id와 함께 내려주면, 프론트는 이렇게 계산한다:

```
1단계에서 '빨강'(id=3)을 고름
  → 2단계 후보 = variant 중 option_value 에 3을 포함하는 것들의 2단계 값
  → 그중 (stock_quantity - reserved_quantity) > 0 인 것만 선택 가능,
     나머지는 '품절' 로 비활성 표시
```

없는 조합은 애초에 variant가 없으므로 후보에서 빠진다. 서버는 조합 목록만 주고, **최종 검증은 주문 생성 시 variant id 기준으로 다시 한다.**

### 2.4 `product_images`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK→`products` | `cascadeOnDelete` |
| `path` | string(255) | |
| `alt` | string(100) nullable | |
| `sort_order` | integer default 0 | |
| `is_primary` | boolean default false | |

- 1차 스코프에서 variant별 이미지는 두지 않는다.

### 2.5 상태 enum

```
ProductStatus     DRAFT      판매 준비중 (미노출)
                  ON_SALE    판매중
                  SOLD_OUT   품절 (노출, 구매 불가) — 관리자가 명시적으로 건다
                  HIDDEN     숨김 — 소프트 삭제 대용 (§1)

ShippingFeeType   FREE       무료배송 상품
                  PAID       배송비 정책 적용
```

`SOLD_OUT` 은 재고에서 파생되지 않는다. 예약 때문에 일시적으로 0이 된 것과 관리자의 품절 판단은 다른 사건이다.

---

## 3. 장바구니

### 3.1 `carts`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK→`users` nullable | `cascadeOnDelete` |
| `session_token` | string(64) nullable | **비회원용** |
| timestamps | | |

- 인덱스: `user_id` unique, `session_token` unique
- 회원 로그인 시 세션 장바구니를 회원 장바구니로 **병합**한다. 같은 variant는 수량 합산.

### 3.2 `cart_items`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `cart_id` | FK→`carts` | `cascadeOnDelete` |
| `product_variant_id` | FK→`product_variants` | `cascadeOnDelete` |
| `quantity` | unsignedInteger | |
| timestamps | | |
| | | unique(`cart_id`, `product_variant_id`) |

- **가격을 저장하지 않는다.** 장바구니는 항상 현재 가격을 보여준다.
- **장바구니는 재고를 예약하지 않는다.** 예약은 주문 생성 시점부터다 (§7).

---

## 4. 주문 ★ 스냅샷이 핵심

### 4.1 `orders`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `order_no` | string(30) unique | `ORD` + 날짜 + 일련. 앱에서 생성 |
| `user_id` | FK→`users` nullable | `nullOnDelete`. **비회원이면 null** |
| `guest_password` | string(255) nullable | **비회원 주문조회용 해시** (§4.2) |
| `status` | string(20) | `OrderStatus` (§4.3) |
| `items_total` | unsignedBigInteger | 상품 합계 |
| `discount_total` | unsignedBigInteger default 0 | 쿠폰 할인 (§8) |
| `shipping_fee` | unsignedBigInteger default 0 | (§6) |
| `total_amount` | unsignedBigInteger | 최종 결제 금액 |
| `user_coupon_id` | FK→`user_coupons` nullable | `nullOnDelete`. 1주문 1쿠폰 |
| `orderer_name` | string(50) | **스냅샷** |
| `orderer_phone` | string(20) | **스냅샷** |
| `orderer_email` | string(255) nullable | **스냅샷.** 비회원은 필수 |
| `receiver_name` | string(50) | **스냅샷** |
| `receiver_phone` | string(20) | **스냅샷** |
| `postcode` | string(10) | **스냅샷** |
| `address1` | string(255) | **스냅샷** |
| `address2` | string(255) nullable | **스냅샷** |
| `delivery_memo` | string(255) nullable | |
| `ordered_at` | timestamp | |
| `paid_at` | timestamp nullable | |
| `canceled_at` | timestamp nullable | |
| `stock_released_at` | timestamp nullable | **예약 해제 멱등성** (§7.4) |
| timestamps | | |

- 인덱스: `user_id`, `status`, `ordered_at`, `(user_id, ordered_at)`, `order_no`
- **주소·연락처를 회원 정보에서 조인하지 않는다.** 회원이 주소를 바꿔도 과거 주문의 배송지는 그대로여야 한다.
- 금액을 4개로 분해해 저장한다. 합계만 두면 정산·부분환불에서 근거를 잃는다.

### 4.2 비회원 주문 — **2026-08-25 폐지**

**구매는 회원만 할 수 있다.** 결제 경로(`orders.checkout` / `orders.store` /
`orders.complete`)에 `auth` 미들웨어가 걸려 있다.

- **장바구니는 여전히 비회원도 쓴다** (`carts.session_token`). 로그인을 요구하는
  지점은 '담기' 가 아니라 '주문' 이다 — 담아두고 로그인하면 그대로 넘어온다
  (`MergeGuestCartOnLogin`). 주문서는 GET 이라 로그인 후 자동 복귀한다.
- `orders.user_id` / `guest_password` 컬럼은 **남겨뒀다.** 정책 변경 전에 접수된
  비회원 주문을 조회할 통로가 필요해서다. 새 주문은 `user_id` 가 반드시 채워지고
  `guest_password` 는 항상 null 이다 (`OrderLibrary::create()` 가 `int $userId` 를 받는다).
- 주문조회 화면(`/orders/lookup`)도 같은 이유로 남아 있지만 **헤더 링크는 뺐다** —
  새 비회원 주문이 생기지 않으므로 신규 방문자에게는 쓸모가 없다.

아래는 폐지 전 규칙이다. 조회 화면이 아직 이 규칙으로 동작한다:

- 조회는 **주문번호 + 비회원 비밀번호**. `guest_password` 는 해시 저장한다.
- **전화번호만으로 조회시키지 않는다.** 주문번호가 순차적이면 전화번호 조합으로 남의 주문이 열린다.
- 비회원 주문조회에도 **스로틀링**을 건다. 관리자 로그인과 같은 이유다.

### 4.3 `order_items`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | FK→`orders` | `cascadeOnDelete` |
| `product_id` | FK→`products` nullable | `nullOnDelete`. 링크용 |
| `product_variant_id` | FK→`product_variants` nullable | `nullOnDelete`. 링크용 |
| `product_name` | string(200) | **스냅샷** |
| `variant_name` | string(100) nullable | **스냅샷** — '빨강 / L' |
| `sku` | string(50) nullable | **스냅샷** |
| `unit_price` | unsignedBigInteger | **스냅샷** — 주문 시점 판매가 |
| `quantity` | unsignedInteger | |
| `subtotal` | unsignedBigInteger | `unit_price * quantity` |
| `shipping_fee_type` | string(20) | **스냅샷** — 배송비 계산 근거 (§6) |
| timestamps | | |

**화면에 찍는 값은 전부 스냅샷 컬럼에서 읽는다.** FK를 조인해 상품명·가격을 가져오면 상품이 수정되는 순간 과거 주문서가 바뀐다.

### 4.4 상태 enum

```
OrderStatus   PENDING     결제대기 — 이 상태가 재고를 예약한다
              PAID        결제완료
              PREPARING   상품준비중
              SHIPPING    배송중
              DELIVERED   배송완료
              CANCELED    취소
              REFUNDED    환불완료
```

---

## 5. 결제

### 5.1 `payments`

**주문당 여러 행.** 시도·실패·재시도 이력이 남는다.

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | FK→`orders` | `restrictOnDelete` |
| `method` | string(20) | `PaymentMethod` |
| `status` | string(20) | `PaymentStatus` |
| `amount` | unsignedBigInteger | |
| `pg_provider` | string(30) nullable | `TOSS` / `PORTONE` / `MOCK` |
| `pg_transaction_id` | string(100) nullable | |
| `raw_response` | json nullable | 응답 원문 보관 |
| `requested_at` | timestamp | |
| `paid_at` | timestamp nullable | |
| timestamps | | |

```
PaymentMethod   CARD, BANK_TRANSFER, VIRTUAL_ACCOUNT, MOCK
PaymentStatus   READY, PAID, FAILED, CANCELED, REFUNDED, PARTIAL_REFUNDED
```

- `raw_response` 는 분쟁 대응용 보관이다. CLAUDE.md §5.3대로 이 JSON 내부를 검색·정렬하지 않는다.
- 로컬 단계에서는 `MOCK` 만 쓴다.

---

## 6. 배송 ★

### 6.1 `shipping_policies` — 관리자 설정 페이지가 붙는다

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(50) | '기본 배송비' |
| `base_fee` | unsignedBigInteger | 배송비 |
| `free_threshold` | unsignedBigInteger nullable | **이 금액 이상이면 무료.** null이면 조건부 무료 없음 |
| `is_default` | boolean default false | 상품이 정책을 안 고르면 이것 |
| `is_active` | boolean default true | |
| timestamps | | |

- **`is_default` 인 활성 정책은 항상 정확히 1개.** 라이브러리에서 강제한다. 0개면 배송비 계산이 터지고, 2개면 어느 쪽인지 알 수 없다.
- 기본 정책은 삭제할 수 없다. 다른 정책을 기본으로 지정한 뒤에야 지울 수 있다.
- 도서산간·권역별 추가요금은 1차 스코프 밖이다. 필요해지면 `shipping_policy_regions` 를 붙인다.

### 6.2 배송비 계산 규칙

주문당 배송비는 **1건**이다.

```
1. 주문의 order_items 중 shipping_fee_type = 'PAID' 인 것만 모은다
2. 하나도 없으면            → 배송비 0 (전부 무료배송 상품)
3. 적용 정책 = 그 상품들의 정책 중 base_fee 가 가장 큰 것
4. 유료배송 상품 합계 >= 정책.free_threshold  → 배송비 0
5. 아니면                    → 배송비 = 정책.base_fee
```

**4번의 기준은 "유료배송 상품 합계"지 "주문 총액"이 아니다.**
무료배송 상품이 임계금액을 채워주면 유료배송 상품의 배송비가 공짜가 되어 정책 의도와 어긋난다. 총액 기준으로 바꾸고 싶으면 이 한 줄만 고치면 된다 — 계산은 라이브러리 한 곳에 있다.

**배송비는 주문 생성 시 계산해 `orders.shipping_fee` 에 확정 저장한다.** 정책이 나중에 바뀌어도 과거 주문 금액은 불변이다. `order_items.shipping_fee_type` 스냅샷은 그 계산의 근거를 남긴다.

### 6.3 `shipments`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | FK→`orders` | `cascadeOnDelete` |
| `carrier` | string(30) nullable | `CJ`, `HANJIN` 등 대문자 코드 |
| `tracking_no` | string(50) nullable | |
| `status` | string(20) | `READY` \| `SHIPPING` \| `DELIVERED` |
| `shipped_at` | timestamp nullable | |
| `delivered_at` | timestamp nullable | |
| timestamps | | |

- 부분배송은 1차 스코프 밖이라 주문당 1건이다. 그래도 별도 테이블로 두면 나중에 분할해도 `orders` 를 안 건드린다.

---

## 7. 재고 ★ 가장 위험한 구간

**재고는 `product_variants` 한 곳에만 있고, 두 컬럼으로 나뉜다.**

| 컬럼 | 의미 | 언제 변하나 |
|---|---|---|
| `stock_quantity` | 실물 재고 | 입고, **결제 완료**, 반품 |
| `reserved_quantity` | 결제 진행중 묶인 수량 | 주문 생성, 결제 완료, 예약 해제 |

**판매가능 = `stock_quantity - reserved_quantity`** — 저장하지 않고 항상 계산한다.

### 7.1 왜 한 컬럼으로 안 되나

주문 시점에 `stock_quantity` 를 바로 깎으면 두 가지가 망가진다.

- **실사 대조 기준이 사라진다.** 창고에 100개인데 5개가 결제중이면 화면엔 95개다. 관리자가 재고를 셀 때 매번 역산해야 한다.
- **복원 버그가 실물 재고를 영구 오염시킨다.** 복원이 한 번 누락되면 그 수량은 영원히 증발한다. 예약 컬럼이면 잘못돼도 진행중 주문에서 재계산해 고칠 수 있다.

### 7.2 상태 전이

```
주문 생성 (PENDING)
    reserved_quantity += N        ← 다른 사람에게는 이 수량이 없는 것으로 보인다
    stock_quantity 그대로

결제 완료 (PAID)
    reserved_quantity -= N
    stock_quantity    -= N        ← 실물이 여기서 나간다

결제 실패 / 취소 / 예약 만료
    reserved_quantity -= N
    stock_quantity 그대로         ← 실물은 애초에 안 건드렸다
```

### 7.3 예약 만료 — 반드시 필요하다 ★

결제창을 열어두고 브라우저를 닫으면 예약이 **영원히 잡힌다.** 재고가 있는데 아무도 못 사는 상태가 되고, 조용히 매출을 갉아먹는다.

**기한은 결제 수단마다 다르다.** 고정 TTL 은 쓰지 않는다 — 사람이 은행에 가야 하는 무통장입금을 카드와 같은 30분으로 잡으면 모든 무통장 주문이 자동취소된다.

- 주문 생성 시 `orders.payment_due_at` 에 **확정 저장**한다. 설정은 `config/shop.php` 의 `payment.due`.
  - `BANK_TRANSFER` 1일 (그날 자정까지)
  - `CARD` 30분
- 스케줄러(`shop:expire-orders`, 5분마다)가 기한 지난 `PENDING` 주문을 `CANCELED` 로 바꾸고 예약을 푼다.
- **스케줄러 프로세스가 실제로 떠 있어야 한다.** `php artisan schedule:work` 또는 크론. `artisan dev` 에는 포함되지 않는다.

### 7.4 해제 멱등성

`orders.stock_released_at` 이 있으면 이미 푼 주문이므로 두 번 빼지 않는다.
**이 컬럼이 없으면 스케줄러 재시도·중복 실행이 `reserved_quantity` 를 음수로 만든다.**

### 7.5 예약을 별도 테이블로 두지 않는 이유

**진행중 `PENDING` 주문의 `order_items` 가 곧 예약 내역이다.** `reserved_quantity` 는 그것을 빠르게 읽기 위한 비정규화 캐시다.

- 예약 주체가 주문 하나뿐이라 `stock_reservations` 테이블은 지금 필요 없다.
- 대신 **정합성 점검 커맨드**를 만든다: `PENDING` 주문에서 variant별 수량을 합산해 `reserved_quantity` 와 비교하고 **보고만** 한다. 자동 수정하지 않는다 — 원인을 봐야 한다.
- 장바구니 홀드처럼 주문 외 예약이 생기면 그때 테이블로 승격한다.

### 7.6 동시성 — 지금 환경에서 검증 불가

CLAUDE.md §5.2대로 **SQLite에서는 `lockForUpdate()` 가 사실상 무효**다. 동시 주문 오버셀을 **재현할 수도, 검증할 수도 없다.** 이 구간만큼은 로컬 테스트 통과가 안전을 보장하지 않는다.

```
DB::transaction(function () {
    // 1. variant 행들을 lockForUpdate() 로 잠근다 (id 오름차순 — 데드락 방지)
    // 2. stock_quantity - reserved_quantity >= 요청수량 확인
    // 3. reserved_quantity += N
    // 4. 주문 + order_items 생성
    // 5. stock_movements 기록
});
```

- **여러 variant를 잠글 때는 항상 id 오름차순으로 잠근다.** 순서가 엇갈리면 두 주문이 서로를 기다리는 데드락이 난다.
- 트랜잭션 경계는 라이브러리 안이다 (CLAUDE.md §4.2).

### 7.7 `stock_movements`

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `product_variant_id` | FK | `restrictOnDelete` |
| `type` | string(20) | 아래 표 |
| `stock_delta` | integer | 실물 변동. 부호 있음 |
| `reserved_delta` | integer | 예약 변동. 부호 있음 |
| `stock_after` | integer | 변동 후 실물 잔량 |
| `reserved_after` | integer | 변동 후 예약 잔량 |
| `order_id` | FK→`orders` nullable | 주문 기인이면 연결 |
| `admin_id` | FK→`admins` nullable | 수동 조정이면 연결 |
| `memo` | string(255) nullable | |
| `created_at` | timestamp | 갱신되지 않으므로 `updated_at` 불필요 |

| `type` | `stock_delta` | `reserved_delta` | 발생 시점 |
|---|---|---|---|
| `RESERVE` | 0 | `+N` | 주문 생성 |
| `RELEASE` | 0 | `-N` | 결제 실패·취소·예약 만료 |
| `SELL` | `-N` | `-N` | 결제 완료 |
| `RESTOCK` | `+N` | 0 | 반품·입고 |
| `MANUAL_IN` / `MANUAL_OUT` | `±N` | 0 | 관리자 수동 조정 |
| `ADJUST` | `±N` | 0 | 실사 반영 |

---

## 8. 쿠폰 ★

### 8.1 `coupons` — 쿠폰 마스터 (관리자가 만든다)

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `code` | string(30) unique nullable | 코드 입력형일 때만. 대문자 |
| `name` | string(50) | '신규가입 5천원 할인' |
| `issue_type` | string(20) | `SIGNUP` \| `MANUAL` \| `CODE` |
| `discount_type` | string(20) | `FIXED` \| `PERCENT` |
| `discount_value` | unsignedBigInteger | 원 또는 % |
| `max_discount_amount` | unsignedBigInteger nullable | **PERCENT 일 때 상한.** 없으면 무제한 |
| `min_order_amount` | unsignedBigInteger default 0 | 최소 주문금액 |
| `valid_days` | unsignedInteger nullable | **발급일 기준 유효일수** |
| `valid_from` | timestamp nullable | 절대 기간 시작 |
| `valid_until` | timestamp nullable | 절대 기간 종료 |
| `total_issue_limit` | unsignedInteger nullable | 총 발급 한도. null=무제한 |
| `per_user_limit` | unsignedInteger default 1 | 1인당 발급 한도 |
| `is_active` | boolean default true | |
| timestamps | | |

```
CouponIssueType    SIGNUP   회원가입 시 자동 발급
                   MANUAL   관리자가 지정 발급
                   CODE     고객이 코드 입력해 받음

CouponDiscountType FIXED    정액 (원)
                   PERCENT  정률 (%)
```

**`valid_days` 와 `valid_from`/`valid_until` 은 다른 축이다.**
가입쿠폰은 "발급일로부터 30일"(`valid_days=30`)이고, 이벤트 쿠폰은 "3월 1일~31일"(`valid_from`/`valid_until`)이다. 둘 다 설정되면 **더 이른 만료일**을 적용한다.

**`PERCENT` 쿠폰에는 `max_discount_amount` 를 강하게 권한다.** 상한 없는 정률 쿠폰은 고가 상품에서 손실이 무한정 커진다. 관리자 화면에서 경고를 띄운다.

### 8.2 `user_coupons` — 발급된 쿠폰

| 컬럼 | 타입 | 비고 |
|---|---|---|
| `id` | bigint PK | |
| `coupon_id` | FK→`coupons` | `restrictOnDelete`. 발급분 있는 쿠폰은 못 지운다 |
| `user_id` | FK→`users` | `cascadeOnDelete` |
| `issued_at` | timestamp | |
| `expires_at` | timestamp | **발급 시점에 확정 저장** |
| `used_at` | timestamp nullable | |
| `order_id` | FK→`orders` nullable | `nullOnDelete`. 사용된 주문 |
| timestamps | | |

- 인덱스: `(user_id, used_at)`, `coupon_id`, `expires_at`
- unique(`coupon_id`, `user_id`) — `per_user_limit = 1` 인 경우. 그 이상 허용하면 검증으로만 막는다.
- **`expires_at` 을 발급 시점에 계산해 박아둔다.** 마스터의 `valid_days` 를 나중에 바꿔도 이미 발급된 쿠폰의 만료일은 변하지 않아야 한다. 스냅샷 원칙과 같다.
- **쿠폰은 삭제하지 않는다.** `is_active = false` 로 내린다. 사용 이력이 매출과 엮이기 때문이다.

### 8.3 사용 시 규칙

- **1주문 1쿠폰.** `orders.user_coupon_id` 로 연결한다. 중복 사용은 1차 스코프 밖이다.
- 할인은 **상품 합계(`items_total`)에만** 적용한다. 배송비는 할인 대상이 아니다.
- `discount_total` 은 `items_total` 을 넘을 수 없다. 넘으면 `items_total` 로 자른다.
- 쿠폰 사용은 **주문 생성 트랜잭션 안에서** `used_at` 을 찍는다. 재고 예약과 같은 트랜잭션이다 — 따로 처리하면 이중 사용이 난다.
- 주문 취소 시 쿠폰을 **되살린다** (`used_at = null`, `order_id = null`). 단 이미 만료됐으면 되살리지 않는다.
- 비회원은 쿠폰을 쓸 수 없다. `user_coupons.user_id` 가 필수이기 때문이다.

---

## 9. 관리자 메뉴 추가분

`config/admin/menu.php` 에 아래를 더한다. 코드는 대문자, 한번 정하면 바꾸지 않는다 (CLAUDE.md §7.3).

| 그룹 | `menu_code` | 화면 |
|---|---|---|
| 상품관리 | `PRODUCT_LIST` | 상품 목록/등록/수정 (옵션·재고 포함) |
| 상품관리 | `PRODUCT_CATEGORY` | 카테고리 |
| 주문관리 | `ORDER_LIST` | 주문 목록/상세 |
| 주문관리 | `ORDER_SHIPMENT` | 배송 관리 |
| 회원관리 | `MEMBER_LIST` | 회원 목록 |
| **프로모션** | **`COUPON_LIST`** | **쿠폰 생성·관리, 발급 내역** |
| **설정** | **`SETTING_SHIPPING`** | **배송비 정책** |
| 관리자설정 | `SETTING_ROLE` | 권한설정 ✔구현됨 |
| 관리자설정 | `SETTING_ADMIN` | 관리자관리 ✔구현됨 |

---

## 10. 구현 순서

각 단계는 마이그레이션 → 모델/Enum → 라이브러리 → 관리자 화면 → 고객 화면.

1. `categories` → 카테고리 관리
2. `shipping_policies` → 배송비 설정 (`SETTING_SHIPPING`) — 상품이 참조하므로 먼저
3. `products` + 옵션 3종 + `product_images` → 상품 관리 (`PRODUCT_LIST`)
4. 상품 목록/상세 — 조합형 옵션 선택 UI (고객)
5. `carts` / `cart_items` → 장바구니
6. `coupons` / `user_coupons` → 쿠폰 관리 (`COUPON_LIST`) + 가입쿠폰 자동발급
7. `orders` / `order_items` / `stock_movements` → 주문 + 재고 예약 + 비회원 주문
8. `payments` (MOCK) → 결제 + 예약 만료 스케줄러
9. `shipments` → 배송 관리
10. 주문 관리 (관리자)
11. `order_returns` / `order_return_items` → 반품·교환 (`ORDER_RETURN`) — §11
12. `product_reviews` / `product_questions` + `product_images.type` → 후기·문의·상세이미지 — §12

2번이 3번보다 앞인 이유: `products.shipping_policy_id` 가 `shipping_policies` 를 참조한다.
6번이 7번보다 앞인 이유: `orders.user_coupon_id` 가 `user_coupons` 를 참조한다.

---

## 11. 반품 · 교환 ★

### 11.1 경계

**출고 전은 취소, 출고 후는 반품이다.**

| 주문 상태 | 되돌리는 방법 |
|---|---|
| PENDING · PAID | 고객·관리자 취소 (`OrderLibrary::cancel`) |
| PREPARING | 관리자 취소만 |
| SHIPPING · DELIVERED | **반품·교환** (`ReturnLibrary`) |
| CANCELED · REFUNDED | 없음 (종료 상태) |

취소는 주문을 통째로 되돌린다. 반품은 **항목·수량 단위**라 주문당 여러 건이 생긴다.

### 11.2 테이블

`order_returns`

| 컬럼 | 설명 |
|---|---|
| `type` | `RETURN` / `EXCHANGE` |
| `reason` | `CHANGE_OF_MIND` `SIZE_OR_COLOR` `DEFECTIVE` `WRONG_DELIVERY` `DAMAGED` `OTHER` |
| `responsibility` | `CUSTOMER` / `SELLER` — **배송비를 누가 내는가.** 승인 시 관리자가 확정 |
| `status` | `REQUESTED` → `APPROVED` → `PICKING` → `RECEIVED` → `COMPLETED` / `REJECTED` |
| `items_refund` `coupon_deduction` `shipping_deduction` `shipping_refund` `refund_amount` | **승인 시점 금액 스냅샷** |
| `pickup_carrier` `pickup_tracking_no` | 회수 송장 |
| `exchange_carrier` `exchange_tracking_no` | 교환 재발송 송장 |
| `restock` | 재판매 가능 여부. 불량·파손이면 `false` → 재고를 안 되돌린다 |

`order_return_items` — `order_item_id` + `quantity` (+ 교환이면 `exchange_variant_id`)

**송장을 `shipments` 에 넣지 않은 이유:** `shipments` 는 주문당 1건(최초 배송)이다.
두 번째 행을 허용하면 `Order::shipment()` 부터 의미가 흔들린다.

### 11.3 환불 금액

```
items_refund       = Σ(주문 시점 단가 × 반품 수량)          ← 스냅샷 단가 (§4.3)
coupon_deduction   = 할인액을 상품 금액 비율로 안분
shipping_deduction = 고객 귀책이면 config('shop.return.shipping_fee')
shipping_refund    = 판매자 귀책 + 전량 반품이면 최초 배송비
refund_amount      = max(0, items - coupon - deduction + refund)
```

**쿠폰 안분은 누적으로 계산한다.** 매 건 따로 내림하면 잔돈이 샌다
(worklog 밟으면 아픈 곳 §15).

```
이번 차감 = floor(할인 × (이전누계 + 이번) / 상품합계) − 이미차감액
```

**환불액을 0 으로 막는다.** 반품 배송비가 상품값보다 크면 음수가 되는데
추가 청구 수단이 없다. 부족분은 관리자 메모로 관리한다.

**교환은 정산이 없다** (`refund_amount = 0`). `shipping_deduction` 만 남겨
관리자가 "고객이 낼 배송비" 를 알 수 있게 한다 — 수납은 시스템 밖이다.

### 11.4 재고와 돈이 움직이는 시점

**`COMPLETED` 한 곳뿐이다.** 물건을 받기 전에 환불하면 회수하지 못한다.
그래서 `RECEIVED` 를 거치지 않으면 완료할 수 없다.

| 유형 | 처리완료 시 |
|---|---|
| 반품 | `restock` 이면 회수품 실물 +N. 주문 전량이 돌아왔으면 주문 `REFUNDED` + 결제 `REFUNDED` + 쿠폰 복원 |
| 교환 | 회수품 +N, 교환품 −N (실물에서 바로 뺀다 — 이미 결제된 건이라 예약 단계가 없다) |

**쿠폰은 전량 반품일 때만 복원한다.** 부분 반품에서 되살리면
남은 주문에 할인이 적용된 채로 쿠폰을 다시 쓴다.

### 11.5 수량 점유

같은 주문 항목을 중복 신청할 수 없다.
`remainingQuantities()` = 주문 수량 − (반려되지 않은 신청들의 수량 합).

**반려·고객 취소는 점유를 푼다** (`ReturnStatus::occupiesQuantity()`).
사유를 바꿔 다시 신청할 수 있어야 한다.

### 11.6 기한

배송완료일 + `config('shop.return.days')` (기본 7일).

- 아직 배송중이면 기한이 시작되지 않은 것으로 보고 받아둔다.
- **관리자 대행 접수는 기한을 보지 않는다** — 하자는 법정 기한이 더 길고,
  예외 판단은 시스템이 아니라 사람이 한다.

### 11.7 교환 제약

**같은 상품의 다른 조합으로만** 바꾼다. 다른 상품은 금액이 달라져
반품 후 재주문이 맞다.

교환 재고 확인은 **접수가 아니라 `complete()` 에서 잠근 채로** 한다.
접수와 발송 사이에 재고가 바뀌기 때문이다.

---

## 12. 상품 상세 이미지 · 후기 · 문의 ★

### 12.1 상세 이미지

`product_images.type` 으로 용도를 나눈다. 테이블은 그대로다.

| 값 | 쓰임 | 대표 개념 | 한도 |
|---|---|---|---|
| `GALLERY` | 상단 갤러리. **첫 장이 목록 썸네일** | 있음 (`is_primary`) | `shop.image.max_per_product` |
| `DETAIL` | 상세 설명 영역에 세로로 이어 붙임 | 없음 | `shop.image.max_detail_per_product` |

**업로드·정렬은 반드시 용도를 함께 넘긴다.** 안 그러면 상세 이미지가 대표로 지정되어
목록 썸네일이 엉뚱한 그림으로 바뀐다. `ProductImageLibrary::reorder()` 가
같은 용도 안에서만 동작하도록 소유권과 함께 검사한다.

### 12.2 상품 후기 · 평점

**구매자만 쓴다.** 근거는 `order_items` 다:

- `product_reviews.order_item_id` 는 **unique** — 주문 항목당 후기 하나
- 그 주문이 `DELIVERED` 여야 한다. 물건을 받아봐야 후기다
- 관리자 대행 작성은 없다

**후기는 삭제하지 않고 숨긴다** (`ReviewStatus::HIDDEN`). 고객이 '삭제' 를 눌러도 숨김이다.
평점 이력과 구매 이력이 엮여 있어 지우면 "왜 평점이 갑자기 올랐나" 를 설명할 수 없다.

**평점은 `products` 에 비정규화한다.**

```
products.review_count   노출 중인 후기 수
products.rating_sum     노출 중인 후기의 별점 합
평균 = rating_sum / review_count   (저장하지 않는다)
```

- **평균이 아니라 합계를 저장하는 이유:** 평균은 부동소수라 누적하면 오차가 쌓이고,
  후기 하나가 숨겨질 때 되돌릴 수가 없다. 합계·건수는 정수라 정확히 가감된다.
- 목록 20개마다 후기를 집계하면 화면이 느려진다. 그래서 컬럼으로 둔다.
- **가감 책임은 `ProductReviewLibrary` 밖으로 나가면 안 된다.** 컬럼을 직접 만지는 코드가
  생기는 순간 숫자가 어긋나고, 어긋난 걸 알아챌 방법이 없다.
- 상태 변경은 **멱등하다.** 같은 상태로 다시 바꾸면 아무것도 하지 않는다 —
  숨김을 두 번 누르면 평점이 두 번 빠지는 사고를 막는다 (§7.4 예약 해제와 같은 원리).
- `review_count` / `rating_sum` 은 모델 `Fillable` 에서 **일부러 뺐다** (§7.5 `reserved_quantity` 와 같은 이유).

고객 화면에서는 **이름을 가린다** (`김서연` → `김*연`). 관리자 화면은 실명 그대로다.

### 12.3 상품 문의 (Q&A)

1:1 문의(`inquiries`)와 **다른 테이블**이다. 붙는 대상과 공개 범위가 다르다:

| | 1:1 문의 | 상품 문의 |
|---|---|---|
| 붙는 곳 | 주문 | 상품 |
| 공개 | 비공개 | **기본 공개** |
| 구매 필요 | — | **불필요** (사기 전에 묻는 게 문의다) |
| 처리 화면 | 회원관리 > 회원 상세 | 상품관리 > 상품문의 |

**비밀글은 서버에서 내용을 지워 내린다.** 화면에서 가리면 개발자도구로 다 보인다.
`ProductQuestion::isVisibleTo()` 가 판단하고, 볼 수 없으면 `content`·`answer` 가 `null` 로 간다.
목록에서 행 자체를 감추지는 않는다 — 아예 없애면 답변 대기 건수가 안 맞아 보인다.

답변이 달린 문의는 작성자도 못 지운다. 답변이 고아가 된다.

### 12.4 상담 채널 (네이버 톡톡 등)

**실시간 상담을 자체 구현하지 않는다.** 상담원이 붙어 있어야 의미가 있고,
그건 시스템이 아니라 운영의 문제다. `config('shop.support.channel.url')` 에
외부 채널 주소를 넣으면 스토어 전역에 플로팅 버튼이 뜨고, 비어 있으면 안 뜬다.

네이버 톡톡은 파트너센터에서 채널을 만들면 대화 링크가 나온다.
카카오 채널·오픈채팅 등 URL 기반이면 무엇이든 같은 자리에 붙는다.
