<?php

declare(strict_types=1);

namespace App\Libraries\Member;

use App\Exceptions\DomainRuleException;
use App\Models\UserAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 회원 배송지록.
 *
 * `BankAccountLibrary` 와 같은 "기본 하나만 유지" 패턴을 쓰되, 성격이 다르다 —
 * 계좌는 **전역**에 기본이 반드시 있어야 하지만, 배송지는 **회원별**이고
 * 하나도 없는 상태가 정상이다(가입 직후). 그래서 "기본이 없으면 예외" 를 하지 않는다.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class AddressLibrary
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listFor(int $userId): Collection
    {
        return UserAddress::query()
            ->where('user_id', $userId)
            ->ordered()
            ->get()
            ->map(fn (UserAddress $a) => $this->present($a));
    }

    /** 주문서 자동 채움에 쓰는 기본 배송지. 없으면 null — 그냥 직접 입력하면 된다. */
    public function defaultFor(int $userId): ?array
    {
        $address = UserAddress::query()
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->first();

        return $address === null ? null : $this->present($address);
    }

    /**
     * @param  array{label?: string|null, receiver_name: string, receiver_phone: string, postcode: string, address1: string, address2?: string|null, is_default?: bool}  $data
     */
    public function create(int $userId, array $data): UserAddress
    {
        $count = UserAddress::query()->where('user_id', $userId)->count();
        $max = (int) config('shop.address.max_per_user');

        if ($count >= $max) {
            throw new DomainRuleException("배송지는 최대 {$max}개까지 저장할 수 있습니다.", 'general');
        }

        return DB::transaction(function () use ($userId, $data, $count) {
            $address = UserAddress::query()->create([
                ...$this->attributes($data),
                'user_id' => $userId,
            ]);

            // 첫 배송지는 자동으로 기본이 된다. 물어보나 마나인 선택이다.
            if ($address->is_default || $count === 0) {
                $this->makeSoleDefault($userId, $address);
            }

            return $address;
        });
    }

    /**
     * @param  array{label?: string|null, receiver_name: string, receiver_phone: string, postcode: string, address1: string, address2?: string|null, is_default?: bool}  $data
     */
    public function update(int $userId, int $addressId, array $data): UserAddress
    {
        $address = $this->owned($userId, $addressId);

        return DB::transaction(function () use ($userId, $address, $data) {
            $address->update($this->attributes($data));

            if ($address->is_default) {
                $this->makeSoleDefault($userId, $address);
            }

            return $address->fresh();
        });
    }

    public function delete(int $userId, int $addressId): void
    {
        $address = $this->owned($userId, $addressId);
        $wasDefault = $address->is_default;

        $address->delete();

        // 기본이던 걸 지웠으면, 남은 것 중 가장 최근 걸 기본으로 승계한다.
        // 하나도 안 남았으면 그냥 둔다 — "기본 배송지 없음" 도 정상 상태다.
        if ($wasDefault) {
            $next = UserAddress::query()->where('user_id', $userId)->orderByDesc('id')->first();
            $next?->forceFill(['is_default' => true])->save();
        }
    }

    public function setDefault(int $userId, int $addressId): void
    {
        $address = $this->owned($userId, $addressId);
        $this->makeSoleDefault($userId, $address);
    }

    /** 소유권 확인. 남의 배송지를 id 로 바로 못 만지게 한다. */
    private function owned(int $userId, int $addressId): UserAddress
    {
        return UserAddress::query()
            ->where('user_id', $userId)
            ->findOr($addressId, fn () => throw new DomainRuleException('본인의 배송지만 관리할 수 있습니다.'));
    }

    private function makeSoleDefault(int $userId, UserAddress $address): void
    {
        UserAddress::query()
            ->where('user_id', $userId)
            ->whereKeyNot($address->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (! $address->is_default) {
            $address->forceFill(['is_default' => true])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'label' => $data['label'] !== '' ? ($data['label'] ?? null) : null,
            'receiver_name' => $data['receiver_name'],
            'receiver_phone' => $data['receiver_phone'],
            'postcode' => $data['postcode'],
            'address1' => $data['address1'],
            'address2' => $data['address2'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(UserAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'receiver_name' => $address->receiver_name,
            'receiver_phone' => $address->receiver_phone,
            'postcode' => $address->postcode,
            'address1' => $address->address1,
            'address2' => $address->address2,
            'is_default' => $address->is_default,
        ];
    }
}
