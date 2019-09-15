<?php

namespace humhub\modules\threebot_login\authclient;

use Yii;
use yii\authclient\OAuth2;
use humhub\modules\user\models\Auth;


class ThreebotAuth extends OAuth2
{
    public $authUrl    = 'https://login.threefold.me';
    public $keyPair = ''; // Freeflow Crypto key Pair (will be set automatically from settings)

    /**
     * Composes user authorization URL.
     * @param array $params additional auth GET params.
     * @return string authorization URL.
     *
     *
     * This method alters the default oauth redirecturl /user/auth/external
     * to another custom one /3bot/login provided by login function in controller
     *
     */

    public function buildAuthUrl(array $params = [])
    {
        $params = $_GET;
        unset($params['authclient']);
        $publicKey = sodium_crypto_sign_publickey(base64_decode($this -> keyPair));
        $redirectUrl = "/threebot_login/login";
        $defaultParams = [
            'appid' => 'freeflowpages.com',
            'scope' => '{"user": true, "email": true}',
            'publickey' => base64_encode(sodium_crypto_sign_ed25519_pk_to_curve25519($publicKey)),
            'redirecturl' => $this->composeUrl($redirectUrl, array_merge($params)),
        ];

        if ($this->validateAuthState) {
            $authState = $this->generateAuthState();
            Yii::$app->session -> set("authState", $authState);
            $defaultParams['state'] = $authState;
        }

        return $this->composeUrl($this->authUrl, array_merge($defaultParams));
    }

    protected function initUserAttributes() {}

    protected function defaultName() {
        return '3bot';
    }

    protected function defaultTitle() {
        return '3bot';
    }
}

