# Freeflow 3bot Login Integration module 

## Requirements

- HumHub URL Rewriting enabled
    - HumHub 1.3+
    - Enable pretty Urls in humhub configuration file **protected/config/common.php**
       ```php
        <?php
    
        return [
            'components' => [
                'urlManager' => [
                    'showScriptName' => false,
                    'enablePrettyUrl' => true,
                ],
            ]
        ];
        ```
    - Enable rewriting in Apache server
        - Rename the file `.htaccess-dist` in humhub home dir to `.htaccess`
        - Edit the Apache configuration file **/etc/apache2/sites-available/000-default.conf** 
        ```editorconfig
          <VirtualHost *:80>
                  <Directory /var/www/html>
                        Options Indexes FollowSymLinks MultiViews
                        AllowOverride All
                        Require all granted
                  </Directory>
           </VirtualHost>
        ```
        - Enable `mod-rewrite` in Apache by invoking `a2enmod rewrite` then restart apache by `service apache2 restart` 
 
## Installation

#### Add configuration entry

- Generate Cryptographic Key Pair
```php
php -r "echo PHP_EOL;echo base64_encode(sodium_crypto_sign_keypair());echo PHP_EOL;echo PHP_EOL;"
```

- Add the following to `protected/config/common.php`

```buildoutcfg
'authClientCollection' => [
            'clients' => [
                '3bot' => [
                    'class' => 'humhub\modules\threebot_login\authclient\ThreebotAuth',
                    'clientId' => '3bot',
                    'keyPair' => '{Generated_Key_Pair}'

                ]
            ]
]

```
#### Install module

##### Manual way
1. Download module files and put it into: **/protected/modules/threebot_login**
2. Make sure module directory owned by Web user : `chmod -R www-data:www-data {humhub-Path}/protected/modules/threebot_login
2. Enable module (Administration -> Modules -> Installed -> Threebot Login -> Enable)

##### Automatic way (command line)

```bash
cd {humhub-installation-dir}/protected
cp -r humhub-modules-rest {humhub-installation-dir}/protected/modules/threebot_login
chown -R www-data:www-data modules/threebot_login
./yii module/list
./yii module/enable threebot_login
```


