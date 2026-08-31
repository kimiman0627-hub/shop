/**
 * 화면 전반에서 되풀이되는 클래스 묶음.
 *
 * 같은 문자열이 24곳에 흩어져 있었고, 그 사이에 `mt-1 w-full` 이 붙거나 안 붙는
 * 미세한 차이가 섞여 있었다 — 어느 쪽이 의도한 것인지 알 수 없는 상태였다.
 * 여기서 한 번만 정하고 필요한 변형은 뒤에 덧붙인다.
 *
 * **관리자와 고객은 배경이 반대다.** 관리자는 어두운 화면(`neutral-950`),
 * 고객은 흰 화면이라 테두리·포커스 색이 다르다. 하나로 합치지 않는다.
 */

/** 관리자(어두운 배경) 입력 */
export const adminInput =
    'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';

/** 관리자 — 라벨 아래 한 줄 전체를 차지하는 입력 */
export const adminField = `mt-1 w-full ${adminInput}`;

/** 고객(흰 배경) 입력 */
export const storeInput =
    'rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900';

/** 고객 — 라벨 아래 한 줄 전체를 차지하는 입력 */
export const storeField = `mt-1 w-full ${storeInput}`;

/** 관리자 카드(어두운 배경 위의 한 구역) */
export const adminCard = 'rounded-xl border border-neutral-800 bg-neutral-900/30 p-5';

/** 고객 카드 */
export const storeCard = 'rounded-lg border border-neutral-200 p-5';
