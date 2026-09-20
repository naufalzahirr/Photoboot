<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PhotoDownload extends Model {
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['expires_at'=>'immutable_datetime']; }
    public function photoPath(): string { return 'photo-downloads/'.$this->id.'.jpg'; }
}
