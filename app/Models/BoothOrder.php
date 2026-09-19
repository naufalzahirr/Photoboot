<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BoothOrder extends Model {
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['amount'=>'integer','package'=>'array','charge_attempted'=>'boolean','expires_at'=>'datetime','verified_at'=>'datetime']; }
}
