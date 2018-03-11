<?php

namespace Moontius\SSOService\Models;

use Illuminate\Database\Eloquent\Model;

class XeroBank extends Model {

    protected $table = 'xero_banks';
    public $timestamps = false;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function bank() {
        return $this->belongsTo(Bank::class);
    }
}
