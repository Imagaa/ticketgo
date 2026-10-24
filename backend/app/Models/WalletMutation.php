<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WalletMutation extends Model {
    protected $fillable = ['wallet_id', 'amount', 'type', 'description'];

    public function wallet() {
        return $this->belongsTo(Wallet::class);
    }
}