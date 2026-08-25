<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * 도메인 규칙 위반.
 *
 * 라이브러리가 규칙을 판단해 이 예외를 던지고, 컨트롤러가 응답 형태를 정한다.
 * 라이브러리는 HTTP 를 모른다 (CLAUDE.md §4.2).
 */
class DomainRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $field = 'general',
    ) {
        parent::__construct($message);
    }
}
