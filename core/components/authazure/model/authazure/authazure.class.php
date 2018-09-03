<?php

/**
 * The authAzure service class.
 *
 * @package authAzure
 */
class AuthAzure
{
    public $modx;
    public $config = array();

    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        $basePath = $this->modx->getOption('authazure.core_path', $config, $this->modx->getOption('core_path') . 'components/authazure/');
        $assetsUrl = $this->modx->getOption('authazure.assets_url', $config, $this->modx->getOption('assets_url') . 'components/authazure/');
        $this->config = array_merge(array(
            'basePath' => $basePath,
            'corePath' => $basePath,
            'vendorPath' => $basePath . 'vendors/',
            'modelPath' => $basePath . 'model/',
            'processorsPath' => $basePath . 'processors/',
            'templatesPath' => $basePath . 'templates/',
            'chunksPath' => $basePath . 'elements/chunks/',
            'jsUrl' => $assetsUrl . 'mgr/js/',
            'cssUrl' => $assetsUrl . 'mgr/css/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl . 'connector.php',

            'addContexts' => '', //FIXME Convert to system setting
            'groups' => 'Staff', //FIXME Convert to system setting

        ), $config);
        $this->modx->addPackage('authazure', $this->config['modelPath']);
    }

    /**
     * Checks user and logs them in. Also creates/updates user profile
     *
     * @return void
     */
    public function Login()
    {
        $provider = $this->loadProvider();
        $id_token = $this->userAuth($provider);

        if ($id_token) {
            // Check if new or existing user
            $username = $id_token['email'];
            if ($this->modx->getCount('modUser', array('username' => $username))) {
                //$this->modx->log(modX::LOG_LEVEL_ERROR, $username . ' - EXISTS');
                // check existing access token - refresh if needed
                // get AAD profile
                // create profile
                // extended fields - https://github.com/modxcms/Login/blob/master/core/components/login/processors/register.php#L113
                // update user
                // login
            } else {
                try {
                    // get access token for new user
                    $token = $provider->getAccessToken('authorization_code', [
                        'code' => $_REQUEST['code']
                    ]);
                    // get AAD profile
                    $aadProfile = $this->getApi('https://graph.microsoft.com/beta/me', $token, $provider);
                    $aadProfile['photoUrl'] = $this->getProfilePhoto($aadProfile['mailNickname'], $token, $provider);
                    // prep new user
                    $newUser = array(
                        'username' => $id_token['email'],
                        'fullname' => $aadProfile['givenName'] . ' ' . $aadProfile['surname'],
                        'email' => $aadProfile['mail'],
                        'photo' => $aadProfile['photoUrl'],
                        'phone' => $aadProfile['businessPhones'][0],
                        'mobilephone' => $aadProfile['mobilePhone'],
                        'groups' => 'Staff', //TODO sync user groups from aad
                        'active' => 1,
                    );
                    //create user
                    $response = $this->runProcessor('web/user/create', $newUser);
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                            '[authAzure] -  Unable to create user ' . print_r($newUser, true) . '. Message: ' . $msg
                        );
                    } else {
                        //TODO add md5 hash for profile comparison
                        $uid = $response->response['object']['id'];
                        $username = $response->response['object']['username'];
                        $response = $this->runProcessor('web/profile/create', array(
                            'user_id' => $uid,
                            'data' => serialize($aadProfile),
                            'token' => serialize($token)
                        ));
                        if ($response->isError()) {
                            $msg = implode(', ', $response->getAllErrors());
                            $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                                '[authAzure] -  Unable to create AAD Profile ' . print_r($newUser, true) . '. Message: ' . $msg
                            );
                        } else {
                            //login
                            $login_data = [
                                'username' => $username,
                                'password' => md5(rand()),
                                'rememberme' => true,
                                'login_context' => $this->config['ctx']
                            ];
                            $_SESSION['authAzure']['verified'] = true;
                            $response = $this->modx->runProcessor('security/login', $login_data);
                            if ($response->isError()) {
                                $msg = implode(', ', $response->getAllErrors());
                                $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                                    '[authAzure] -  Login error for user ' . $login_data['username'] . '. Message: ' . $msg);
                            } else {
                                //redirect AFTER login
                                if (isset($_SESSION['authAzure']['redirectUrl'])) {
                                    $this->modx->sendRedirect($_SESSION['authAzure']['redirectUrl']);
                                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'url: ' . print_r($_SESSION['authAzure']['redirectUrl'], true));
                                    unset($_SESSION['authAzure']['redirectUrl']);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->exceptionHandler($e);
                }
            }
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'user is NOT authorised');
        }
    }

    /**
     * Instantiates the provider
     *
     * @return object
     */
    public function loadProvider()
    {
        require_once $this->config['vendorPath'] . 'autoload.php';
        $providerConfig = [
            'clientId' => $this->modx->getOption('authazure.client_id'),
            'clientSecret' => $this->modx->getOption('authazure.client_secret'),
            'redirectUri' => $this->modx->getOption('authazure.redirect_url'),
            'metadata' => $this->modx->getOption('authazure.openid_config_url'),
            'responseType' => 'id_token code',
            'responseMode' => 'form_post',
        ];
        try {
            $provider = new TheNetworg\OAuth2\Client\Provider\Azure($providerConfig);
            return $provider;
        } catch (Exception $e) {
            $this->exceptionHandler($e);
        }
    }
    /**
     * Custom exception handler
     *
     * @param Throwable $e
     *
     * @return void;
     */
    public function exceptionHandler(Throwable $e)
    {
        $code = $e->getCode();
        if ($code <= 6) {
            $level = modX::LOG_LEVEL_ERROR;
        } else {
            $level = modX::LOG_LEVEL_INFO;
        }
        $this->modx->log($level, '[authAzure] - ' . $e->getMessage() . ' on Line ' . $e->getLine());
        $this->modx->sendRedirect($this->modx->makeUrl($this->modx->getOption('site_start'), '', '', 'full')); //TODO change to custom error page
    }
    /**
     * Authenticates the user against provider and returns verified id_token
     *
     * @param object $provider - Object containing provider config
     *
     * @return array|bool
     */
    public function userAuth($provider)
    {
        if (!isset($_REQUEST['code'])) {
            try {
                $nonce = md5(rand());
                $authorizationUrl = $provider->getAuthorizationUrl([
                    'scope' => [
                        'openid', 'email', 'profile',
                        'https://graph.microsoft.com/user.read',
                        'https://graph.microsoft.com/user.read.all',
                    ],
                    'nonce' => $nonce
                ]);
            } catch (Exception $e) {
                $this->exceptionHandler($e);
            }
            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $provider->getState();
            $_SESSION['oauth2nonce'] = $nonce;
            $_SESSION['authAzure']['redirectUrl'] = $this->getRedirectUrl();
            $_SESSION['authAzure']['active'] = true;

            // Redirect the user to the authorization URL.
            $this->modx->sendRedirect($authorizationUrl);
            exit;
            // Check given state against previously stored one to mitigate CSRF attack
        } elseif (!empty($_REQUEST['state']) && (isset($_SESSION['oauth2state']) && $_REQUEST['state'] == $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            unset($_SESSION['authAzure']['active']);
            if (isset($_REQUEST['id_token'])) { //add nonce check here and maybe signature check as well
                $id_array = explode('.', $_REQUEST['id_token']);
                $id_array[1] = base64_decode($id_array[1]);
                $id_token = json_decode($id_array[1], true);
                return $id_token;
            }
        }
        return false;
    }
    /**
     * Returns clean redirect url
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        $request = preg_replace('#^' . $this->modx->getOption('base_url') . '#', '', strtok($_SERVER['REQUEST_URI'], '?'));
        $query_str = strtok( '' ); //gets the rest
        if ($query_str !== false) {
            parse_str($query_str, $query_arr);
            if (!empty($query_arr['authAzure_action'])) {
                unset($query_arr['authAzure_action']);
            }
            if (!empty($query_arr)) {
                $url_params = '?' . http_build_query($query_arr, '', '&amp;');
                $request .= $url_params;
            }
        }
        $url = $this->modx->getOption('site_url') . ltrim(rawurldecode($request), '/');
        $url = preg_replace('#["\']#', '', strip_tags($url));
        return $url;
    }
    /**
     * Returns Microsoft Graph response
     *
     * @param string $url - API Endpoint URL
     * @param string $token - Access Token
     * @param object $provider - Object containing provider config
     *
     * @return array
     * @throws
     */
    public function getApi($url, $token, $provider)
    {
        try {
            $request = $provider->getAuthenticatedRequest('get', $url, $token);
            $response = $provider->getResponse($request);
            $body = $response->getBody();
            return json_decode($body, true);
        } catch (Exception $e) {
            throw $e;
        }
    }
    /**
     * Returns profile photo url
     *
     * @param string $filename - API Endpoint URL
     * @param string $token - Access Token
     * @param object $provider - Object containing provider config
     *
     * @return string
     * @throws
     */
    public function getProfilePhoto($filename, $token, $provider)
    {
        try {
            $request = $provider->getAuthenticatedRequest('get', 'https://graph.microsoft.com/beta/me/photo/$value', $token);
            $response = $provider->getResponse($request);
            $body = $response->getBody();
            $path = 'img/web/profile-photos/' . $filename . '.jpg';
            $photo = fopen($path, "w");
            fwrite($photo, $body);
            fclose($photo);
            return $path;
        } catch (Exception $e) {
            throw $e;
        }
    }
    /**
     * Shorthand for load and run custom processor
     *
     * @param string $action
     * @param array $scriptProperties
     *
     * @return mixed
     */
    public function runProcessor($action = '', $scriptProperties = [])
    {
        $this->modx->error->reset();
        return $this->modx->runProcessor($action, $scriptProperties, [
                'processors_path' => $this->config['processorsPath'],
            ]
        );
    }
}
