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
            'defaultGroups' => $this->modx->getOption('authazure.default_groups'),
            'adGroupSync' => $this->modx->getOption('authazure.enable_group_sync'),

        ), $config);
        $this->modx->addPackage('authazure', $this->config['modelPath']);
    }

    /**
     * Authenticates user and logs them in. Also creates/updates user profile
     *
     * @return void
     */
    public function Login()
    {
        //TODO check if mgr user and if yes addSessionContext
        $provider = $this->loadProvider();
        $id_token = $this->userAuth($provider);

        if ($id_token) {
            // Check if new or existing user
            $username = $id_token['email'];
            if ($this->modx->getCount('modUser', array('username' => $username))) {
                try {
                    // get user details
                    $user = $this->modx->getObject('modUser', array('username' => $username));
                    $aaz_profile = $this->modx->getObject('AazProfile', array('user_id' => $user->get('id'))); //TODO add error logging
                    $token = unserialize($aaz_profile->get('token'));
                    if ($token->hasExpired()) {
                        $exp_token = $token;
                        $token = '';
                        if ($exp_token->getRefreshToken()) {
                            try {
                                $token = $provider->getAccessToken('refresh_token', [
                                    'refresh_token' => $exp_token->getRefreshToken() //TODO document - app must request and be granted the offline_access scope https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-id-and-access-tokens
                                ]);
                            } catch (Exception $e) {
                                $this->exceptionHandler($e, __LINE__);
                            }
                        }
                        if (!$token) {
                            $token = $provider->getAccessToken('authorization_code', [
                                'code' => $_REQUEST['code']
                            ]);
                        }
                        $aaz_profile->set('token', serialize($token));
                        $aaz_profile->save();
                    }
                    //get active directory profile
                    $ad_profile = $this->getApi('https://graph.microsoft.com/beta/me', $token, $provider);
                    try {
                        $ad_profile['photoUrl'] = $this->getProfilePhoto($ad_profile['mailNickname'], $token, $provider);
                    } catch (Exception $e) {
                        $this->exceptionHandler($e, __LINE__);
                    }
                    $user_data = array(
                        'id' => $user->get('id'),
                        'username' => $username,
                        'fullname' => $ad_profile['givenName'] . ' ' . $ad_profile['surname'],
                        'email' => $ad_profile['mail'],
                        'defaultGroups' => $this->config['defaultGroups'],
                        'active' => 1
                    );
                    //sync active directory groups
                    if ($this->config['adGroupSync']) {
                        try {
                            $group_arr = $this->getApi('https://graph.microsoft.com/v1.0/me/memberOf?$select=displayName', $token, $provider);
                            $user_data['adGroupsParent'] = $this->config['adGroupSync'];
                            $user_data['adGroups'] = implode(',',array_column($group_arr['value'], 'displayName'));
                        } catch (Exception $e) {
                            $this->exceptionHandler($e, __LINE__);
                        }
                    }
                    //update user
                    $response = $this->runProcessor('web/user/update', $user_data);
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                            '[authAzure] -  User update failed ' . print_r($user_data, true) . '. Message: ' . $msg
                        );
                        //TODO add error page redirect
                    } else {
                        $response = $this->runProcessor('web/profile/update', array(
                            'id' => $aaz_profile->get('id'),
                            'data' => serialize($ad_profile)
                        ));
                        if ($response->isError()) {
                            $msg = implode(', ', $response->getAllErrors());
                            $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                                '[authAzure] -  Unable to update authAzure Profile ' . print_r($user_data, true) . '. Message: ' . $msg
                            );
                            //TODO add error page redirect
                        } else {
                            //login
                            $login_data = [
                                'username' => $username,
                                'password' => md5(rand()),
                                'rememberme' => true,
                                'login_context' => $this->config['ctx'] //TODO add sys setting support
                            ];
                            $_SESSION['authAzure']['verified'] = true;
                            $response = $this->modx->runProcessor('security/login', $login_data);
                            if ($response->isError()) {
                                $msg = implode(', ', $response->getAllErrors());
                                $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                                    '[authAzure] -  Login error for user ' . $login_data['username'] . '. Message: ' . $msg);
                                //TODO add error page redirect
                            } else {
                                //redirect AFTER login
                                if (isset($_SESSION['authAzure']['redirectUrl'])) {
                                    $this->modx->sendRedirect($_SESSION['authAzure']['redirectUrl']);
                                    unset($_SESSION['authAzure']['redirectUrl']);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->exceptionHandler($e, __LINE__);
                    //TODO add error page redirect
                }
            } else {
                try {
                    $token = $provider->getAccessToken('authorization_code', [
                        'code' => $_REQUEST['code']
                    ]);
                    //get active directory profile
                    $ad_profile = $this->getApi('https://graph.microsoft.com/beta/me', $token, $provider);
                    try {
                        $ad_profile['photoUrl'] = $this->getProfilePhoto($ad_profile['mailNickname'], $token, $provider);
                    } catch (Exception $e) {
                        $this->exceptionHandler($e, __LINE__);
                    }
                    $user_data = array(
                        'username' => $username,
                        'fullname' => $ad_profile['givenName'] . ' ' . $ad_profile['surname'],
                        'email' => $ad_profile['mail'],
                        'defaultGroups' => $this->config['defaultGroups'],
                        'active' => 1,
                    );
                    //sync active directory groups
                    if ($this->config['adGroupSync']) {
                        try {
                            $group_arr = $this->getApi('https://graph.microsoft.com/v1.0/me/memberOf?$select=displayName', $token, $provider);
                            $user_data['adGroupsParent'] = $this->config['adGroupSync'];
                            $user_data['adGroups'] = implode(',',array_column($group_arr['value'], 'displayName'));
                        } catch (Exception $e) {
                            $this->exceptionHandler($e, __LINE__);
                        }
                    }
                    //create user
                    $response = $this->runProcessor('web/user/create', $user_data);
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                            '[authAzure] -  Unable to create user ' . print_r($user_data, true) . '. Message: ' . $msg
                        );
                        //TODO add error page redirect
                    } else {
                        $uid = $response->response['object']['id'];
                        $username = $response->response['object']['username'];
                        $response = $this->runProcessor('web/profile/create', array(
                            'user_id' => $uid,
                            'data' => serialize($ad_profile),
                            'token' => serialize($token)
                        ));
                        if ($response->isError()) {
                            $msg = implode(', ', $response->getAllErrors());
                            $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                                '[authAzure] -  Unable to create authAzure Profile ' . print_r($user_data, true) . '. Message: ' . $msg
                            );
                            //TODO add error page redirect
                        } else {
                            //login
                            $login_data = [
                                'username' => $username,
                                'password' => md5(rand()),
                                'rememberme' => true,
                                'login_context' => $this->config['ctx'] //TODO add sys setting support
                            ];
                            $_SESSION['authAzure']['verified'] = true;
                            $response = $this->modx->runProcessor('security/login', $login_data);
                            if ($response->isError()) {
                                $msg = implode(', ', $response->getAllErrors());
                                $this->modx->log(modX::LOG_LEVEL_ERROR, //TODO move to custom exception handler
                                    '[authAzure] -  Login error for user ' . $login_data['username'] . '. Message: ' . $msg);
                                //TODO add error page redirect
                            } else {
                                //redirect AFTER login
                                if (isset($_SESSION['authAzure']['redirectUrl'])) {
                                    $this->modx->sendRedirect($_SESSION['authAzure']['redirectUrl']);
                                    unset($_SESSION['authAzure']['redirectUrl']);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, '[authAzure] -  Main Catch Block ');
                    $this->exceptionHandler($e, __LINE__);
                    //TODO add error page redirect
                }
            }
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'user is NOT authorised');
            //TODO add error page redirect
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
            $this->exceptionHandler($e, __LINE__);
        }
    }

    /**
     * Custom exception handler
     *
     * @param Throwable $e
     * @param string $line
     *
     * @return void;
     */
    public function exceptionHandler(Throwable $e, string $line)
    {
        $code = $e->getCode();
        if ($code <= 6) {
            $level = modX::LOG_LEVEL_ERROR;
        } else {
            $level = modX::LOG_LEVEL_INFO;
        }
        $this->modx->log($level, '[authAzure] - ' . $e->getMessage() . ' on Line ' . $line, '', '', '', $line); //TODO $line ignored - log modx issue
        //$this->modx->sendRedirect($this->modx->makeUrl($this->modx->getOption('site_start'), '', '', 'full')); //TODO change to custom error page
    }

    /**
     * Authenticates the user against provider and returns verified id_token
     *
     * @param object $provider - Object containing provider config
     *
     * @return array|boolean
     */
    public function userAuth($provider)
    {
        if (!isset($_REQUEST['code'])) {
            try {
                $nonce = md5(rand());
                $authorizationUrl = $provider->getAuthorizationUrl([
                    'scope' => [ //TODO move to sys setting
                        'openid', 'email', 'profile', 'offline_access',
                        'https://graph.microsoft.com/User.Read',
                        'https://graph.microsoft.com/Directory.Read.All'
                    ],
                    'nonce' => $nonce
                ]);
            } catch (Exception $e) {
                $this->exceptionHandler($e, __LINE__);
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
                //TODO decode token using resourceOwner instead
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
        $query_str = strtok(''); //gets the rest
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
     * @throws exception
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
     * @param string $filename - Filename to use for saved image
     * @param string $token - Access Token
     * @param object $provider - Object containing provider config
     *
     * @return string
     * @throws exception
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
