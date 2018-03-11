<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService\Http\Socket;

use Ratchet\ConnectionInterface;
use Illuminate\Support\Facades\App;
use Moontius\SSOService\CSSO;
use Moontius\SSOService\Models\SsoSession;

//use Moontius\SSOService\SsoFacade;
//export PHP_IDE_CONFIG="serverName=netbeans-xdebug"
//php artisan ratchet:serve --driver=WsServer

/**
 * Description of SocketService
 *
 * @author root
 */
class SocketService extends \Askedio\LaravelRatchet\RatchetWsServer {

    public $sess = [];

    public function onOpen(ConnectionInterface $conn) {
        $this->conn = $conn;
        $this->attach()->throttle()->limit();
    }

    public function onMessage(ConnectionInterface $conn, $input) {
//        $remote_country_code_iso2 = $conn->httpRequest->getHeader('GEOIP_COUNTRY_CODE');
        $result = '{}';
        $csso = App::make(CSSO::class);
        $data = json_decode($input, true);
        $request = isset($data['request']) ? $data['request'] : 'init';
        $reqparams = isset($data['params']) ? $data['params'] : [];

        switch ($request) {
            case 'sessionToken':
                $locale = isset($reqparams['locale']) ? $reqparams['locale'] : 'NL';
                $device_info = isset($reqparams['deviceInfo']) ? $reqparams['deviceInfo'] : '';
                $sso_session = $this->create_sso_session(null);
                $token = $sso_session->sesskey;
                $this->sess[$token] = $conn->resourceId;
                $result = '{"signUpSessionId":"' . $token . '","otpTimeOut": 90}';
                break;
            case 'reconnect':
                $token = isset($data['token']) ? $data['token'] : '';
                $resourceId = isset($this->sess[$token]) ? $this->sess[$token] : '';
                if (isset($resourceId)) {
                    try {
                        //create new token and renew token expire time
                        $sso_session = $this->create_sso_session($token);
                        $result = '{"session": "accepted","token":"' . $sso_session->sesskey . '"}';
                    } catch (Exception $exc) {
                        $result = '{"session": "invalid_token"}';
                    }
                } else {
                    $result = '{"session": "invalid_token"}';
                }
                break;
            case 'whats.my.info':
                $result = '{ "country_code":"GB",
		  "Country_prefix":"44",
		  "country_name":"United Kingdom",
		  "external_ip":"90.152.66.68"
		}';
                break;
            case 'callback.otp':
                $number = $reqparams['myNumber'];
                $result = '{"status":"calling"}';
                $result = '{"status":"switchTextOTP"}';
                $result = '{"status":"failed", "errorMsg":"The number is not a valid Mobile number"}';
                break;
            case 'callback.cancel':
                $result = '{"status":"cancelDone"}';
                break;
            case 'text.otp.form':
                $params['verify_state'] = 'mobile_form';
                try {
                    if ($csso->validate($params)) {
                        $result = $this->do_after_validate($csso);
                    }
                    $result = '{"status":"success","params":"' . $params . '"}';
                } catch (Exception $ex) {
                    $result = '{"status":"failed","params":"' . $params . '","error":"' . $ex->getMessage() . '","code":"' . $ex->getCode() . '"}';
                }
                break;
            case 'text.otp.submit':
                $number = $reqparams['myNumber'];
                $params['verify_state'] = 'mobile_submit';
                $params['mobile'] = $number;
                try {
                    if ($csso->validate($params)) {
                        $result = $this->do_after_validate($csso);
                    }
                    $result = '{"status":"success","params":"' . $params . '"}';
                } catch (Exception $ex) {
                    $result = '{"status":"failed","params":"' . $params . '","error":"' . $ex->getMessage() . '","code":"' . $ex->getCode() . '"}';
                }
                break;
            case 'verify.otp.form':
                $params['verify_state'] = 'verify_form';
                try {
                    if ($csso->validate($params)) {
                        $result = $this->do_after_validate($csso);
                    }
                    $result = '{"status":"success","params":"' . $params . '"}';
                } catch (Exception $ex) {
                    $result = '{"status":"failed","params":"' . $params . '","error":"' . $ex->getMessage() . '","code":"' . $ex->getCode() . '"}';
                }
                break;
            case 'verify.otp.submit':
                $params['verify_state'] = 'verify_submit';
                $params['method'] = isset($reqparams['otpMethod']) ? $reqparams['otpMethod'] : 'callbackOTP';
                $params['v_code'] = isset($reqparams['otpValue']) ? $reqparams['otpValue'] : '';
                try {
                    if ($csso->validate($params)) {
                        $result = $this->do_after_validate($csso);
                    }
                    $result = '{"status":"success","params":"' . $params . '"}';
                } catch (Exception $ex) {
                    $result = '{"status":"failed","params":"' . $params . '","error":"' . $ex->getMessage() . '","code":"' . $ex->getCode() . '"}';
                }
                break;
            case 'sso.next':
                $next = $csso->getCurrentStep();
                $url = $this->find_url($next);
                $result = '{"next":"' . $next . '","url":"' . $url . '"}';
                break;
            default :
                break;
        }
        $this->send($conn, $result);
        $this->console->comment("++++++++++++++");
    }

    public function do_after_validate(CSSO $csso) {
        $next = $csso->getCurrentStep();
        $preference = $csso->getViewParams(array($next));
        $user = $csso->getUser($next);
        $date = [
            'user' => $user,
            'preference' => $preference,
            'current_state' => $next
        ];
        return json_encode($date);
    }

    function create_sso_session($token) {
        $sso_session = new SsoSession();
        if (isset($token)) {
            $sso_session = SsoSession::where('sesskey', $token)->first();
            if (!isset($sso_session)) {
                throw new \Moontius\SSOService\SSOException('Invalid token ' . $token);
            }
        }
        $sess_key = $this->generateToken();
        $sso_session->sesskey = $sess_key;
        $expiry = strtotime('+1days');
        $sso_session->expiry = $expiry;
        $sso_session->save();
        return $sso_session;
    }

    protected function generateToken() {
        return hash_hmac('sha256', \Illuminate\Support\Str::random(40), config('app.key'));
    }

    public function find_url($next) {
        switch ($next) {
            case 'SocialSignOn':
                return "/ws/sso/social/{provider}";
            case 'VerifiedMobilePhone':
                return "/ws/sso/mobile";
            default:
        }
        return "";
    }

}
