<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

use humhub\components\Application;

/** @noinspection MissedFieldInspection **/
return [
    'id' => 'threebot_login',
    'class' => 'humhub\modules\threebot_login\Module',
    'namespace' => 'humhub\modules\threebot_login',
    'events' => [
        [Application::class, Application::EVENT_BEFORE_REQUEST, ['\humhub\modules\threebot_login\Events', 'onBeforeRequest']]
    ]
];
?>
