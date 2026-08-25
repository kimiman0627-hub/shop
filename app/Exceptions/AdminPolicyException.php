<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * 관리자 안전장치 위반 (CLAUDE.md §7.6).
 *
 * DomainRuleException 의 특수형이다. 컨트롤러는 둘 중 아무거나 잡아도 되지만,
 * 관리자 안전장치임을 드러내려면 이 타입을 쓴다.
 */
class AdminPolicyException extends DomainRuleException {}
