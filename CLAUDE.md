# CLAUDE.md — 쇼핑몰 프로젝트

이 파일은 Claude Code가 이 저장소에서 작업할 때 지켜야 할 규칙이다.
**여기 적힌 내용은 기본 동작보다 우선한다.**

> **작업 시작 전에 [`docs/worklog.md`](docs/worklog.md) 를 먼저 읽는다.**
> 현재 진행 상황, 다음 할 일, 그리고 **이미 밟아본 함정**이 정리되어 있다.
> **테이블·컬럼은 [`docs/tables.md`](docs/tables.md)** — 실제 스키마에서 뽑은 레퍼런스다.
> 설계 의도와 "왜 그렇게 했는가" 는 [`docs/schema-draft.md`](docs/schema-draft.md).
>
> 기능을 끝낼 때마다 worklog 에 **"왜 그렇게 했는가"와 "밟으면 아픈 곳"** 을 남긴다.
> 코드를 보면 아는 사실은 적지 않는다.
>
> `docs/` 는 **옵시디언 볼트로도 열린다** — [`docs/홈.md`](docs/홈.md) 가 시작점이고
> 함정 29개·도메인별 설계/스키마로 바로 가는 링크가 있다. 사본이 아니라 원본을 가리킨다.

---

## 1. 프로젝트 개요

- 개인 쇼핑몰(커머스) 사이트. 상품 노출 → 장바구니 → 주문 → 결제 → 배송 조회까지가 1차 목표.
- 현재 **서버 없음**. 전부 로컬(Windows 11)에서 개발한다. 배포 관련 작업은 별도 지시가 있을 때만 한다.
- **관리자와 고객은 계정 체계부터 분리**한다 (§7). 한 Laravel 앱 안에서 가드/라우트/미들웨어로 나눈다.

## 2. 기술 스택

**모든 의존성은 최신 안정 버전을 쓴다.** 아래는 2026-08-20 기준 확인값이다.

| 영역 | 선택 | 버전 | 비고 |
|---|---|---|---|
| 런타임 | PHP | **8.4** (최소 8.3) | Laravel 13이 `^8.3`을 요구 |
| 백엔드 | Laravel | **13.x** (13.26.1) | |
| 프론트 연결 | `inertiajs/inertia-laravel` | **3.x** (3.3.1) | 별도 API 서버 없음. SPA지만 라우팅은 Laravel이 소유 |
| 프론트 | Vue 3 (Composition API, `<script setup>`) | **3.5.x** | Options API 금지 |
| Inertia 클라이언트 | `@inertiajs/vue3` | **3.7.x** | |
| 번들러 | Vite | **8.x** | `laravel-vite-plugin` 3.x, `@vitejs/plugin-vue` 6.x |
| 스타일 | Tailwind CSS | **4.x** | v4는 설정이 CSS 우선. v3 문법(`tailwind.config.js` 중심)과 다르니 주의 |
| DB | **PostgreSQL** | **18.6** | 2026-08-20 전환 완료. `DB_CONNECTION=sqlite` 로 되돌릴 수 있다 |
| 인증 (고객) | `laravel/fortify` | **1.38** | `web` 가드. 이메일 인증 사용 (§12.2) |
| 인증 (관리자) | 수동 구현 | — | `admin` 가드. Fortify는 단일 가드라 겸용 불가 (§12.3) |

> 스캐폴딩 후 `composer.json` / `package.json`의 실제 설치값과 위 표가 다르면 **표를 실제값으로 고친다.** 버전을 임의로 낮추지 않는다.

## 3. 로컬 개발 명령어

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

개발 서버 — Laravel 13은 `artisan dev` 하나로 서버 + Vite + 큐 + 로그를 띄운다:

```bash
php artisan dev
```

**`artisan dev` 에 스케줄러는 포함되지 않는다.** 재고 예약 만료를 확인하려면 별도 터미널에서:

```bash
php artisan schedule:work
```

이게 안 돌면 입금하지 않은 주문이 재고를 계속 물고 있는다. 운영에서는 크론/작업 스케줄러가 1분마다 `schedule:run` 을 호출한다.

따로 띄우려면 터미널 2개:

```bash
php artisan serve
npm run dev
```

기타:

```bash
php artisan migrate:fresh --seed   # 로컬 DB 초기화 (운영에도 필요한 시드만)
php artisan shop:demo --fresh      # 초기화 + 데모 데이터 (상품·쿠폰·주문) ★로컬 전용
php artisan test                   # 테스트
./vendor/bin/pint                  # 코드 포맷 (커밋 전 필수)
npm run build                      # 프로덕션 빌드
```

**시더는 두 층이다.** 섞지 않는다:

| | 담는 것 | 실행 |
|---|---|---|
| `DatabaseSeeder` | 운영에도 필요한 것 — 관리자 계정, 기본 배송비 정책, 입금 계좌 | `db:seed` |
| `DemoSeeder` | 화면을 채우는 데모 — 카테고리·상품·이미지·쿠폰·회원·주문·반품·문의 | `db:seed --class=DemoSeeder` |

`DemoSeeder` 는 `production` 환경과 **상품이 이미 있는 DB** 에서 스스로 멈춘다.
데모 데이터는 전부 **라이브러리를 거쳐** 만든다 — 모델을 직접 만들면 slug·재고 예약·
쿠폰 사용처리가 빠져서 시드 데이터만 앱이 만드는 것과 다른 모양이 된다.

- 개발 서버는 사용자가 직접 띄우는 것을 기본으로 한다. Claude가 `php artisan serve`를 백그라운드로 오래 물고 있지 않는다.
- `.env`는 절대 커밋하지 않고, 값을 임의로 바꾸지 않는다. 새 키가 필요하면 `.env.example`에도 같이 추가한다.

### 3.1 로컬 환경 실측값 (2026-08-20 설치 완료)

| 항목 | 값 |
|---|---|
| PHP | 8.4.24 (ZTS x64) |
| PHP 경로 | `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\` |
| `php.ini` | 위 경로. `php.ini-development` 복사본에 확장 활성화 |
| 활성 확장 | curl, fileinfo, gd, intl, mbstring, openssl, pdo_pgsql, pdo_sqlite, pgsql, sqlite3, zip |
| Composer | 2.10.2 (`%USERPROFILE%\bin\composer.phar` + `composer.bat`) |
| Node / npm | 24.16.0 / 11.13.0 |
| SQLite DB | `database/database.sqlite` |
| PostgreSQL | 18.6 — `C:\Program Files\PostgreSQL\18\`, 포트 5432 |
| PG 서비스 | `postgresql-x64-18` (Running / 자동 시작) |
| `psql` | PATH 등록됨 |

- **PHP 확장을 추가로 켤 일이 생기면 위 `php.ini`를 직접 수정**한다. 시스템에 다른 PHP가 없으므로 이 파일이 유일한 설정이다.
- `pdo_pgsql` / `pgsql`은 이미 켜져 있다. PostgreSQL 전환 시 PHP 쪽은 추가 작업이 없다.

### 3.2 파일 저장소

상품 이미지는 `config('shop.image.disk')` 가 정하는 디스크에 저장한다. **코드에 디스크명을 박지 않는다.**

| 환경 | `SHOP_IMAGE_DISK` | 비고 |
|---|---|---|
| 로컬 (현재) | `public` | `storage/app/public` + `public/storage` 링크 |
| 운영 (예정) | `s3` | `AWS_*` 채우고 아래 패키지 설치 |

```bash
php artisan storage:link                       # 로컬 최초 1회 (Windows는 Junction, 관리자 권한 불필요)
composer require league/flysystem-aws-s3-v3    # S3 전환 시에만
```

- **DB에는 상대 경로만 저장**하고 URL은 `Storage::disk(...)->url()` 로 만든다. 그래서 디스크를 바꿔도 데이터 마이그레이션이 없다 — 파일만 옮기면 된다.
- 파일과 DB 행을 같이 지울 때는 **항상 DB 행이 먼저**다. 파일 삭제가 실패하면 고아 파일이 남을 뿐이지만, 반대면 깨진 이미지를 가리키는 행이 남는다.

## 4. 디렉터리 및 레이어 규칙

### 4.1 디렉터리 구조

```
app/
  Models/                 Eloquent 모델
  Http/Controllers/       얇게 유지. 데이터 조회·비즈니스 로직 금지 (§4.2)
    Store/                고객용
    Admin/                관리자용
  Http/Requests/          검증은 전부 FormRequest로
  Http/Middleware/        AdminAuth, AdminPermission 등
  Libraries/              ★ 데이터 조회 + 가공. 실제 일은 전부 여기서 (§4.2)
    Product/
    Order/
    Admin/
  Enums/                  상태값 정의. 도메인별 하위 폴더 (§6)
    Order/
    Product/
    Admin/
  Support/                도메인에 안 묶이는 헬퍼
.claude/
  skills/                 UI/UX 참고 데이터 (3자 MIT, .claude/skills/README.md 참고)
config/
  shop.php                쇼핑몰 공통 설정
  admin/
    menu.php              관리자 메뉴·페이지 정의 (권한의 원천, §7.3)
routes/
  web.php                 고객(스토어프론트) 전용. 관리자 라우트 금지
  auth.php                고객 인증 (스캐폴딩 생성물)
  admin.php               관리자 전용. 로그인 + 업무 라우트 (§7.4)
  console.php
resources/js/
  Pages/                  Inertia 페이지 컴포넌트. 라우트와 1:1
    Store/
    Admin/
  Components/             재사용 UI
  Layouts/                StoreLayout / AdminLayout
database/
  migrations/
  seeders/
  factories/
```

### 4.2 라이브러리 레이어 ★

**컨트롤러에서 데이터를 가져오지 않는다.** 조회·가공은 전부 `app/Libraries/`에서 하고 결과만 돌려받는다.

컨트롤러가 하는 일은 딱 셋이다: **요청 받기 → 라이브러리 호출 → 응답**. 그 사이에 쿼리도, 계산도, 조건 분기도 넣지 않는다.

```php
// X — 컨트롤러가 직접 조회
public function index(Request $request)
{
    $products = Product::with('images')
        ->where('is_active', true)
        ->when($request->keyword, fn ($q, $k) => $q->where('name', 'like', "%{$k}%"))
        ->orderByDesc('id')
        ->paginate(20);

    return Inertia::render('Store/Product/Index', ['products' => $products]);
}

// O — 라이브러리가 조회·가공해서 반환
public function index(ProductSearchRequest $request)
{
    return Inertia::render('Store/Product/Index', [
        'products' => $this->productLibrary->getSaleList($request->validated()),
    ]);
}
```

**규칙**

- `Eloquent` / `DB` 파사드 호출은 라이브러리와 모델 안에서만. 컨트롤러·Vue에서 직접 쿼리 금지.
- 위치는 도메인별 폴더. `app/Libraries/Product/ProductLibrary.php` 형태. 한 파일이 커지면 목적별로 쪼갠다 (`ProductLibrary` / `ProductStockLibrary`).
- 클래스는 컨트롤러 생성자 주입으로 받는다. 라이브러리 안에서 `new`로 다른 라이브러리를 만들지 않는다.
- 메서드는 **반환 타입을 명시**한다. `array`를 두루뭉술하게 넘기지 말고 모델·컬렉션·DTO로 반환한다.

**재사용을 실제로 가능하게 하는 조건 — 이게 핵심이다**

- 라이브러리는 `Request` / `Session` / `Auth` / `Inertia`에 **의존하지 않는다.** 필요한 값은 인자로 받는다.
  - 이유: `Request`를 물고 있으면 HTTP 요청이 있을 때만 동작한다. 그러면 아티즌 커맨드, 큐 잡, 스케줄러, 테스트에서 재사용이 불가능해진다. 인자로만 받으면 어디서든 부를 수 있다.
- `Inertia::render()`나 리다이렉트를 라이브러리가 하지 않는다. 응답 형태를 정하는 건 컨트롤러 책임이다. 라이브러리는 데이터만 돌려준다.
- **고객용과 관리자용이 같은 라이브러리를 공유**한다. 상품 조회 로직을 `Store/ProductController`와 `Admin/ProductController`가 각각 짜지 않는다. 노출 조건 차이는 라이브러리 메서드를 나눠서 표현한다 (`getSaleList()` / `getAdminList()`).
- 트랜잭션 경계는 라이브러리 안에 둔다. 컨트롤러가 `DB::transaction()`을 열지 않는다.
- 라이브러리끼리 호출은 허용한다. 단 **순환 참조 금지** — A가 B를 부르면 B는 A를 부르지 않는다.

**모델의 역할**

- 모델에는 관계, 캐스팅, 스코프, 접근자까지만 둔다.
- 여러 테이블을 엮거나 조건이 붙는 조회는 모델이 아니라 라이브러리에서 조립한다.

### 4.3 화면 만들 때 지키는 것

- **아이콘에 이모지를 쓰지 않는다.** OS·브라우저마다 모양이 달라 일관성이 깨진다.
  `resources/js/Components/Icon.vue` 에 경로를 추가해 쓴다(24×24 stroke, `currentColor`).
- **`cursor-pointer` 를 컴포넌트마다 붙이지 않는다.** Tailwind 4 Preflight 가 버튼 커서를
  `default` 로 되돌려서 `resources/css/app.css` 의 `@layer base` 에서 한 번에 정해뒀다.
- **좁은 화면을 숫자로 확인한다.** 눈으로 보면 놓친다 —
  `scrollWidth > clientWidth` 로 360·375·768·1024px 를 훑는다(밟으면 아픈 곳 §28).
- `.claude/skills/ui-ux-pro-max` 에 UX 가이드라인과 검수 체크리스트가 있다.
  **디자인 시스템 생성기로 쓰지 않는다** — 이유는 `.claude/skills/README.md` 에 적어뒀다.

## 5. DB 이식성 규칙 ★가장 중요★

**2026-08-20 PostgreSQL 18.6 전환 완료.** 마이그레이션 17개가 SQLite·PostgreSQL 양쪽에서 무수정으로 돌았다 — 아래 규칙이 실제로 값을 했다.

전환했다고 규칙을 놓지 않는다. **SQLite 로 되돌릴 수 있는 상태를 유지한다** (`DB_CONNECTION=sqlite` 한 줄). 테스트를 SQLite 로 빠르게 돌리는 선택지가 살아 있어야 하고, 특정 DB 문법에 묶이는 순간 그게 사라진다.

### 5.1 절대 금지

- **마이그레이션·모델·쿼리에 특정 DB 전용 SQL 금지.** `DB::statement()`, `whereRaw()`, `selectRaw()`는 원칙적으로 쓰지 않는다. 불가피하면 코드에 `// PORTABILITY:` 주석으로 이유를 남기고 사용자에게 보고한다.
- **`ILIKE` 금지.** PostgreSQL 전용이다. SQLite에서 동작하지 않는다.
- **`->enum()` 컬럼 금지.** 대신 `->string()` + `app/Enums/`의 PHP enum + 모델 `$casts` (§6). DB enum은 이후 값 추가/변경이 매우 번거롭다.
- **DB 함수에 의존하는 기본값 금지** (`now()`, `uuid_generate_v4()` 등). 기본값은 애플리케이션에서 채운다.

### 5.2 알려진 동작 차이 — 반드시 인지

| 항목 | SQLite | PostgreSQL | 대응 |
|---|---|---|---|
| `LIKE` 대소문자 | ASCII 기준 **구분 안 함** | **구분함** | 검색은 양쪽 소문자로 정규화 후 비교, 또는 라이브러리에 검색 메서드 하나로 캡슐화 |
| boolean | 0 / 1 정수 | true / false | 모델 `$casts`에 `'is_active' => 'boolean'` **항상** 지정 |
| `unsigned` | 무시 | 타입 없음 (그냥 bigint) | 음수 방지는 DB가 아니라 검증/도메인 로직에서 |
| `->after()` 컬럼 순서 | **무시됨** | **무시됨** (MySQL 전용) | 아예 쓰지 않는다. 컬럼 순서에 의존하는 코드 작성 금지 (`SELECT *` 순서 가정 금지) |
| `lockForUpdate()` | **사실상 무효** | 정상 동작 | 재고 차감 등 동시성 로직은 PostgreSQL 기준으로 작성. SQLite에서는 동시성 검증이 안 된다는 점을 알고 있을 것 |
| 정렬 미지정 시 순서 | 우연히 id 순 | 보장 없음 | 목록 쿼리는 **항상** `orderBy()` 명시 |
| 전문검색 | FTS5 | tsvector | 1차는 `LIKE` 기반으로 두고, 라이브러리 뒤에 숨겨 나중에 교체 |
| `->change()` | 테이블 재생성 | ALTER | 컬럼 변경 마이그레이션은 양쪽에서 모두 실행 확인 |

### 5.3 마이그레이션 작성 규칙

- 파일 하나 = 목적 하나. 테이블 생성과 무관한 변경을 섞지 않는다.
- **`down()`을 반드시 구현**한다. 비워두지 않는다.
- 외래키는 `$table->foreignId('x_id')->constrained()->cascadeOnDelete()` 형태를 기본으로 한다. 삭제 정책(`cascade`/`restrict`/`nullOnDelete`)을 매번 명시적으로 고른다.
- 금액은 **정수(원 단위)** 로 저장한다. `->unsignedBigInteger('price')`. `float`/`double` 금지. (KRW는 소수점이 없고, 부동소수 반올림 오차를 원천 차단한다.)
- 날짜/시간은 UTC로 저장한다. `config/app.php`의 `timezone`은 `UTC`로 두고 표시 시점에만 KST로 변환한다.
- JSON 컬럼은 `->json()` 또는 `->jsonb()`를 쓴다 (SQLite에서는 text로 떨어지고 Laravel이 캐스팅을 처리한다). 단, **JSON 내부 값으로 검색·정렬하는 쿼리는 짜지 않는다** — 검색이 필요한 값은 별도 컬럼으로 뺀다.
- 자주 조회하는 컬럼과 모든 외래키에 인덱스를 건다. 유니크 제약은 DB에 건다(애플리케이션 검증만 믿지 않는다).
- **이미 적용된 마이그레이션 파일을 수정하지 않는다.** 변경은 항상 새 마이그레이션으로. (실서버 전이라 예외를 두고 싶으면 사용자에게 먼저 확인.)

### 5.4 전환 기록 (완료)

**(1) 만들어둔 DB와 계정** — 재구축이 필요하면 이 순서로 한다:

```bash
createdb -U postgres -E UTF8 shop
psql -U postgres -c "CREATE ROLE shop_app LOGIN PASSWORD '원하는_비밀번호';"
psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE shop TO shop_app;"
psql -U postgres -d shop -c "GRANT ALL ON SCHEMA public TO shop_app;"
psql -U postgres -d shop -c "ALTER SCHEMA public OWNER TO shop_app;"
```

**뒤의 두 줄이 핵심이다.** PostgreSQL 15부터 `public` 스키마의 기본 CREATE 권한이 회수되어, DB 권한만 줘서는 마이그레이션이 테이블을 못 만든다.

`pg_hba.conf` 가 `scram-sha-256` 이라 모든 접속에 비밀번호가 필요하다. 비대화형 셸에서 `psql` 을 그냥 부르면 **입력을 기다리며 멈춘다** — `PGPASSWORD` 환경변수를 쓴다.

**(2) `.env` 교체**

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=shop
DB_USERNAME=shop_app
DB_PASSWORD=위에서_정한_비밀번호
```

**(3) PostgreSQL에서만 검증되는 것 ★**

`lockForUpdate()`가 SQLite에서 무효였기 때문에, 아래는 전환 후에야 확인할 수 있었다:

- **동시 주문 오버셀** — 재고 1개에 5개 프로세스가 동시 주문 → 정확히 1건만 성공 (검증 완료)
- 여러 조합을 잠글 때의 데드락

**재고·주문 관련 코드를 고친 뒤에는 SQLite 테스트 통과만으로 안전을 판단하지 않는다.**

- SQLite로 되돌리려면 `DB_CONNECTION=sqlite` 한 줄만 바꾸면 된다.
- **애플리케이션은 `postgres` superuser로 접속하지 않는다.** `shop_app` 전용 계정을 쓴다.

## 6. 상태값 / 상수 정의 규칙 ★

### 6.1 값은 항상 대문자

**DB에 저장되는 상태·구분 값은 예외 없이 영문 대문자 + 언더스코어로 정의한다.**

```
O   'PENDING', 'PAID', 'SHIPPING', 'DELIVERED', 'CANCELED'
    'CARD', 'BANK_TRANSFER', 'VIRTUAL_ACCOUNT'
    'SUPER_ADMIN', 'MANAGER', 'STAFF'

X   'pending', 'Pending', 1, 'ord_pending', '결제대기'
```

- 숫자 코드(1, 2, 3)로 상태를 저장하지 않는다. DB를 직접 열었을 때 읽을 수 있어야 한다.
- 한글은 값이 아니라 **라벨**이다. 값 → 한글 라벨 변환은 enum의 `label()` 메서드가 담당한다.
- **적용 범위는 "값"이다.** 테이블명·컬럼명·라우트명은 기존대로 snake_case 소문자를 쓴다. 대문자 규칙은 컬럼에 들어가는 내용물에만 적용된다.

### 6.2 정의 위치 — 흩어놓지 않는다

상태값을 컨트롤러나 Vue 컴포넌트에 문자열로 직접 박지 않는다. 반드시 아래 중 한 곳에 정의하고 참조한다.

**(1) 상태·구분값 → `app/Enums/`의 PHP backed enum**

case 이름과 value를 동일한 대문자로 맞춘다.

```php
// app/Enums/Order/OrderStatus.php
enum OrderStatus: string
{
    case PENDING   = 'PENDING';
    case PAID      = 'PAID';
    case SHIPPING  = 'SHIPPING';
    case DELIVERED = 'DELIVERED';
    case CANCELED  = 'CANCELED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => '결제대기',
            self::PAID      => '결제완료',
            self::SHIPPING  => '배송중',
            self::DELIVERED => '배송완료',
            self::CANCELED  => '취소',
        };
    }
}
```

- 모델 `$casts`에 반드시 연결한다: `'status' => OrderStatus::class`
- **한 도메인에 enum이 3개 이상 쌓이면 하위 폴더로 분리**한다. `app/Enums/Order/`, `app/Enums/Product/`, `app/Enums/Admin/`.

**(2) 목록·정책·튜닝 값 → `config/` 파일**

enum이 아닌 것(페이지당 개수, 업로드 제한, 메뉴 정의 등)은 config로 뺀다. 매직넘버 금지.

```php
// config/shop.php
return [
    'per_page'        => ['product' => 20, 'order' => 30],
    'image'           => ['max_kb' => 2048, 'allowed' => ['jpg', 'jpeg', 'png', 'webp']],
    'order_no_prefix' => 'ORD',
];
```

- **한 파트에 설정이 많아지면 파일을 쪼개고, 더 많아지면 폴더로 만든다.** 예: `config/admin/menu.php`. `config/shop.php` 하나에 전부 밀어 넣지 않는다.
- 참조는 `config('shop.per_page.product')`. `env()`는 config 파일 안에서만 호출한다(설정 캐시 시 `env()`가 null이 된다).

**(3) 프론트에서 쓸 상태 라벨**

Vue에서 상태 문자열을 다시 하드코딩하지 않는다. 목록/뱃지에 필요한 라벨은 서버가 props로 같이 내려주거나, Inertia 공유 데이터로 한 번만 내려서 재사용한다.

## 7. 관리자(Admin) 설계 ★

### 7.1 계정 테이블 분리

**관리자와 고객은 테이블도 인증 가드도 완전히 분리한다.** `users`에 `is_admin` 플래그를 두는 방식은 쓰지 않는다.

| 대상 | 테이블 | 가드 | 라우트 | 레이아웃 |
|---|---|---|---|---|
| 고객 | `users` | `web` | `/` 이하 | `StoreLayout` |
| 관리자 | `admins` | `admin` | `/admin` 이하 | `AdminLayout` |

- `config/auth.php`에 `admin` 가드와 `admins` 프로바이더를 추가한다.
- 비밀번호 재설정 토큰 테이블도 각각 따로 둔다.
- 세션이 섞이지 않아야 한다. 고객 로그인 상태로 `/admin`에 접근하면 관리자 로그인 화면으로 보낸다.
- 관리자 로그인은 이메일이 아니라 **로그인 ID(`login_id`)** 기준으로 한다. 관리자 계정은 가입이 아니라 **상위 관리자가 생성**하는 것이므로 공개 회원가입 라우트를 만들지 않는다.

### 7.2 권한 모델

역할(Role) 기반으로 간다. 관리자 1명 = 역할 1개, 역할이 접근 가능한 페이지 집합을 가진다.

```
admins                    관리자 계정
  id, login_id(unique), name, email, password,
  admin_role_id(FK), status('ACTIVE' | 'SUSPENDED'),
  last_login_at, timestamps

admin_roles               역할
  id, code(unique, 대문자: 'SUPER_ADMIN' | 'MANAGER' | 'STAFF'),
  name, description, timestamps

admin_role_permissions    역할 × 메뉴 권한
  id, admin_role_id(FK), menu_code(대문자),
  can_read(bool), can_write(bool), timestamps
  unique(admin_role_id, menu_code)
```

- `SUPER_ADMIN` 역할은 권한 검사를 **전부 통과**시킨다 (레코드에 의존하지 않는 하드코딩 예외).
- `can_read` = 페이지 조회, `can_write` = 생성/수정/삭제. 읽기 없이 쓰기만 가능한 조합은 허용하지 않는다.
- 권한 레코드가 없으면 **차단**이 기본값이다 (allow-list).

### 7.3 메뉴 정의는 config에 (§6.2)

메뉴 목록은 DB가 아니라 `config/admin/menu.php`에 둔다. 코드로 관리되어야 배포 시점에 메뉴와 라우트가 함께 움직이고, 메뉴를 추가할 때 마이그레이션이 필요 없기 때문이다. DB는 "어떤 역할이 어떤 `menu_code`에 접근 가능한가"만 저장한다.

```php
// config/admin/menu.php
return [
    'PRODUCT' => [
        'name'     => '상품관리',
        'children' => [
            'PRODUCT_LIST'     => ['name' => '상품목록', 'route' => 'admin.products.index'],
            'PRODUCT_CATEGORY' => ['name' => '카테고리', 'route' => 'admin.categories.index'],
        ],
    ],
    'ORDER' => [ /* ... */ ],
    'SETTING' => [
        'name'     => '관리자설정',
        'children' => [
            'SETTING_ROLE'  => ['name' => '권한설정',   'route' => 'admin.settings.roles.index'],
            'SETTING_ADMIN' => ['name' => '관리자관리', 'route' => 'admin.settings.admins.index'],
        ],
    ],
];
```

- `menu_code`는 대문자. 한번 정한 코드는 바꾸지 않는다 (DB 권한 레코드가 이 값을 참조한다).
- 메뉴를 삭제할 때는 해당 `menu_code`의 권한 레코드 정리도 같이 한다.

### 7.4 라우트 파일 분리

**관리자 라우트와 고객 라우트는 파일부터 나눈다.** `routes/web.php`에 관리자 라우트를 넣지 않는다. 파일이 갈려 있어야 `web.php`를 열었을 때 "여기 있는 건 전부 공개 영역"이 성립하고, 관리자 라우트에 미들웨어를 빠뜨리는 사고가 구조적으로 막힌다.

| 파일 | 대상 | prefix | 이름 prefix | 기본 미들웨어 |
|---|---|---|---|---|
| `routes/web.php` | 고객 | 없음 | 없음 | `web` |
| `routes/auth.php` | 고객 인증 | 없음 | 없음 | `web` |
| `routes/admin.php` | 관리자 | `/admin` | `admin.` | `web`, `auth:admin` |

`routes/admin.php` 등록 — Laravel 13은 `RouteServiceProvider`가 없다. 라우팅 설정은 전부 `bootstrap/app.php`에서 한다:

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__ . '/../routes/web.php',
    commands: __DIR__ . '/../routes/console.php',
    then: function () {
        Route::middleware('web')
            ->prefix('admin')
            ->name('admin.')
            ->group(base_path('routes/admin.php'));
    },
)
```

`routes/admin.php` 내부는 인증 전/후 두 그룹으로 나눈다. 로그인 화면까지 `auth:admin`을 걸면 무한 리다이렉트가 된다.

```php
// 인증 전 — 로그인만
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('login', [AdminLoginController::class, 'store']);
});

// 인증 후 — 나머지 전부
Route::middleware('auth:admin')->group(function () {
    Route::post('logout', [AdminLoginController::class, 'destroy'])->name('logout');

    Route::middleware('admin.permission:PRODUCT_LIST,READ')
        ->get('products', [ProductController::class, 'index'])->name('products.index');
    // ...
});
```

- 라우트 이름은 `admin.` prefix가 자동으로 붙는다. 그룹 안에서 `->name('admin.products.index')`로 다시 쓰지 않는다 (`admin.admin.` 이 된다).
- 관리자 라우트가 늘어나면 `routes/admin/` 폴더로 쪼개고 `admin.php`에서 `require` 한다. 파일 하나가 비대해지게 두지 않는다.

### 7.5 접근 통제

- 모든 `/admin` 라우트는 `auth:admin` + `admin.permission:{MENU_CODE},{READ|WRITE}` 미들웨어를 통과한다. 미들웨어 없는 관리자 라우트를 만들지 않는다(로그인·로그아웃 제외).
- 사이드바 메뉴는 **현재 관리자가 `can_read` 권한을 가진 항목만** 렌더링한다. 단, 화면에서 숨기는 것은 UI 편의일 뿐이고 **차단의 근거는 서버 미들웨어**다. 둘 다 있어야 한다.
- 관리자 권한 정보는 Inertia 공유 데이터로 내려서 프론트가 매번 조회하지 않게 한다.

### 7.6 관리자설정 메뉴 (구현 완료)

**안전장치는 전부 라이브러리 안에 있다.** 컨트롤러에서 우회하지 않는다:
`AdminRoleLibrary` / `AdminAccountLibrary` 가 규칙을 판단해 `AdminPolicyException` 을 던지고,
컨트롤러는 그것을 응답으로 바꾸기만 한다. 화면에서 버튼을 숨기는 것은 UI 편의일 뿐이다.

**(1) 권한설정 (`SETTING_ROLE`)**
- 역할 목록 / 생성 / 수정 / 삭제
- 역할별 메뉴 권한 편집: `config/admin/menu.php`를 읽어 메뉴 트리를 그리고, 항목마다 조회/쓰기 체크박스
- 사용 중인 역할(소속 관리자 존재)은 삭제 불가

**(2) 관리자 관리 (`SETTING_ADMIN`)**
- 관리자 목록 (로그인ID, 이름, 역할, 상태, 최근 로그인)
- 관리자 생성 — 로그인ID, 이름, 이메일, 역할, 초기 비밀번호
- 관리자 정보 수정 — 이름/이메일/역할/상태
- 비밀번호 변경 — 상위 관리자에 의한 초기화, 그리고 본인 비밀번호 변경(현재 비밀번호 확인 필수)

**(3) 안전장치 — 삭제하지 말 것**

전부 구현·검증되어 있다. 하나라도 빼면 계정이 잠기거나 권한 상승이 가능해진다.

| 규칙 | 위치 |
|---|---|
| 마지막 `SUPER_ADMIN` 정지·역할 강등 불가 | `AdminAccountLibrary::ensureNotLastSuperAdmin()` |
| 본인 역할 변경 불가 (권한 상승 차단) | `AdminAccountLibrary::update()` |
| 본인 계정 정지 불가 | `AdminAccountLibrary::update()` / `suspend()` |
| 본인이 속한 역할의 권한 수정 불가 | `AdminRoleLibrary::updatePermissions()` |
| `SUPER_ADMIN` 역할 권한 편집·삭제 불가 | `AdminRoleLibrary` |
| 화면에서 `SUPER_ADMIN` 코드 생성 불가 | `AdminRoleLibrary::create()` |
| 소속 관리자 있는 역할 삭제 불가 | `AdminRoleLibrary::delete()` + DB `restrictOnDelete` |
| 쓰기 권한은 조회를 전제 | `AdminPermissionLibrary::syncRolePermissions()` |

- 관리자는 **물리 삭제하지 않고 `status = 'SUSPENDED'`** 로 만든다. 주문·상품 이력이 관리자를 참조하기 때문이다.
- 비밀번호는 모델 `$casts` 의 `'hashed'` 가 처리한다. 평문 저장/로깅/화면 표시 금지.
- 본인 비밀번호 변경은 `current_password:admin` 규칙으로 **현재 비밀번호를 확인**한다 (`AdminOwnPasswordRequest`).

## 8. 도메인 모델

**테이블·컬럼 전체 목록은 [`docs/tables.md`](docs/tables.md) 에 있다** (앱 테이블 32개, 실제 스키마에서 뽑음).
설계 배경과 "왜 그렇게 했는가" 는 [`docs/schema-draft.md`](docs/schema-draft.md).

여기에는 **테이블을 만들 때마다 다시 확인해야 할 원칙**만 둔다.

- **재고는 `product_variants` 한 곳에만 둔다.** `products` 에 재고 컬럼을 만들지 않는다 — 양쪽에 두면 반드시 어긋난다.
- **재고는 실물(`stock_quantity`)과 예약(`reserved_quantity`)으로 나뉜다.** 판매가능 = 실물 − 예약. 주문 생성 시 예약만 잡고, 실물은 결제 완료 시에만 깎는다. 두 컬럼의 역할을 섞지 않는다.
- **소프트 삭제(`deleted_at`)를 쓰지 않는다.** 대신 이력이 있는 행은 삭제를 막고 상태로 내린다 — 상품은 `HIDDEN`, 관리자는 `SUSPENDED`, 쿠폰·후기는 비활성/숨김.
- **`orders` / `order_items` 는 스냅샷이 원본이다.** 화면에 찍는 상품명·가격·주소는 FK 조인이 아니라 스냅샷 컬럼에서 읽는다. 조인하면 상품이 수정되는 순간 과거 주문서가 바뀐다.
- **확정 시점에 저장해야 하는 값들:** 쿠폰 `expires_at`(발급 시점), 주문 `shipping_fee`·`payment_due_at`(주문 시점), 결제 계좌 정보(요청 시점), 반품 환불 금액(승인 시점). 마스터 설정이 바뀌어도 과거 기록은 불변이다.
- **출고 전은 취소, 출고 후는 반품이다.** `OrderLibrary::cancel()` 은 `SHIPPING` 이후를 거부하고, `ReturnLibrary` 는 `SHIPPING`·`DELIVERED` 만 받는다. 두 경로가 겹치지 않게 유지할 것.
- **반품에서 재고와 돈은 `complete()` 한 곳에서만 움직인다.** 물건을 받기(`RECEIVED`) 전에 환불하면 회수하지 못한다. 승인은 금액을 계산해 스냅샷으로 굳힐 뿐 아무것도 옮기지 않는다.
- **비정규화 컬럼은 갱신 책임을 한 곳에 묶는다.** `products.thumbnail_path`(이미지 라이브러리), `products.review_count`·`rating_sum`(후기 라이브러리), `categories.depth`(카테고리 라이브러리). 이 컬럼들은 모델 `Fillable` 에서 빼두거나 라이브러리만 건드린다 — 폼에서 덮어쓰면 숫자가 조용히 어긋난다.
- **컬럼을 추가하면 모델 `Fillable` 과 `casts` 를 같이 본다.** 빠뜨리면 저장이 조용히 무시된다 (실제로 `orders.payment_due_at` 에서 겪었다).
- **회원 프로필 컬럼(전화번호·수신동의·마지막 로그인·배송지)이 이제 있다.** `users.phone` 은 자유롭게 수정, `marketing_*_agreed_at`·`last_login_at` 은 `Admin.last_login_at` 과 같은 규칙 — `Fillable` 밖, `forceFill` 로만 쓴다. 배송지는 `user_addresses` 로 별도 테이블(§8, `docs/tables.md` §11).

모든 상태 컬럼은 §6 규칙에 따라 대문자 문자열 + PHP enum 캐스팅.

## 9. 코딩 컨벤션

**PHP**
- `declare(strict_types=1);` 사용. 인자·반환 타입 항상 명시.
- 검증은 전부 FormRequest. 컨트롤러 안 `$request->validate()` 지양.
- **컨트롤러에서 쿼리 금지.** 조회·가공은 `app/Libraries/`에서 하고 반환값만 받는다 (§4.2).
- 상태값 문자열 리터럴을 코드에 직접 쓰지 않는다. 항상 enum 또는 config 경유 (§6).
- N+1 주의 — 목록 조회에는 라이브러리에서 `with()`를 명시한다.
- 커밋 전 `./vendor/bin/pint`.

**Vue / Inertia**
- Vue 3 `<script setup>` + Composition API만 사용.
- 페이지 컴포넌트는 `resources/js/Pages/` 하위에서 라우트와 1:1 대응.
- 서버 데이터는 Inertia props로 받는다. 페이지 안에서 `axios`로 별도 호출하지 않는다(자동완성/검색 등 부분 갱신 제외).
- props에 필요 없는 데이터를 넘기지 않는다. 목록에는 API Resource 등으로 필요한 필드만 실어 보낸다.

**공통**
- 사용자에게 보이는 문구는 한국어. 코드상의 식별자·상태값은 영문.
- 주석은 "왜"를 적는다. 코드가 이미 말하는 "무엇"은 적지 않는다.

## 10. Claude 작업 규칙

- **작업 시작 전 항상 `git fetch` / `git pull`로 최신 소스를 받는다.** (협업자가 있어 원격이 먼저 움직인다.)
- 스키마 변경, 패키지 추가, 라이브러리 도입은 **먼저 제안하고 승인을 받은 뒤** 진행한다.
- `migrate:fresh`처럼 데이터가 날아가는 명령은 실행 전에 확인받는다.
- 커밋/푸시는 사용자가 요청할 때만 한다.
- 요청하지 않은 리팩터링·범위 확장을 하지 않는다.
- 테스트가 실패하면 실패했다고 출력과 함께 그대로 보고한다. 넘어가지 않는다.
- §5(이식성) / §6(상태값) / §7(관리자 권한) 규칙을 어겨야만 하는 상황이 오면, 임의로 진행하지 말고 사용자에게 알린다.

## 11. 미결정 사항 (정해지면 이 문서를 갱신할 것)

- [x] ~~PHP 8.4 / Composer 로컬 설치~~ — 완료 (§3.1)
- [x] ~~Laravel + Inertia + Vue + Tailwind 스캐폴딩~~ — 완료. `/`, `/admin` 렌더 확인
- [x] ~~PostgreSQL 18 설치~~ — 완료. 18.6, 서비스 Running, 포트 5432 (§3.1)
- [x] ~~PostgreSQL 전환~~ — 완료. 마이그레이션 전량 무수정 통과, 동시성 검증까지 (§5.4)
- [x] ~~인증 방식 택일~~ — 고객 Fortify + 관리자 수동으로 확정, 구현 완료 (§12)
- [x] ~~`admins` / `admin_roles` / `admin_role_permissions` 마이그레이션 + `admin` 가드~~ — 완료. 로그인·권한 미들웨어 동작 확인
- [x] ~~관리자설정 화면 2종~~ — 권한설정 / 관리자관리 구현 완료. 안전장치 8종 검증 (§7.6)
- [x] ~~카테고리 관리~~ — 완료. 3단계 계층, 순환·깊이 초과·하위존재 삭제 차단 검증
- [x] ~~배송비설정~~ — 완료. 정책 CRUD + 배송비 계산. 기본 정책 보호 4종 검증
- [x] ~~상품 관리~~ — 완료. 조합형 옵션(최대 3단계), 조합별 재고/SKU, 삭제 가드 검증
- [x] ~~카테고리·배송비 정책 삭제 가드~~ — 완료
- [x] ~~나머지 업무 화면~~ — 완료. **`config/admin/menu.php` 의 메뉴 전부에 라우트가 붙어 비활성 메뉴가 없다**
- [x] ~~상품 이미지 업로드~~ — 완료. 대표 지정·순서·삭제, 상품 삭제 시 파일 정리 검증
- [x] ~~이미지 저장소 결정~~ — 로컬 `public` 디스크. `.env` 의 `SHOP_IMAGE_DISK=s3` 로 전환 (§3.2)
- [ ] **`verified` 미들웨어가 아직 아무 라우트에도 안 붙었다** — 이메일 인증은 동작하지만 미인증 회원도 주문·문의가 된다. 어디까지 막을지 정해야 한다(주문은 막고 조회는 허용 등)
- [ ] **자동화 테스트가 없다** — `tests/` 에 스캐폴딩 `ExampleTest` 2개뿐이다. 지금까지의 검증은 전부 스크래치패드 tinker 스크립트 + 브라우저였고 **저장소에 남지 않는다.** 재고 예약·쿠폰 계산·반품 금액처럼 조용히 틀리는 곳부터 Feature 테스트로 옮길 것
- [x] ~~관리자 대시보드·매출통계~~ — 완료. 처리대기·매출요약·일별그래프·인기상품·재고부족·최근주문·회원. **카드마다 `menu_code` 로 권한을 걸러 내린다**
- [x] ~~추천·최근 본 상품~~ — 완료. 함께 구매 기반 추천, 쿠키 기반 최근 본 상품, 다시 구매하기, 상품 상세 연관상품
- [x] ~~바로구매~~ — 완료. 장바구니를 거치지 않고 주문서 직행. `OrderLibrary::create()` 본체를 장바구니 주문과 공유
- [x] ~~바로구매만 로그인을 요구한다~~ — 해소됨. **구매 전체가 회원 전용으로 바뀌었다**(2026-08-25). 이제 바로구매·장바구니 경로가 같은 규칙이다
- [x] ~~통계 집계가 PHP 컬렉션 기반이다~~ — 완료. `daily_sales_stats` 일별 집계 테이블 + `shop:aggregate-sales`(5분마다 최근 3일, 새벽 4시 최근 90일). 매출 요약·일별 추이는 이 테이블만 읽는다
- [x] ~~상품별 통계가 실시간 집계다~~ — 완료. `daily_product_stats` 로 **조회 → 장바구니 → 구매 → 매출** 을 상품별로 접어둔다. 관리자 > 통계 > 상품분석(`STAT_PRODUCT`)
- [ ] **카테고리별 통계는 아직 실시간 집계다** — 상품별은 옮겼지만 카테고리는 '지금의 상품 정보' 기준이라(상품이 카테고리를 옮기면 과거 매출도 따라간다) 날짜별로 접으면 의미가 달라진다. 그대로 둘지 판단 필요
- [x] ~~통계 금액 설명~~ — 완료. '정산 내역' 블록으로 상품합계 − 할인 + 배송비 = 결제매출 − 환불 = 순매출을 화면에서 따라 읽게 함
- [x] ~~표시 시간대가 UTC 그대로다~~ — 완료. `App\Support\LocalTime` 이 라이브러리가 내보내는 모든 `*_at` 을 영업 시간대로 바꾼다(`Y-m-d H:i`). **저장은 UTC 그대로다** — `config('app.timezone')` 은 건드리지 않았다. 주문번호의 날짜도 KST 기준으로 바꿨다
- [x] ~~상품 상세 이미지~~ — 완료. `product_images.type` (GALLERY/DETAIL) 로 갈랐다. 관리자 수정 화면에 섹션 두 개
- [x] ~~상품 후기·평점~~ — 완료. **구매자만 작성**(주문 항목당 1건, 배송완료 필수), 숨김 처리, 판매자 답글, 평점 비정규화(`rating_sum`/`review_count`)
- [x] ~~상품 문의(Q&A)~~ — 완료. 상품에 붙는 공개 문의. 비밀글은 **서버가 내용을 지워 내린다**
- [x] ~~상담 채널 버튼~~ — 완료. `SHOP_SUPPORT_URL` 에 네이버 톡톡·카카오 채널 주소를 넣으면 전역 플로팅 버튼이 뜬다
- [ ] **후기에 사진을 못 올린다** — 포토후기는 전환율에 크게 영향을 준다. `product_images` 처럼 별도 테이블이 필요하고, 저장소·용량 정책을 같이 정해야 한다
- [x] ~~관리자가 처리할 일을 한눈에 못 본다~~ — 완료. **관리자 상단 알림**(🔔 배지 + 드롭다운). 대시보드 '처리 대기' 와 `AdminTodoLibrary` 를 공유하고, 미답변 상품문의·답글 없는 후기 항목을 새로 넣었다
- [ ] **고객에게 가는 알림은 여전히 없다** — 후기 답글·문의 답변·반품 상태가 바뀌어도 고객은 직접 화면을 봐야 안다. 관리자 쪽만 해결됐다. 메일·알림톡 채널이 필요하다
- [ ] **후기 정렬·필터가 없다** — 지금은 최신순 고정이다. '별점 높은 순', '사진 있는 후기만' 등은 후기가 쌓인 뒤에 판단
- [ ] **상담 채널 주소를 관리자 화면에서 못 바꾼다** — `.env` 로만 설정한다. 자주 바뀌지 않아 config 로 뒀는데, UI 가 필요하면 입금계좌설정처럼 작은 설정 페이지를 만들면 된다
- [x] ~~회원 프로필 확장(전화번호·마케팅 수신동의·마지막 로그인)~~ — 완료. `users.phone`/`marketing_*_agreed_at`/`last_login_at`. "내 정보" 화면(Fortify `PUT /user/profile-information` 그대로 재사용) + 가입 시 선택 입력 + 관리자 회원상세 읽기 전용 노출
- [x] ~~회원 배송지록(여러 배송지 저장)~~ — 완료. `user_addresses` 테이블, 기본 배송지 자동 승계(`bank_accounts` 와 같은 패턴), 주문서 자동 채움 + '이 배송지 저장' 체크박스
- [x] ~~간편로그인(카카오·네이버)~~ — 완료. `user_social_accounts` 테이블(회원 1 : 연동 N), Laravel Socialite + `socialiteproviders/kakao`·`socialiteproviders/naver`. `.env` 에 Client ID 없으면 로그인·가입 화면에 버튼이 안 뜬다(`HandleInertiaRequests` 의 `socialLogin` 공유 데이터). 제공자가 이메일을 안 주면(카카오) 추가입력 화면을 거친다 (§12.5)
- [ ] **카카오 이메일은 비즈 앱 전환 후에야 받을 수 있다** — 지금은 추가입력 화면으로 우회 중이다. 사업자등록 후 비즈 앱으로 전환하면 `account_email` 동의항목이 열리고, 그때부터 카카오도 네이버처럼 추가입력 없이 바로 가입된다(코드는 이미 그 경로를 타므로 수정 불필요)
- [ ] **로그인한 회원이 소셜 계정을 나중에 연동하는 화면이 없다** — `SocialLoginLibrary::link()` 는 이미 있고 소유권 검사도 하는데, "내 정보" 에 붙일 UI 를 안 만들었다. 지금은 이미 쓰는 이메일로 소셜 가입을 시도하면 "해당 계정으로 로그인한 뒤 연동해 주세요" 라고 안내만 하고 정작 연동할 곳이 없다
- [ ] **자주 쓰는 결제수단 저장이 안 된다** — 카드 결제 자체가 미구현(PG 연동 전)이라 저장할 대상이 없다. PG 붙일 때 같이 볼 것
- [ ] **비회원 주문조회 화면이 사실상 죽은 코드다** — 회원 전용 구매로 바뀐 뒤 새 비회원 주문이 안 생긴다. 현재 DB 의 비회원 주문은 0건이라 `/orders/lookup` 과 `orders.guest_password` 를 지워도 되지만, 되돌리기 어려워 일단 남겨뒀다(헤더 링크만 제거)
- [x] ~~좁은 화면에서 레이아웃이 깨진다~~ — 완료. 스토어 헤더 접이식 메뉴, 관리자 사이드바 드로어, 표 19개 가로 스크롤, flex/grid 축소 버그(`min-w-0`) 수정. 360/375/768px 전 페이지 가로 넘침 0
- [ ] **우편번호 검색이 없다** — 주문서에서 우편번호·주소를 손으로 친다. 다음(카카오) 우편번호 서비스는 외부 스크립트라 붙일지 결정 필요
- [ ] **관리자 재고 입고 화면이 없다** — `stock_movements` 에 `MANUAL_IN`/`ADJUST` 타입은 있는데 그걸 만드는 화면이 없다. 재고는 상품 수정에서 숫자를 덮어쓰는 방법뿐이라 이력이 안 남는다
- [ ] 관리자 권한을 역할(Role) 단위로만 둘지, 개별 관리자 예외 권한도 허용할지
- [ ] 관리자 활동 로그(감사 로그) 테이블 필요 여부
- [x] ~~스키마 결정 7개~~ — 전부 확정. `docs/schema-draft.md` §0. **구현 착수 가능**
- [x] ~~고객 상품 목록·상세 + 조합형 옵션 선택 UI~~ — 완료. 품절 조합 비활성 검증
- [x] ~~장바구니~~ — 완료. 비회원 세션 장바구니 + 로그인 시 병합 검증
- [x] ~~쿠폰~~ — 완료. 관리자 쿠폰관리 + 가입쿠폰 자동발급 + 고객 쿠폰함 검증
- [x] ~~`user_coupons.order_id` 컬럼~~ — 완료
- [x] ~~주문 + 재고 예약~~ — 완료. 비회원 주문·조회, 예약/판매확정/취소, 만료 스케줄러, **동시성 검증**
- [x] ~~`ProductLibrary::delete()` 주문 이력 가드~~ — 완료
- [x] ~~결제 — 무통장입금~~ — 완료. 계좌설정·입금안내·관리자 수동 확인. 결제 기한을 수단별로 분리
- [ ] **PG 연동 미구현** — 카드·가상계좌. `PaymentLibrary` 에 메서드를 더하는 방식으로 붙인다
- [ ] **입금 안내가 실제로 발송되지 않는다** — `DepositNotifier` 가 로그에만 남긴다. 카카오 알림톡·SMS 사업자 계약 후 채널 추가 필요
- [x] ~~배송관리~~ — 완료. 송장 등록·출고·배송완료·출고취소, 고객 주문내역에 배송조회 노출
- [x] ~~관리자 주문관리(`ORDER_LIST`)~~ — 완료. 전체 상태 조회·상세·강제취소. 취소 권한 경계 검증
- [x] ~~반품·교환~~ — 완료. 부분 반품, 교환, 귀책별 배송비, 쿠폰 안분, 재판매 여부 분리 (`schema-draft.md` §11)
- [ ] **실제 환불 송금 수단이 없다** — `order_returns.refund_amount` 는 **계산해서 기록만 한다.** 돈은 관리자가 은행 앱에서 직접 보낸다. PG 를 붙이면 `ReturnLibrary::complete()` 에 결제 취소·부분취소 호출을 넣는다
- [ ] **반품 상태가 바뀌어도 고객에게 알림이 가지 않는다** — 문의 답변과 같은 상태
- [ ] **반품 배송비가 상품값보다 크면 환불액을 0 으로 막는다** — 추가 청구 수단이 없기 때문. 부족분은 관리자 메모로 관리 중이며, PG 연동 시 재검토
- [x] ~~회원관리(`MEMBER_LIST`)~~ — 완료. 상세 모달(정보·주문·결제·1:1문의·쿠폰·메모), 1:1 문의 기능 포함
- [ ] **문의 답변 시 고객에게 알림이 가지 않는다** — 고객이 직접 화면을 봐야 안다. 메일·알림톡 연동 필요
- [ ] **택배사 조회 URL 검증 필요** — `config/shop.php` 의 `shipping.carriers[].tracking_url`. 택배사 사정으로 바뀌므로 실제 송장번호로 열어 확인할 것
- [x] ~~예약 만료 스케줄러 동작 검증~~ — 완료. `schedule:work` 로 자동 실행·취소·예약해제 실측 (§3)
- [ ] **운영 배포 시 크론/작업 스케줄러 등록** — 1분마다 `schedule:run`. 안 돌면 재고가 조용히 잠긴다
- [ ] 결제 연동 대상 (토스페이먼츠 / 포트원 등) — 로컬 단계에서는 목(mock) 결제로 진행
- [ ] 관리자 영역을 같은 앱에 둘지, 서브도메인으로 분리할지

## 12. 인증 구조 (2026-08-20 결정 · 구현 완료)

**고객 = Fortify, 관리자 = 수동.** 두 축이 완전히 분리되어 있다.

| | 고객 | 관리자 |
|---|---|---|
| 테이블 | `users` | `admins` |
| 가드 | `web` | `admin` |
| 인증 구현 | **Laravel Fortify 1.38** | **직접 구현** |
| 로그인 식별자 | 이메일 | `login_id` |
| 회원가입 | 공개 | 없음 (상위 관리자가 생성) |
| 이메일 인증 | **사용** | 미사용 |

### 12.1 왜 이렇게 갈랐나

- **Fortify는 단일 가드다.** `config/fortify.php`가 `'guard' => 'web'`, `'passwords' => 'users'` 단일값이라 관리자 가드를 겸할 수 없다. 그래서 관리자는 어차피 수동이다.
- Breeze는 후보가 아니다. README 첫 줄이 "This starter kit is for Laravel 11.x and prior"이다.
- 공식 Vue 스타터킷(`laravel/vue-starter-kit`)은 스켈레톤 **앱**이라 기존 프로젝트에 얹을 수 없어 제외했다. 그쪽도 내부적으로 Fortify를 쓴다.

### 12.2 고객 (Fortify)

- 설정은 `config/fortify.php`, 화면 연결은 `app/Providers/FortifyServiceProvider.php`의 `Fortify::loginView()` 등에서 Inertia 페이지로 매핑한다.
- 활성 기능: 회원가입, 비밀번호 재설정, **이메일 인증**, 프로필 수정, 비밀번호 변경.
- **2FA와 패스키는 껐다.** 켜려면 `config/fortify.php`에서 주석을 풀고 해당 마이그레이션을 다시 `vendor:publish` 한 뒤 화면을 만들어야 한다.
- `User`는 `MustVerifyEmail`을 구현한다. 인증이 필요한 라우트에는 `verified` 미들웨어를 붙인다.
- 로컬은 `MAIL_MAILER=log`다. **인증 메일은 `storage/logs/laravel.log`에서 확인**한다.
- 화면: `resources/js/Pages/Store/Auth/`

### 12.3 관리자 (수동)

- 로그인: `app/Http/Controllers/Admin/Auth/LoginController.php`
- 권한 판정: `app/Libraries/Admin/AdminPermissionLibrary.php`
- 메뉴 트리: `app/Libraries/Admin/AdminMenuLibrary.php`
- 미들웨어: `admin.permission:{MENU_CODE},{READ|WRITE}` (`app/Http/Middleware/AdminPermission.php`)
- 화면: `resources/js/Pages/Admin/`, 레이아웃 `AdminLayout.vue`

수동 구현이므로 아래를 직접 챙긴다. **삭제하거나 우회하지 말 것:**

- 로그인 성공 시 `session()->regenerate()` — 세션 고정 공격 방어
- 로그아웃 시 `session()->invalidate()` + `regenerateToken()`
- `RateLimiter` 로 `login_id|IP` 기준 5회 제한
- 실패 메시지는 ID 오류와 비밀번호 오류를 **구분하지 않는다** — 계정 열거 방지
- `SUSPENDED` 계정은 인증에 성공해도 로그인시키지 않고, 세션 유지 중에도 미들웨어에서 차단한다

### 12.4 시드 계정 (로컬 전용)

`php artisan db:seed` 로 생성된다. 시더는 `updateOrCreate` 라 재실행해도 안전하다.

| 로그인 ID | 비밀번호 | 역할 | 권한 |
|---|---|---|---|
| `superadmin` | `admin-local-1234` | `SUPER_ADMIN` | 전부 통과 |
| `manager1` | `manager-local-1234` | `MANAGER` | 관리자설정 제외 |

**로컬 개발용이다. 운영 전 반드시 교체한다.**

`manager1` 은 권한 제한이 실제로 걸리는지 확인하는 용도다. 최고관리자만으로는 권한 검사를 검증할 수 없다.

### 12.5 간편로그인 (카카오·네이버)

고객(Fortify) 쪽에만 있다. 관리자는 대상이 아니다.

- `Laravel\Socialite` + `socialiteproviders/kakao`·`socialiteproviders/naver` (SocialiteProviders 패밀리는 자기 서비스 프로바이더가 없어서, 드라이버 등록은 `app/Listeners/ExpandSocialiteProviders.php` 가 `SocialiteWasCalled` 이벤트를 듣고 해준다 — 이것도 `app/Listeners` 자동 등록에 얹힌다, §12.2 와 같은 `Event::listen()` 금지 규칙).
- 라우트: `GET /login/{provider}/redirect`, `GET /login/{provider}/callback` (`provider` 는 `kakao|naver` 로만 열려 있다). 제공자가 이메일을 안 줄 때 거치는 추가입력 화면은 `GET|POST /login/social/complete` — **여기엔 `{provider}` 가 없다.** 소셜 신원은 URL·폼이 아니라 세션(`social_signup`)에서만 꺼낸다. 컨트롤러는 `App\Http\Controllers\Store\SocialLoginController`.
- 매칭·가입 로직은 `App\Libraries\Member\SocialLoginLibrary` — 회원 판정 규칙은 [`docs/tables.md`](docs/tables.md) §13 참고.
- **이메일의 출처에 따라 메서드가 갈린다. 합치지 말 것.** `linkWithVerifiedEmail()`(제공자가 검증 — 같은 이메일의 기존 회원에 연동 추가 가능) / `createWithUnverifiedEmail()`(본인이 직접 입력 — **기존 회원에 절대 붙이지 않는다**, 붙이면 남의 이메일을 적는 것만으로 계정 탈취가 된다). 자세한 이유는 `docs/worklog.md` 의 간편로그인 절.
- **카카오는 개인 개발자 앱에서 이메일을 못 받는다** (`account_email` 동의항목이 "권한 없음", 비즈 앱 전환 = 사업자등록 필요). 그래서 추가입력 화면이 필요하다. 네이버는 이메일을 정상적으로 준다.
- Client ID/Secret 은 `.env` 의 `KAKAO_CLIENT_ID`/`NAVER_CLIENT_ID` 등. **비어 있으면 로그인·가입 화면에 버튼이 아예 안 뜬다** (`HandleInertiaRequests` 의 `socialLogin` 공유 데이터, `SHOP_SUPPORT_URL`/`supportChannel` 과 같은 패턴). 실제 로그인을 테스트하려면 카카오·네이버 개발자센터에 앱을 등록하고 Redirect URI 로 `{APP_URL}/login/{kakao|naver}/callback` 을 등록해야 한다.
- 로그인 성공은 `Auth::guard('web')->login($user)` 로 끝난다 — 이후는 비밀번호 로그인과 완전히 같은 경로다(`RecordUserLogin` 리스너도 그대로 적용되어 `last_login_at` 이 찍힌다).
