<?php

namespace Moontius\SSOService\Models;

use Illuminate\Database\Eloquent\Model;

class SsoClientsPhoneOtp extends Model {

	protected $table = 'sso_clients_phones_otp';
	public $timestamps = false;

	//    const UPDATED_AT = 'last_update';
	public function setUpdatedAtAttribute($value) {
		// to Disable updated_at
	}

}
