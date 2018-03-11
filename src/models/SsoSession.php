<?php

namespace Moontius\SSOService\Models;

use Illuminate\Database\Eloquent\Model;

class SsoSession extends Model {

    protected $table = 'sso_sessions';
    public $timestamps = false;
    
    protected $primaryKey = 'sesskey';
    
    public $incrementing=false;
}
