<?php

namespace Moontius\SSOService\Models;

use Illuminate\Database\Eloquent\Model;

class SsoSignInOtp extends Model {

	protected $table = 'sso_signin_otp';
	public $timestamps = false;

	//    const UPDATED_AT = 'last_update';
	public function setUpdatedAtAttribute($value) {
		// to Disable updated_at
	}

}
