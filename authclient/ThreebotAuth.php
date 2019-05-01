<?php

namespace humhub\modules\threebot_login\authclient;

use Yii;
use yii\authclient\OAuth2;
use humhub\modules\user\models\Auth;


class ThreebotAuth extends OAuth2
{
    public $authUrl    = 'https://login.threefold.me';

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

        $defaultParams = [
            'redirecturl' => Yii::$app->urlManager->createAbsoluteUrl(['/']) . "threebot_login/login"
        ];

        if ($this->validateAuthState) {
            $authState = $this->generateAuthState();
            Yii::$app->session -> set("authState", $authState);
            $defaultParams['state'] = $authState;
        }

        return $this->composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    protected function initUserAttributes() {}

    protected function defaultName() {
        return '3bot';
    }

    protected function defaultTitle() {
        return '3bot';
    }
}

