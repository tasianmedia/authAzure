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
        $this->modx->lexicon->load('authazure:default');
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

            'loginResourceId' => $this->modx->getOption('authazure.login_resource_id'),
            'defaultGroups' => $this->modx->getOption('authazure.default_groups'),
            'protectedGroups' => $this->modx->getOption('authazure.protected_groups'),
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
        //TODO Refactor to handle user check | profile | login separately
        //TODO ^include better handing of logged in mgr users
        try {
            $this->init();
            $provider = $this->loadProvider();
            $id_token = $this->userAuth($provider);
        } catch (Exception $e) {
            $this->exceptionHandler($e, __LINE__, true);
        }

        if (isset($id_token) && $id_token) {
            // Set critical
            $username = $id_token['email'];
            // Check if new or existing user
            /** @var modUser $user */
            if ($user = $this->modx->getObject('modUser', array('username' => $id_token['email']))) {
                try {
                    /** @var AazProfile $aaz_profile */
                    if ($aaz_profile = $this->modx->getObject('AazProfile', array('user_id' => $user->get('id')))) {
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
                    } else {
                        $token = $provider->getAccessToken('authorization_code', [
                            'code' => $_REQUEST['code']
                        ]);
                        $aaz_profile = $this->modx->newObject('AazProfile', array(
                            'user_id' => $user->get('id'),
                            'token' => serialize($token)
                        ));
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
                        'active' => 1 //TODO fix active status
                    );
                    //sync active directory groups
                    if ($this->config['adGroupSync']) {
                        try {
                            $group_arr = $this->getApi('https://graph.microsoft.com/v1.0/me/memberOf?$select=displayName', $token, $provider);
                            $user_data['adGroupsParent'] = $this->config['adGroupSync'];
                            $user_data['adGroups'] = implode(',', array_column($group_arr['value'], 'displayName'));
                        } catch (Exception $e) {
                            $this->exceptionHandler($e, __LINE__);
                        }
                    }
                    //update user
                    /** @var modProcessorResponse $response */
                    $response = $this->runProcessor('web/user/update', $user_data);
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        throw new Exception('Update user failed: ' . print_r($user_data, true) . '. Message: ' . $msg);
                    }
                    //update profile
                    /** @var modProcessorResponse $response */
                    $response = $this->runProcessor('web/profile/update', array(
                        'id' => $aaz_profile->get('id'),
                        'data' => serialize($ad_profile)
                    ));
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        throw new Exception('Update user profile failed: ' . print_r($user_data, true) . '. Message: ' . $msg);
                    }
                    if (!$user->isMember(explode(',', $this->config['protectedGroups']))) {
                        $login_data = [
                            'username' => $username,
                            'password' => md5(rand()),
                            'rememberme' => false,
                            'login_context' => $this->config['ctx']
                        ];
                        $_SESSION['authAzure']['verified'] = true;
                        /** @var modProcessorResponse $response */
                        $response = $this->modx->runProcessor('security/login', $login_data);
                        if ($response->isError()) {
                            $msg = implode(', ', $response->getAllErrors());
                            throw new Exception('Login user failed: ' . print_r($user_data, true) . '. Message: ' . $msg);
                        }
                    } else {
                        $user->addSessionContext($this->config['ctx']);
                    }
                    //redirect after login
                    if (isset($_SESSION['authAzure']['redirectUrl'])) {
                        $this->modx->sendRedirect($_SESSION['authAzure']['redirectUrl']);
                        unset($_SESSION['authAzure']['redirectUrl']);
                    }
                } catch (Exception $e) {
                    $this->exceptionHandler($e, __LINE__, true);
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
                            $user_data['adGroups'] = implode(',', array_column($group_arr['value'], 'displayName'));
                        } catch (Exception $e) {
                            $this->exceptionHandler($e, __LINE__);
                        }
                    }
                    //create user
                    /** @var modProcessorResponse $response */
                    $response = $this->runProcessor('web/user/create', $user_data);
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        throw new Exception('Create new user failed: ' . print_r($user_data, true) . '. Message: ' . $msg);
                    }
                    $uid = $response->response['object']['id'];
                    //create profile
                    /** @var modProcessorResponse $response */
                    $response = $this->runProcessor('web/profile/create', array(
                        'user_id' => $uid,
                        'data' => serialize($ad_profile),
                        'token' => serialize($token)
                    ));
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        throw new Exception('Create new user profile failed: ' . print_r($user_data, true) . '. Message: ' . $msg);
                    }
                    $login_data = [
                        'username' => $username,
                        'password' => md5(rand()),
                        'rememberme' => false,
                        'login_context' => $this->config['ctx']
                    ];
                    $_SESSION['authAzure']['verified'] = true;
                    /** @var modProcessorResponse $response */
                    $response = $this->modx->runProcessor('security/login', $login_data);
                    if ($response->isError()) {
                        $msg = implode(', ', $response->getAllErrors());
                        throw new Exception('Login new user failed: ' . print_r($user_data, true) . '. Message: ' . $msg);
                    }
                    //redirect AFTER login
                    if (isset($_SESSION['authAzure']['redirectUrl'])) {
                        $this->modx->sendRedirect($_SESSION['authAzure']['redirectUrl']);
                        unset($_SESSION['authAzure']['redirectUrl']);
                    }
                } catch (Exception $e) {
                    $this->exceptionHandler($e, __LINE__, true);
                }
            }
        }
    }

    /**
     * Initial checks before auth flow
     *
     * @return void;
     * @throws exception
     */
    public function init()
    {
        try {
            if (!$this->config['loginResourceId']) {
                throw new Exception('User authentication aborted. Login Resource ID not found system settings but is required.');
            }
            if (!$this->config['protectedGroups']) {
                throw new Exception('User authentication aborted. Protected User Groups not found in system settings but is required.');
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Instantiates the provider
     *
     * @return object
     * @throws exception
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
            throw $e;
        }
    }

    /**
     * Authenticates the user against provider and returns verified id_token
     *
     * @param object $provider - Object containing provider config
     *
     * @return array
     * @throws exception
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
                // Get the state generated for you and store it to the session.
                $_SESSION['authAzure']['oauth2state'] = $provider->getState();
                $_SESSION['authAzure']['oauth2nonce'] = $nonce;
                $_SESSION['authAzure']['redirectUrl'] = $this->getRedirectUrl();
                $_SESSION['authAzure']['active'] = true;
            } catch (Exception $e) {
                throw $e;
            }
            // Redirect the user to the authorization URL.
            $this->modx->sendRedirect($authorizationUrl);
            exit;
            // Check given state against previously stored one to mitigate CSRF attack
        } elseif (!empty($_REQUEST['state']) && (isset($_SESSION['authAzure']['oauth2state']) && $_REQUEST['state'] == $_SESSION['authAzure']['oauth2state'])) {
            unset($_SESSION['authAzure']['oauth2state']);
            unset($_SESSION['authAzure']['active']);
            if (isset($_REQUEST['id_token'])) {
                //TODO add nonce check
                $id_array = explode('.', $_REQUEST['id_token']);
                $id_array[1] = base64_decode($id_array[1]);
                $id_token = json_decode($id_array[1], true);
                return $id_token;
            }
            throw new Exception('ID Token not received. User authentication failed and login aborted.');
        }
        throw new Exception('OAuth2 stored state mismatch. User authentication failed and login aborted.');
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
     * Returns clean action url
     *
     * @param string $action - authAzure action
     * @param bool $referer - use HTTP_REFERER to generate returned url
     *
     * @return string
     */
    public function getActionUrl(string $action = null, bool $referer = false)
    {
        if ($_SERVER['HTTP_REFERER'] && $referer) {
            $request = strtok($_SERVER['HTTP_REFERER'], '?');
        } else {
            $request = preg_replace('#^' . $this->modx->getOption('base_url') . '#', '', strtok($_SERVER['REQUEST_URI'], '?'));
            $request = $this->modx->getOption('site_url') . ltrim($request, '/');
        }
        $query_str = strtok(''); //gets the rest
        if ($query_str !== false) {
            parse_str(html_entity_decode($query_str), $query_arr);
        } else {
            $query_arr = array();
        }
        if ($action) {
            $query_arr['authAzure_action'] = $action;
        }
        if (!empty($query_arr)) {
            $url_params = '?' . http_build_query($query_arr, '', '&amp;');
            $request .= $url_params;
        }
        $request = rawurldecode($request);
        $url = preg_replace('#["\']#', '', strip_tags($request));

        return $url;
    }

    /**
     * Custom exception handler
     *
     * @param Throwable $e
     * @param string $line
     * @param bool $fatal (optional)
     *
     * @return void;
     */
    public function exceptionHandler(Throwable $e, string $line, bool $fatal = false)
    {
        $code = $e->getCode();
        if ($code <= 6 || $fatal) {
            $level = modX::LOG_LEVEL_ERROR;
        } else {
            $level = modX::LOG_LEVEL_INFO;
        }
        $this->modx->log($level, '[authAzure] - ' . $e->getMessage() . ' on Line ' . $line, '', '', '', $line); //TODO $line ignored - log modx issue
        if ($fatal) {
            unset($_SESSION['authAzure']);
            $_SESSION['authAzure']['error'] = true; //TODO fix unset in correct places
            if ($id = $this->config['loginResourceId']) {
                $this->modx->sendRedirect($this->modx->makeUrl($id, '', '', 'full'));
            } else {
                $this->modx->sendRedirect($this->modx->makeUrl($this->modx->getOption('site_start'), '', '', 'full'));
            }
            exit;
        }
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
