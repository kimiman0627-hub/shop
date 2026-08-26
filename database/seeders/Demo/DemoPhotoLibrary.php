<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Http\UploadedFile;

/**
 * 데모용 실사 상품 사진.
 *
 * `database/seeders/Demo/photos/` 에 미리 받아둔 파일을 쓴다.
 * **시드할 때 인터넷을 타지 않는다** — 네트워크가 없거나 원본 서비스가 바뀌어도
 * 시딩이 실패하면 안 된다. 사진은 저장소에 함께 들어 있다.
 *
 * 출처·라이선스는 같은 폴더의 `CREDITS.json` / `README.md` 참고 (Unsplash, 무료 라이선스).
 *
 * **파일이 없으면 예전 방식(GD 로 그린 그러데이션)으로 물러난다.** 사진을 지웠거나
 * 새 상품을 추가했는데 사진을 아직 안 넣은 경우에도 시딩은 끝까지 돌아야 한다.
 */
class DemoPhotoLibrary
{
    private const DIR = __DIR__.'/photos';

    /**
     * 상품 사진 한 장.
     *
     * @param  string  $code  상품 코드('TEE 01'). 파일명은 `tee-01-{n}.jpg` 다.
     * @param  int  $index  같은 상품의 몇 번째 컷인지 (0부터)
     * @param  string  $from  폴백용 그러데이션 시작색
     * @param  string  $to  폴백용 그러데이션 끝색
     */
    public static function make(string $code, int $index, string $from, string $to): UploadedFile
    {
        $path = self::path($code, $index);

        if ($path === null) {
            // 사진이 없다 — 그려서라도 채운다. 시딩을 멈추지 않는다.
            return DemoImageFactory::make($code, $from, $to, $index);
        }

        /*
         * 원본을 그대로 넘기지 않고 임시 파일로 복사한다.
         * 업로드 처리가 파일을 옮기거나 지울 수 있어서, 저장소의 원본이 사라지면
         * 다음 `shop:demo --fresh` 부터 사진이 없는 채로 돌게 된다.
         */
        $tmp = tempnam(sys_get_temp_dir(), 'demophoto').'.jpg';
        copy($path, $tmp);

        // test: true — 실제 HTTP 업로드가 아니므로 검증을 건너뛴다.
        return new UploadedFile($tmp, basename($path), 'image/jpeg', null, true);
    }

    /** 사진 파일 경로. 없으면 null. */
    private static function path(string $code, int $index): ?string
    {
        $slug = str_replace(' ', '-', mb_strtolower($code));
        $path = self::DIR."/{$slug}-{$index}.jpg";

        return is_file($path) ? $path : null;
    }

    /** 사진이 몇 장이나 준비돼 있는지. 시더가 안내 문구에 쓴다. */
    public static function count(): int
    {
        return count(glob(self::DIR.'/*.jpg') ?: []);
    }
}
