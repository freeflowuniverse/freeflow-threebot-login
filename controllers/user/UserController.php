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
use humhub\modules\user\models\User;
use humhub\modules\user\models\GroupUser;
use humhub\modules\user\models\Profile;
use humhub\modules\user\models\Auth;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\threebot_login\authclient\ThreebotAuth;
use humhub\modules\user\models\Invite;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
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
        $data = Json::decode(Yii::$app->request -> get('data'));
        $inviteToken = Yii::$app->request -> get('token');

        $userInvite = null;

        if ($inviteToken != null){
            $userInvite = Invite::findOne(['token' => $inviteToken]);
            if (!$userInvite) {
                throw new HttpException(404, 'Invalid registration token!');
            }
        }

        if($signedhash == null || $username == null || $data == null){
            throw new \yii\web\HttpException(400, 'Bad request');
        }

        $config = require('/var/www/html/humhub/protected/config/common.php');
        $keyPair = $config['components']['authClientCollection']['clients']['3bot']['keyPair'];

        // Get user public key

        $client = new Client();
        // For staging
        #$client -> setUri('https://login.staging.jimber.org/api/users/' . $username);
	    $client -> setUri('https://login.threefold.me/api/users/' . $username);
        $client -> setHeaders(array('Content-Type' => 'application/json'));
        $client->setMethod('GET');
        $response = $client->dispatch($client -> getRequest());

        //  the POST was successful
        if (!$response->isSuccess()) {
            throw new \yii\web\HttpException($response -> getStatusCode(), 'Error while Getting user public key');
        }

        $nonce = base64_decode($data['nonce']);
        $cipherText = base64_decode($data['ciphertext']);

        $freeflowPrivateKey = sodium_crypto_sign_secretkey(base64_decode($keyPair));

        $userPublicKey = base64_decode(Json::decode($response -> getBody())['publicKey']);
        $state = sodium_crypto_sign_open(base64_decode($signedhash),$userPublicKey);

        if ($state != Yii::$app->session -> get("authState")){
            throw new \yii\web\HttpException(400, 'Invalid state');
        }

        $decryption_key = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            sodium_crypto_sign_ed25519_sk_to_curve25519($freeflowPrivateKey),
            sodium_crypto_sign_ed25519_pk_to_curve25519($userPublicKey)
         );

        $decrypted = sodium_crypto_box_open($cipherText, $nonce, $decryption_key);

        if ($decrypted == false){
            throw new \yii\web\HttpException(400, 'Can not decrypt data');
        }

        $result = Json::decode($decrypted);
        $email = $result['email']['email'];
        $emailVerified = $result['email']['verified'];


        if(!$emailVerified){
            return $this->render('error', array('message' => "Email not verified, Please verify and try again"));
        }

        $authUser = Auth::findOne(['source_id' => $username, 'source' => '3bot']);

        // LOGGED IN
        if(!Yii::$app->user->isGuest){
            $user = Yii::$app->user;

            // There's 3bot connection existing but linked to different account
            // Give error in this case
            if ($authUser != null && $authUser -> user_id != $user -> id){
                throw new \yii\web\HttpException(403, "3Bot account used is linked to another user!");
            }

            // Connect
            if ($authUser == null){
                $newUSer = new Auth();
                $newUSer -> source_id = $username;
                $newUSer -> source = '3bot';
                $newUSer -> user_id = $user -> id;
                $newUSer -> save();
            }

        }else { // NOT LOGGED IN
            $user = User::findOne(['email' => $email]);

            // create user if does not exist [New user]
            if ($user == null && $authUser == null){

                $user = new User();
                $user -> username = $username;
                $user -> email = $email;
                $user -> save();
                $user = User::findOne(['email' => $email]);

                $profile = new Profile();
                $profile->scenario = 'editAdmin';
                $profile->user_id = $user -> id;
                $profile-> save();

                $contentContainer = new ContentContainer();
                $contentContainer -> class = "humhub\\modules\\user\\models\\User";
                $contentContainer -> pk = $user -> id;
                $contentContainer -> owner_user_id = $user -> id;

                $groupuser = new GroupUser();
                $groupuser -> user_id = $user -> id;
                $groupuser -> group_id = 2; // users group
                $groupuser -> save();

                $newUSer = new Auth();
                $newUSer -> source_id = $username;
                $newUSer -> source = '3bot';
                $newUSer -> user_id = $user -> id;
                $newUSer -> save();

            }else if ($user != null && $authUser == null){ // user already exists with same email- connect
                $newUSer = new Auth();
                $newUSer -> source_id = $username;
                $newUSer -> source = '3bot';
                $newUSer -> user_id = $user -> id;
                $newUSer -> save();
            }else if ($user == null && $authUser != null){ // connection exists for 3bot, but [emails are different] // find which user and connect
                $user = User::findOne(['id' => $authUser -> user_id]);
            }

            $timeout = 2592000; // 1 month

            if (Yii::$app->getModule('user')->settings->get('auth.defaultUserIdleTimeoutSec')) {
                $timeout = Yii::$app->getModule('user')->settings->get('auth.defaultUserIdleTimeoutSec');
            }            
            Yii::$app->user->login($user, $timeout);
        }
        
        // No token sent, Try find invitation by email
        if(!$userInvite){
            $userInvite = Invite::findOne(['email' => $user -> email]);
        }

        if($userInvite){
            if ($userInvite->language) {
                Yii::$app->language = $userInvite->language;
            }
            $space = Space::findOne(['id' => $userInvite -> space_invite_id]);
            $space -> inviteMember($user -> id, $userInvite -> user_originator_id, true);
            $userInvite -> delete();
        }

        Yii::$app->user->setCurrentAuthClient(new ThreebotAuth());
        $this->redirect(Yii::$app->urlManager->createAbsoluteUrl(['/']));
    }
}
