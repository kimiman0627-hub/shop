<?php

declare(strict_types=1);

namespace App\Libraries\Shipping;

use App\Enums\Product\ShippingFeeType;
use App\Exceptions\DomainRuleException;
use App\Models\Product;
use App\Models\ShippingPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 배송비 정책 관리 + 배송비 계산 (docs/schema-draft.md §6).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 * 덕분에 주문 라이브러리, 아티즌 커맨드, 테스트 어디서든 같은 계산을 쓴다.
 */
class ShippingPolicyLibrary
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getList(): Collection
    {
        return ShippingPolicy::query()
            ->ordered()
            ->get()
            ->map(fn (ShippingPolicy $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'base_fee' => $p->base_fee,
                'free_threshold' => $p->free_threshold,
                'is_default' => $p->is_default,
                'is_active' => $p->is_active,
            ]);
    }

    /**
     * 상품 등록 화면의 정책 선택용. 비활성 정책은 새로 고를 수 없다.
     *
     * @return Collection<int, array{id: int, name: string, is_default: bool}>
     */
    public function selectableOptions(): Collection
    {
        return ShippingPolicy::query()
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->map(fn (ShippingPolicy $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'is_default' => $p->is_default,
            ]);
    }

    /**
     * 기본 정책. 상품이 정책을 고르지 않았을 때 쓴다.
     *
     * 없으면 배송비 계산이 불가능하므로 즉시 터뜨린다. 조용히 0원 처리하면
     * 주문 금액이 틀린 채로 쌓인다.
     */
    public function defaultPolicy(): ShippingPolicy
    {
        $policy = ShippingPolicy::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($policy === null) {
            throw new DomainRuleException(
                '활성화된 기본 배송비 정책이 없습니다. 배송비설정에서 기본 정책을 지정하세요.',
            );
        }

        return $policy;
    }

    /**
     * @param  array{name: string, base_fee: int, free_threshold: int|null, is_default: bool, is_active: bool}  $data
     */
    public function create(array $data): ShippingPolicy
    {
        $this->assertThresholdSane($data);

        return DB::transaction(function () use ($data) {
            $policy = ShippingPolicy::query()->create($data);

            if ($policy->is_default) {
                $this->makeSoleDefault($policy);
            }

            return $policy;
        });
    }

    /**
     * @param  array{name: string, base_fee: int, free_threshold: int|null, is_default: bool, is_active: bool}  $data
     */
    public function update(int $policyId, array $data): ShippingPolicy
    {
        $policy = ShippingPolicy::query()->findOrFail($policyId);

        $this->assertThresholdSane($data);

        // 기본 정책을 비활성화하면 defaultPolicy() 가 터진다. 미리 막는다.
        if ($policy->is_default && ! $data['is_active']) {
            throw new DomainRuleException(
                '기본 정책은 비활성화할 수 없습니다. 다른 정책을 기본으로 지정한 뒤 다시 시도하세요.',
                'is_active',
            );
        }

        // 기본 해제는 단독으로 허용하지 않는다 — 기본이 0개가 되기 때문이다.
        // 기본을 옮기려면 다른 정책을 기본으로 지정한다(그쪽에서 자동으로 해제된다).
        if ($policy->is_default && ! $data['is_default']) {
            throw new DomainRuleException(
                '기본 정책은 해제할 수 없습니다. 다른 정책을 기본으로 지정하세요.',
                'is_default',
            );
        }

        return DB::transaction(function () use ($policy, $data) {
            $policy->update($data);

            if ($policy->is_default) {
                $this->makeSoleDefault($policy);
            }

            return $policy;
        });
    }

    public function delete(int $policyId): void
    {
        $policy = ShippingPolicy::query()->findOrFail($policyId);

        if ($policy->is_default) {
            throw new DomainRuleException(
                '기본 정책은 삭제할 수 없습니다. 다른 정책을 기본으로 지정한 뒤 삭제하세요.',
            );
        }

        // DB 는 restrictOnDelete 로 막혀 있지만, 여기서 먼저 걸러 사람이 읽을 메시지를 준다.
        $productCount = Product::query()->where('shipping_policy_id', $policy->id)->count();

        if ($productCount > 0) {
            throw new DomainRuleException(
                "이 정책을 쓰는 상품이 {$productCount}개 있습니다. 먼저 다른 정책으로 바꾸세요.",
            );
        }

        $policy->delete();
    }

    // ------------------------------------------------------------ 배송비 계산

    /**
     * 주문의 배송비를 계산한다 (docs/schema-draft.md §6.2).
     *
     * 주문당 배송비는 1건이다. 정책이 다른 상품이 섞이면 base_fee 가 가장 큰 정책을 적용한다.
     *
     * @param  list<array{shipping_fee_type: ShippingFeeType|string, subtotal: int, shipping_policy_id: int|null}>  $lines
     */
    public function calculateFee(array $lines): int
    {
        $paid = array_values(array_filter(
            $lines,
            fn (array $l) => $this->feeType($l['shipping_fee_type'])->isPaid(),
        ));

        // 전부 무료배송 상품이면 배송비가 없다.
        if ($paid === []) {
            return 0;
        }

        $policy = $this->applicablePolicy($paid);

        // 기준은 '유료배송 상품 합계' 지 '주문 총액' 이 아니다.
        // 무료배송 상품이 임계금액을 채워주면 정책 의도와 어긋나기 때문이다 (§6.2).
        $paidTotal = array_sum(array_column($paid, 'subtotal'));

        if ($policy->hasFreeThreshold() && $paidTotal >= $policy->free_threshold) {
            return 0;
        }

        return $policy->base_fee;
    }

    /**
     * 적용 정책 = 유료배송 상품들의 정책 중 base_fee 가 가장 큰 것.
     *
     * @param  list<array{shipping_policy_id: int|null}>  $paidLines
     */
    private function applicablePolicy(array $paidLines): ShippingPolicy
    {
        $ids = array_values(array_unique(array_filter(
            array_column($paidLines, 'shipping_policy_id'),
            fn ($id) => $id !== null,
        )));

        $policies = $ids === []
            ? collect()
            : ShippingPolicy::query()->whereIn('id', $ids)->get();

        // 정책을 안 고른 상품이 하나라도 있으면 기본 정책도 후보에 넣는다.
        if (count($ids) < count($paidLines)) {
            $policies = $policies->push($this->defaultPolicy());
        }

        // 참조된 정책이 지워졌거나 전부 null 인 경우의 안전망.
        if ($policies->isEmpty()) {
            return $this->defaultPolicy();
        }

        return $policies->sortByDesc('base_fee')->first();
    }

    private function feeType(ShippingFeeType|string $value): ShippingFeeType
    {
        return $value instanceof ShippingFeeType
            ? $value
            : ShippingFeeType::from($value);
    }

    // ------------------------------------------------------------------ 내부

    /**
     * 기본 정책은 항상 정확히 1개다. 이 정책만 남기고 나머지를 내린다.
     */
    private function makeSoleDefault(ShippingPolicy $policy): void
    {
        ShippingPolicy::query()
            ->whereKeyNot($policy->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * @param  array{base_fee: int, free_threshold: int|null}  $data
     */
    private function assertThresholdSane(array $data): void
    {
        // 임계금액 0 은 '항상 무료' 라서 base_fee 가 의미를 잃는다.
        // 무료배송을 원하면 base_fee 를 0 으로 두는 편이 의도가 분명하다.
        if ($data['free_threshold'] !== null && $data['free_threshold'] <= 0) {
            throw new DomainRuleException(
                '무료배송 기준금액은 1원 이상이어야 합니다. 항상 무료로 하려면 배송비를 0원으로 설정하세요.',
                'free_threshold',
            );
        }
    }
}
