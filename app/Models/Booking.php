<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'reference', 'branch_id', 'customer_id', 'service', 'booking_date',
        'booking_time', 'customer_name', 'mobile', 'status', 'source', 'slot_active',
    ];

    protected function casts(): array
    {
        return ['booking_date' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    protected static function booted(): void
    {
        // Gives each booking its reference, like EG-00001
        static::created(function (Booking $booking) {
            $booking->updateQuietly([
                'reference' => 'EG-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
