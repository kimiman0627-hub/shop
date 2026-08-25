<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Enums\Product\QuestionStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 상품 문의 Q&A (docs/schema-draft.md §12.3).
 *
 * 1:1 문의(`InquiryLibrary`)와 나눈 이유: 붙는 대상과 공개 범위가 다르다.
 * 1:1 문의는 **주문**에 붙고 비공개, 상품 문의는 **상품**에 붙고 기본 공개다.
 * 공개 글이라 다른 고객이 읽고 같은 질문을 안 하게 되는 게 존재 이유다.
 *
 * 후기와 달리 **구매를 요구하지 않는다.** 사기 전에 묻는 게 문의다.
 */
class ProductQuestionLibrary
{
    /**
     * 문의 등록.
     *
     * @param  array{content: string, is_secret?: bool}  $form
     */
    public function create(int $productId, int $userId, array $form): ProductQuestion
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw new DomainRuleException('상품 정보를 찾을 수 없습니다.');
        }

        $content = trim($form['content']);

        if ($content === '') {
            throw new DomainRuleException('문의 내용을 입력해 주세요.', 'content');
        }

        return ProductQuestion::query()->create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'content' => $content,
            'is_secret' => (bool) ($form['is_secret'] ?? false),
            'status' => QuestionStatus::PENDING,
        ]);
    }

    /** 작성자가 지운다. 답변이 달린 뒤에는 못 지운다 — 답변이 고아가 된다. */
    public function deleteByOwner(int $questionId, int $userId): void
    {
        $question = ProductQuestion::query()->findOrFail($questionId);

        if ($question->user_id !== $userId) {
            throw new DomainRuleException('본인이 쓴 문의만 삭제할 수 있습니다.');
        }

        if ($question->status === QuestionStatus::ANSWERED) {
            throw new DomainRuleException('답변이 등록된 문의는 삭제할 수 없습니다.');
        }

        $question->delete();
    }

    public function answer(int $questionId, int $adminId, string $answer): ProductQuestion
    {
        $answer = trim($answer);

        if ($answer === '') {
            throw new DomainRuleException('답변 내용을 입력해 주세요.', 'answer');
        }

        $question = ProductQuestion::query()->findOrFail($questionId);

        $question->forceFill([
            'answer' => $answer,
            'answered_by_admin_id' => $adminId,
            'answered_at' => now(),
            'status' => QuestionStatus::ANSWERED,
        ])->save();

        return $question;
    }

    /* ------------------------------------------------------------------ 조회 */

    /**
     * 상품 상세에 붙는 문의 목록.
     *
     * **비밀글은 서버에서 내용을 지워 내린다.** 화면에서 가리면 개발자도구로 다 보인다.
     *
     * @param  int|null  $viewerId  지금 보고 있는 회원. 비로그인이면 null
     */
    public function forProduct(int $productId, ?int $viewerId, int $perPage = 5): LengthAwarePaginator
    {
        return ProductQuestion::query()
            ->where('product_id', $productId)
            ->with(['user:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage, pageName: 'qna_page')
            ->withQueryString()
            ->through(fn (ProductQuestion $q) => $this->present($q, $viewerId));
    }

    /**
     * 관리자 문의 목록.
     *
     * @param  array{status?: string|null, keyword?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return ProductQuestion::query()
            ->with(['user:id,name', 'product:id,name', 'answeredBy:id,name'])
            ->when(
                ($filters['status'] ?? null) !== null && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when($keyword !== '', fn ($q) => $q
                ->where(fn ($w) => $w
                    ->whereLike('content', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereHas('product', fn ($p) => $p
                        ->whereLike('name', '%'.$keyword.'%', caseSensitive: false))))
            // 답변 대기가 위로 오게. 관리자는 처리할 것부터 본다.
            ->orderByDesc('id')
            ->paginate(config('shop.per_page.admin'))
            ->withQueryString()
            ->through(fn (ProductQuestion $q) => [
                'id' => $q->id,
                'product_id' => $q->product_id,
                'product_name' => $q->product?->name,
                'author' => $q->user?->name,
                // 관리자는 비밀글도 본다. 답변하려면 내용을 봐야 한다.
                'content' => $q->content,
                'is_secret' => $q->is_secret,
                'status' => $q->status->value,
                'status_label' => $q->status->label(),
                'answer' => $q->answer,
                'answered_by' => $q->answeredBy?->name,
                'answered_at' => $q->answered_at?->toDateTimeString(),
                'created_at' => $q->created_at->toDateTimeString(),
            ]);
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        $rows = ProductQuestion::query()->get(['status'])->countBy(fn (ProductQuestion $q) => $q->status->value);

        $counts = [];

        foreach (QuestionStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ProductQuestion $question, ?int $viewerId): array
    {
        $visible = $question->isVisibleTo($viewerId);

        return [
            'id' => $question->id,
            'author' => $this->maskName($question->user?->name),
            'is_secret' => $question->is_secret,
            'is_mine' => $viewerId !== null && $viewerId === $question->user_id,

            // 볼 수 없으면 내용 자체를 안 내린다.
            'content' => $visible ? $question->content : null,
            'answer' => $visible ? $question->answer : null,

            'status' => $question->status->value,
            'status_label' => $question->status->label(),
            'answered_at' => $question->answered_at?->toDateString(),
            'created_at' => $question->created_at->toDateString(),

            // 답변 전이고 본인 글일 때만 삭제 버튼이 뜬다.
            'is_deletable' => $viewerId !== null
                && $viewerId === $question->user_id
                && $question->status === QuestionStatus::PENDING,
        ];
    }

    /** 후기와 같은 규칙으로 가린다. */
    private function maskName(?string $name): string
    {
        $name = trim((string) $name);
        $length = mb_strlen($name);

        return match (true) {
            $length === 0 => '알 수 없음',
            $length === 1 => $name,
            $length === 2 => mb_substr($name, 0, 1).'*',
            default => mb_substr($name, 0, 1)
                .str_repeat('*', $length - 2)
                .mb_substr($name, -1),
        };
    }
}
