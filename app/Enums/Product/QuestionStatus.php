<?php

declare(strict_types=1);

namespace App\Enums\Product;

/**
 * 상품 문의(Q&A) 상태. 값은 대문자 (CLAUDE.md §6.1).
 *
 * 1:1 문의(app/Enums/Support/InquiryStatus)와 별개다.
 * 그쪽은 주문에 붙고 비공개, 이쪽은 상품에 붙고 기본 공개다.
 */
enum QuestionStatus: string
{
    case PENDING = 'PENDING';
    case ANSWERED = 'ANSWERED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '답변대기',
            self::ANSWERED => '답변완료',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
