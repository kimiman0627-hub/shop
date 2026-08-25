<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 배송 (docs/schema-draft.md §6.3).
 */
#[Fillable([
    'order_id', 'carrier', 'tracking_no', 'status',
    'shipped_at', 'delivered_at', 'shipped_by_admin_id', 'memo',
])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'shipped_by_admin_id');
    }

    public function carrierName(): ?string
    {
        if ($this->carrier === null) {
            return null;
        }

        return config("shop.shipping.carriers.{$this->carrier}.name", $this->carrier);
    }

    /**
     * 배송조회 URL. 택배사가 URL 을 안 주거나 송장이 없으면 null 이다.
     */
    public function trackingUrl(): ?string
    {
        if ($this->carrier === null || $this->tracking_no === null) {
            return null;
        }

        $template = config("shop.shipping.carriers.{$this->carrier}.tracking_url");

        if (! is_string($template) || $template === '') {
            return null;
        }

        return str_replace('{no}', rawurlencode($this->tracking_no), $template);
    }
}
