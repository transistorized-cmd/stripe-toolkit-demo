<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property string $product_name
 * @property int $amount
 * @property string $currency
 * @property string|null $customer_email
 * @property string $status
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'product_name',
        'amount',
        'currency',
        'customer_email',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'int',
        'paid_at' => 'datetime',
    ];

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markPaid(string $paymentIntentId, ?string $customerEmail = null): void
    {
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'stripe_payment_intent_id' => $paymentIntentId,
            'customer_email' => $customerEmail ?? $this->customer_email,
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 2).' '.strtoupper($this->currency);
    }
}
