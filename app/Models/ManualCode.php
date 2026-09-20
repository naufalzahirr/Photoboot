<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ManualCode extends Model {
    protected $guarded = [];
    protected function casts(): array {
        return ['package'=>'array','distributed_at'=>'datetime','redeemed_at'=>'datetime','fulfillment_reserved_at'=>'datetime'];
    }
}
