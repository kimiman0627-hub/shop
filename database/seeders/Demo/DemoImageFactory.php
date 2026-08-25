<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Http\UploadedFile;

/**
 * 데모용 상품 이미지 생성기.
 *
 * 실제 상품 사진이 없으므로 GD 로 그린다. 단색 빈 이미지를 넣으면
 * 목록이 전부 회색 네모가 되어 화면 감각을 볼 수 없다 —
 * 상품마다 다른 색과 코드를 넣어 그리드에서 구분되게 한다.
 *
 * **데모 전용이다.** 앱 코드에서 쓰지 않는다.
 */
class DemoImageFactory
{
    private const WIDTH = 900;

    private const HEIGHT = 900;

    /**
     * 상품 이미지 한 장.
     *
     * @param  string  $label  이미지에 찍을 코드. **ASCII 만 된다** —
     *                         GD 내장 폰트는 한글을 못 그린다(TTF 없이는).
     * @param  string  $from  배경 그러데이션 시작색 (#RRGGBB)
     * @param  string  $to  배경 그러데이션 끝색
     * @param  int  $variant  같은 상품의 몇 번째 컷인지. 도형 배치가 달라진다.
     */
    public static function make(string $label, string $from, string $to, int $variant = 0): UploadedFile
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        self::drawGradient($image, $from, $to);
        self::drawShapes($image, $variant);
        self::drawLabel($image, $label);

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        // test: true — 실제 HTTP 업로드가 아니므로 검증을 건너뛴다.
        return new UploadedFile($path, "{$label}.png", 'image/png', null, true);
    }

    private static function drawGradient(\GdImage $image, string $from, string $to): void
    {
        [$r1, $g1, $b1] = self::rgb($from);
        // 끝색을 한 번 더 눌러준다. 카탈로그 색이 전부 파스텔이라
        // 그대로 쓰면 목록에서 타일이 서로 구분되지 않는다.
        [$r2, $g2, $b2] = self::darken(self::rgb($to), 0.62);

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;

            $color = imagecolorallocate(
                $image,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t),
            );

            imageline($image, 0, $y, self::WIDTH, $y, $color);
        }
    }

    /** 컷마다 다른 도형. 같은 상품의 이미지 2장이 구분되게 한다. */
    private static function drawShapes(\GdImage $image, int $variant): void
    {
        imagealphablending($image, true);

        $light = imagecolorallocatealpha($image, 255, 255, 255, 105);
        $dark = imagecolorallocatealpha($image, 0, 0, 0, 115);

        if ($variant % 2 === 0) {
            imagefilledellipse($image, 450, 400, 520, 520, $light);
            imagefilledellipse($image, 640, 660, 260, 260, $dark);
        } else {
            imagefilledrectangle($image, 180, 190, 720, 610, $light);
            imagefilledellipse($image, 250, 690, 300, 300, $dark);
        }
    }

    /**
     * 코드 라벨. GD 내장 폰트는 작아서 작은 이미지에 그린 뒤 확대한다 —
     * 픽셀이 뭉개지지만 플레이스홀더라는 게 오히려 분명해진다.
     */
    private static function drawLabel(\GdImage $image, string $label): void
    {
        $scale = 6;
        $charWidth = imagefontwidth(5);
        $charHeight = imagefontheight(5);

        $textWidth = $charWidth * strlen($label);

        // 라벨 뒤에 어두운 판을 깐다. 배경색이 밝은 상품에서는
        // 흰 글씨만으로는 아예 안 보인다.
        $plateWidth = $textWidth * $scale + 60;
        $plateHeight = $charHeight * $scale + 36;

        imagefilledrectangle(
            $image,
            (int) ((self::WIDTH - $plateWidth) / 2),
            (int) ((self::HEIGHT - $plateHeight) / 2),
            (int) ((self::WIDTH + $plateWidth) / 2),
            (int) ((self::HEIGHT + $plateHeight) / 2),
            imagecolorallocatealpha($image, 0, 0, 0, 88),
        );

        $canvas = imagecreatetruecolor($textWidth, $charHeight);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagestring($canvas, 5, 0, 0, $label, imagecolorallocate($canvas, 255, 255, 255));

        imagecopyresampled(
            $image,
            $canvas,
            (int) ((self::WIDTH - $textWidth * $scale) / 2),
            (int) ((self::HEIGHT - $charHeight * $scale) / 2),
            0, 0,
            $textWidth * $scale, $charHeight * $scale,
            $textWidth, $charHeight,
        );

        imagedestroy($canvas);
    }

    /**
     * @param  array{int, int, int}  $rgb
     * @return array{int, int, int}
     */
    private static function darken(array $rgb, float $factor): array
    {
        return array_map(fn (int $c) => (int) round($c * $factor), $rgb);
    }

    /**
     * @return array{int, int, int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
