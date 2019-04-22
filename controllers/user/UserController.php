<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\threebot_login \controllers\user;

use humhub\components\Controller;
use humhub\modules\rest\definitions\UserDefinitions;
use humhub\modules\user\models\Password;
use humhub\modules\user\models\Profile;
use humhub\modules\user\models\User;

use Yii;
use yii\web\HttpException;
use Zend\Http\Request;
use Zend\Http\Client;
use yii\helpers\Json;

/**
 * Class AccountController
 */
class UserController extends Controller
{

    public function actionAuth()
        {

            $baseString = get_class($this) . '-' . time();
            if (Yii::$app->has('session')) {
                $baseString .= '-' . Yii::$app->session->getId();
            }
            $state =  hash('sha256', uniqid($baseString, true));

            Yii::$app->session -> set("state", $state);
            $returnUrl =  Yii::$app->urlManager -> createUrl("threebot_login/user/user/login", array());
            $this->redirect("https://login.threefold.me?state=" . $state . "&redirecturl=" . Yii::$app->urlManager->createAbsoluteUrl(['/']) . "threebot_login/user/user/login");
        }

    public function actionLogin()
    {
        $signedhash = Yii::$app->request -> get('signedhash');
        $username = Yii::$app->request -> get('username');


        $client = new Client();
        $client -> setUri('https://login.threefold.me/api/verify');
        $client -> setHeaders(array('Content-Type' => 'application/json'));
        $client->setMethod('POST');
        $client -> setRawBody(Json::encode(array(
                'username' => $username,
                'signedhash' =>  $signedhash,
                'hash' => Yii::$app->session -> get("state")
            )));

        $response = $client->dispatch($client -> getRequest());

        //  the POST was successful
        if ($response->isSuccess()) {
            $user = User::findOne(['username' => $username]);
            if ($user === null) {
                throw new \yii\web\HttpException(401, 'User Not found!');
            }

            Yii::$app->user->login($user);
            $this->redirect(Yii::$app->urlManager->createAbsoluteUrl(['/']));
        }else{
            throw new \yii\web\HttpException($response -> getStatusCode(), $response -> getBody());
        }


    }

    protected function returnError($statusCode = 400, $message = 'Invalid request', $additional = [])
    {
        Yii::$app->response->statusCode = $statusCode;
        return array_merge(['code' => $statusCode, 'message' => $message], $additional);
    }
    protected function returnSuccess($message = 'Request successful', $statusCode = 200, $additional = [])
    {
        Yii::$app->response->statusCode = $statusCode;
        return array_merge(['code' => $statusCode, 'message' => $message], $additional);
    }


}