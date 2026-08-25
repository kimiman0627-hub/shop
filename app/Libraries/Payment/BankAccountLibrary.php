<?php

declare(strict_types=1);

namespace App\Libraries\Payment;

use App\Exceptions\DomainRuleException;
use App\Models\BankAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 무통장입금 계좌 관리.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class BankAccountLibrary
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getList(): Collection
    {
        return BankAccount::query()
            ->ordered()
            ->get()
            ->map(fn (BankAccount $a) => [
                'id' => $a->id,
                'bank_name' => $a->bank_name,
                'account_number' => $a->account_number,
                'holder_name' => $a->holder_name,
                'is_default' => $a->is_default,
                'is_active' => $a->is_active,
                'sort_order' => $a->sort_order,
            ]);
    }

    /**
     * 안내에 쓸 기본 계좌.
     *
     * 없으면 즉시 터뜨린다. 계좌 없이 주문을 받으면 고객이 돈 보낼 곳을 모른다.
     */
    public function defaultAccount(): BankAccount
    {
        $account = BankAccount::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            throw new DomainRuleException(
                '입금 계좌가 설정되지 않았습니다. 관리자에게 문의하세요.',
            );
        }

        return $account;
    }

    public function hasUsableAccount(): bool
    {
        return BankAccount::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BankAccount
    {
        return DB::transaction(function () use ($data) {
            $account = BankAccount::query()->create($data);

            // 첫 계좌는 자동으로 기본이 된다. 기본이 없는 상태를 만들지 않는다.
            if ($account->is_default || BankAccount::query()->count() === 1) {
                $this->makeSoleDefault($account);
            }

            return $account;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $accountId, array $data): BankAccount
    {
        $account = BankAccount::query()->findOrFail($accountId);

        if ($account->is_default && ! $data['is_active']) {
            throw new DomainRuleException(
                '기본 계좌는 비활성화할 수 없습니다. 다른 계좌를 기본으로 지정하세요.',
                'is_active',
            );
        }

        if ($account->is_default && ! $data['is_default']) {
            throw new DomainRuleException(
                '기본 계좌는 해제할 수 없습니다. 다른 계좌를 기본으로 지정하세요.',
                'is_default',
            );
        }

        return DB::transaction(function () use ($account, $data) {
            $account->update($data);

            if ($account->is_default) {
                $this->makeSoleDefault($account);
            }

            return $account;
        });
    }

    public function delete(int $accountId): void
    {
        $account = BankAccount::query()->findOrFail($accountId);

        if ($account->is_default) {
            throw new DomainRuleException(
                '기본 계좌는 삭제할 수 없습니다. 다른 계좌를 기본으로 지정한 뒤 삭제하세요.',
            );
        }

        // payments 는 계좌 정보를 스냅샷으로 갖고 있으므로,
        // 계좌를 지워도 과거 안내 내용은 그대로 남는다.
        $account->delete();
    }

    private function makeSoleDefault(BankAccount $account): void
    {
        BankAccount::query()
            ->whereKeyNot($account->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (! $account->is_default) {
            $account->forceFill(['is_default' => true])->save();
        }
    }
}
