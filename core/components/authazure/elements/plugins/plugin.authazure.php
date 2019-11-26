<?php
/**
 * Plugin to handle login / logout flow
 *
 * @package authAzure
 * @subpackage plugins
 *
 * @var modX $modx
 * @var AuthAzure $authAzure
 */
switch ($modx->event->name) {
    case 'OnHandleRequest':
        if ($modx->context->key !== 'mgr') {
            if ($modx->user->isAuthenticated($modx->context->key)) {
                if (!$modx->user->active || $modx->user->Profile->blocked) {
                    $modx->runProcessor('security/logout');
                    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start'), '', '', 'full'));
                }
                if (isset($_REQUEST['authAzure_action'])) {
                    switch ($_REQUEST['authAzure_action']) {
                        case 'logout':
                            $modx->runProcessor('security/logout');
                            unset($_SESSION['authAzure']);
                            $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start'), '', '', 'full'));
                            break;
                    }
                }
                return;
            }
            // Instantiate service class
            $authAzure = $modx->getService('authazure', 'AuthAzure', $modx->getOption('authazure.core_path', null, $modx->getOption('core_path') . 'components/authazure/') . 'model/authazure/', array('ctx' => $modx->context->key));
            if (!($authAzure instanceof AuthAzure)) {
                $modx->log(MODX::LOG_LEVEL_ERROR, '[authAzure] - ' . 'Service class not loaded');
                return;
            };
            try {
                //required
                if ($modx->getOption('authazure.login_resource_id')) {
                    $aaz_login_id = $modx->getOption('authazure.login_resource_id');
                } else {
                    throw new Exception("Login Resource ID is required.");
                }
                //handle errors
                if (isset($_SESSION['authAzure']['error'])) {
                    $modx->sendForward($aaz_login_id);
                } //handle flow
                elseif (isset($_REQUEST['authAzure_action']) || isset($_SESSION['authAzure']['active'])) {
                    if (isset($_SESSION['authAzure']['active']) && $_SESSION['authAzure']['active'] === true) {
                        $authAzure->Login();
                    } else {
                        switch ($_REQUEST['authAzure_action']) {
                            case 'login':
                                $authAzure->Login();
                                break;
                        }
                    }
                } elseif ($modx->getOption('authazure.enable_seamless')) {
                    $authAzure->Login();
                } elseif ($modx->getOption('authazure.enable_sso')) {
                    $modx->sendForward($aaz_login_id);
                }
            } catch (Exception $e) {
                $authAzure->exceptionHandler($e,true);
            }
        }
        break;
    case 'OnWebAuthentication':
        $modx->event->_output = !empty($_SESSION['authAzure']['verified']);
        unset($_SESSION['authAzure']['verified']);
        break;
}

