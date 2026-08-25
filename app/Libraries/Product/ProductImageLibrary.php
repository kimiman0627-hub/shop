<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Enums\Product\ProductImageType;
use App\Exceptions\DomainRuleException;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 상품 이미지 (docs/schema-draft.md §2.4).
 *
 * 저장소는 config('shop.image.disk') 가 정한다. 로컬은 'public'
 * (storage/app/public + public/storage 링크), 운영은 .env 에서 SHOP_IMAGE_DISK=s3.
 *
 * DB 에는 상대 경로만 저장하고 URL 은 Storage::url() 로 만든다.
 * 그래서 디스크를 바꿔도 데이터 마이그레이션이 필요 없다 — 파일만 옮기면 된다.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class ProductImageLibrary
{
    /**
     * 저장소는 설정에서 읽는다. 하드코딩하면 S3 전환 때 코드를 뒤져야 한다.
     * 로컬은 'public', 운영은 .env 의 SHOP_IMAGE_DISK=s3.
     */
    private function disk(): string
    {
        return config('shop.image.disk');
    }

    /**
     * 용도별 장수 제한. 무제한이면 목록·정렬 화면이 무너진다.
     *
     * 갤러리와 상세 이미지는 성격이 달라 한도도 다르다 (ProductImageType::maxCount).
     */
    private function maxPerProduct(ProductImageType $type): int
    {
        return $type->maxCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * 용도별 이미지 목록. 기본은 갤러리다 —
     * 기존 호출부(상품 상세·수정 화면)가 갤러리를 기대하고 있다.
     *
     * @return list<array<string, mixed>>
     */
    public function getListFor(int $productId, ProductImageType $type = ProductImageType::GALLERY): array
    {
        return ProductImage::query()
            ->where('product_id', $productId)
            ->where('type', $type->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductImage $i) => [
                'id' => $i->id,
                'path' => $i->path,
                'url' => Storage::disk($this->disk())->url($i->path),
                'alt' => $i->alt,
                'sort_order' => $i->sort_order,
                'is_primary' => $i->is_primary,
            ])
            ->all();
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function upload(int $productId, array $files, ProductImageType $type = ProductImageType::GALLERY): void
    {
        $product = Product::query()->findOrFail($productId);

        // 장수 제한은 **용도별로** 센다. 상세 이미지를 올렸다고 갤러리가 막히면 안 된다.
        $current = ProductImage::query()
            ->where('product_id', $product->id)
            ->where('type', $type->value)
            ->count();

        $max = $this->maxPerProduct($type);

        if ($current + count($files) > $max) {
            throw new DomainRuleException(
                "{$type->label()}는 상품당 최대 {$max}장입니다. (현재 {$current}장)",
                'images',
            );
        }

        $nextOrder = (int) ProductImage::query()
            ->where('product_id', $product->id)
            ->where('type', $type->value)
            ->max('sort_order');

        DB::transaction(function () use ($product, $files, $current, $type, &$nextOrder) {
            $isFirst = $current === 0;

            foreach ($files as $index => $file) {
                $path = $file->storeAs(
                    "products/{$product->id}",
                    Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
                    $this->disk(),
                );

                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'alt' => $product->name,
                    'sort_order' => ++$nextOrder,
                    'type' => $type,
                    // 첫 이미지는 자동으로 대표가 된다. 대표가 없는 상태를 만들지 않는다.
                    // 상세 이미지에는 대표 개념이 없다.
                    'is_primary' => $type->hasPrimary() && $isFirst && $index === 0,
                ]);
            }

            $this->syncThumbnail($product);
        });
    }

    public function delete(int $productId, int $imageId): void
    {
        $image = ProductImage::query()
            ->where('product_id', $productId)
            ->findOrFail($imageId);

        DB::transaction(function () use ($image, $productId) {
            $wasPrimary = $image->is_primary;

            // DB 행보다 파일을 먼저 지우면, 실패 시 고아 행이 남는다.
            // 반대로 하면 고아 파일이 남는데 그쪽이 덜 위험하다(화면은 정상 동작).
            $image->delete();
            Storage::disk($this->disk())->delete($image->path);

            if ($wasPrimary) {
                // 대표가 사라졌으면 남은 것 중 첫 장을 승계시킨다.
                $next = ProductImage::query()
                    ->where('product_id', $productId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                $next?->update(['is_primary' => true]);
            }

            $this->syncThumbnail(Product::query()->findOrFail($productId));
        });
    }

    /**
     * 순서와 대표 이미지를 한 번에 저장한다.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(
        int $productId,
        array $orderedIds,
        ?int $primaryId,
        ProductImageType $type = ProductImageType::GALLERY,
    ): void {
        /*
         * **같은 용도 안에서만 정렬한다.**
         * 상세 이미지 id 를 갤러리 정렬에 섞어 보내면 상세 이미지가 대표(썸네일)가 되어
         * 목록에 엉뚱한 그림이 뜬다. 소유권과 용도를 함께 확인한다.
         */
        $owned = ProductImage::query()
            ->where('product_id', $productId)
            ->where('type', $type->value)
            ->pluck('id')
            ->all();

        foreach ($orderedIds as $id) {
            if (! in_array($id, $owned, true)) {
                throw new DomainRuleException('이 상품의 이미지가 아닙니다.', 'images');
            }
        }

        if ($primaryId !== null && ! in_array($primaryId, $owned, true)) {
            throw new DomainRuleException('이 상품의 이미지가 아닙니다.', 'images');
        }

        DB::transaction(function () use ($productId, $orderedIds, $primaryId, $type) {
            foreach ($orderedIds as $index => $id) {
                $attributes = ['sort_order' => $index + 1];

                // 상세 이미지에는 대표 개념이 없다. is_primary 를 건드리지 않는다.
                if ($type->hasPrimary()) {
                    $attributes['is_primary'] = $id === $primaryId;
                }

                ProductImage::query()->whereKey($id)->update($attributes);
            }

            // 대표를 지정하지 않았으면 첫 장이 대표다.
            if ($type->hasPrimary() && $primaryId === null && $orderedIds !== []) {
                ProductImage::query()->whereKey($orderedIds[0])->update(['is_primary' => true]);
            }

            $this->syncThumbnail(Product::query()->findOrFail($productId));
        });
    }

    /**
     * 상품 삭제 시 파일까지 지운다.
     *
     * DB 는 cascade 로 행만 지운다. 파일은 아무도 안 지우므로 여기서 처리한다.
     * 이걸 빼면 storage 가 조용히 부풀어 오른다.
     */
    public function deleteAllFor(int $productId): void
    {
        Storage::disk($this->disk())->deleteDirectory("products/{$productId}");
    }

    /**
     * 대표 이미지를 products.thumbnail_path 에 반영한다.
     *
     * 목록에서 이미지 테이블을 조인하지 않으려고 두는 비정규화 컬럼이다.
     */
    private function syncThumbnail(Product $product): void
    {
        $primary = ProductImage::query()
            ->where('product_id', $product->id)
            ->where('is_primary', true)
            ->first();

        $product->update(['thumbnail_path' => $primary?->path]);
    }
}
