# .claude/skills

## ui-ux-pro-max

UI/UX 설계·검수용 참고 데이터와 검색 스크립트.

- **출처:** https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
- **라이선스:** MIT
- **설치:** `npx ui-ux-pro-max-cli init --ai claude` (2026-08-26 기준 CLI 2.15.0)
- **직접 만든 것이 아니다.** 위 저장소의 산출물을 그대로 두고 필요한 부분만 남겼다.

### 설치본에서 덜어낸 것

CLI 는 스킬 **7개**를 한꺼번에 깐다. 이 프로젝트에 필요한 하나만 남기고 지웠다:

| 지운 것 | 이유 |
|---|---|
| `ui-styling` | shadcn/React 전제다. 이 프로젝트는 Vue 라 **잘못된 방향을 제시한다** |
| `design`, `banner-design`, `brand` | 로고·CIP·배너·브랜드 아이덴티티. 이 프로젝트에서 안 쓴다 |
| `design-system`, `slides` | 디자인 토큰 생성·발표자료. 안 쓴다 |
| `*/scripts/tests/` | 패키지 자체 테스트. 사용에 필요 없다 |

**스킬 설명문은 항상 컨텍스트에 올라간다.** 무관한 스킬이 많으면 도움이 아니라 방해다.

### 이 프로젝트에서 쓰는 방식

**"디자인 시스템 생성기" 로 쓰지 않는다.** 화면 45개의 톤이 이미 잡혀 있어서,
새 시스템을 생성하면 갈아엎는 일이 된다. 실제로 이 프로젝트 조건으로 돌려보면
민트 배경 + 에메랄드 + 오렌지에 SaaS 랜딩 구조를 제안하는데, 한국 패션몰과 거리가 멀다.
(한글 폰트도 다루지 않는다.)

**검수 체크리스트와 UX 근거 조회용으로 쓴다.** 실제로 값을 한 예:
이 저장소에서 이모지를 아이콘으로 쓰던 3곳과 `cursor-pointer` 누락을 잡아냈다.

```bash
# UX 가이드라인 조회
python .claude/skills/ui-ux-pro-max/scripts/search.py "mobile bottom navigation" --domain ux

# 스택별 관례 (vue / laravel 데이터가 있다)
python .claude/skills/ui-ux-pro-max/scripts/search.py "form validation" --stack vue

# 검수 체크리스트가 딸려 나온다
python .claude/skills/ui-ux-pro-max/scripts/search.py "<주제>" --design-system --project-name shop --stack vue
```

로컬 CSV 검색만 한다 — 네트워크 호출은 없다(설치 시 확인).

**충돌 시 `CLAUDE.md` 가 우선이다.** 이 스킬이 제안하는 내용이 프로젝트 규칙과
다르면 프로젝트 규칙을 따른다.

---

## launch.json

옵시디언·스킬과 무관한 파일이다. 개발 서버 실행 설정(`php artisan dev`, 포트 8000).
