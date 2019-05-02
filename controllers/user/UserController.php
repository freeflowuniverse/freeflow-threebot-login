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
use humhub\modules\user\models\Auth;
use humhub\modules\threebot_login\authclient\ThreebotAuth;

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
                'hash' => Yii::$app->session -> get("authState")
            )));

        $response = $client->dispatch($client -> getRequest());

        //  the POST was successful
        if ($response->isSuccess()) {
            // LOGGED IN
            if(!Yii::$app->user->isGuest){
                $user = Yii::$app->user;

                $connection = Auth::findOne(['source_id' => $username, 'source' => '3bot']);

                // There's 3bot connection existing but linked to different account
                // Give error in this case
                if ($connection != null && $connection -> user_id != $user -> id){
                    throw new \yii\web\HttpException(403, "3-Bot account used is linked to another user!");
                }

                $authUser = Auth::findOne(['user_id' => Yii::$app->user -> id, 'source' => '3bot']);

                // Connect
                if ($authUser == null){
                    $newUSer = new Auth();
                    $newUSer -> source_id = $username;
                    $newUSer -> source = '3bot';
                    $newUSer -> user_id = $user -> id;
                    $newUSer -> save();
                }else if ($authUser -> source_id != $username){
                    // User was connected with another 3bot account
                    // delete old one and reconnect
                    $authUser -> delete();
                    $newUSer = new Auth();
                    $newUSer -> source_id = $username;
                    $newUSer -> source = '3bot';
                    $newUSer -> user_id = $user -> id;
                    $newUSer -> save();
                }

            }else { // NOT LOGGED IN
                $authUser = Auth::findOne(['source_id' => $username, 'source' => '3bot']);
                $user = User::findOne(['username' => $username]); // @TODO: Check for email as well otherwise, we will link user to wrong account

                // create user if does not exist [New user]
                if ($user == null && $authUser == null){


                }else if ($user != null && $authUser == null){ // user already exists with same name - connect
                    $newUSer = new Auth();
                    $newUSer -> source_id = $username;
                    $newUSer -> source = '3bot';
                    $newUSer -> user_id = $user -> id;
                    $newUSer -> save();
                }else if ($user == null && $authUser != null){ // user was connected, but usernames are different
                    $user = User::findOne(['id' => $authUser -> user_id]);
                }

                Yii::$app->user->login($user);
            }

            Yii::$app->user->setCurrentAuthClient(new ThreebotAuth());
            $this->redirect(Yii::$app->urlManager->createAbsoluteUrl(['/']));
        }else{
            throw new \yii\web\HttpException($response -> getStatusCode(), $response -> getBody());
        }
    }
}