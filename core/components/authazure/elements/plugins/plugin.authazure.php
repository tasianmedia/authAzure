<?php
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
            } elseif (isset($_REQUEST['authAzure_action']) || isset($_SESSION['authAzure']['active'])) {
                $path = $modx->getOption('authazure.core_path', null, $modx->getOption('core_path') . 'components/authazure/') . 'model/authazure/';
                if ($authAzure = $modx->getService('authazure', 'AuthAzure', $path, array('ctx' => $modx->context->key))) {
                    if (isset($_SESSION['authAzure']['active']) && $_SESSION['authAzure']['active'] === true) {
                        $authAzure->Login();
                    } else {
                        switch ($_REQUEST['authAzure_action']) {
                            case 'login':
                                $authAzure->Login();
                                break;
                            default:
                                if ($modx->getOption('authazure.enable_sso')) {
                                    if ($id = $modx->getOption('authazure.login_resource_id')) {
                                        $modx->sendForward($id);
                                    } else {
                                        $modx->log(xPDO::LOG_LEVEL_ERROR, '[authAzure] - ' . 'Login Resource ID not found, cannot enable Single Sign-on.');
                                    }
                                }
                        }
                    }
                } else {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, '[authAzure] - ' . 'Service class cannot be loaded');
                }
            } elseif ($modx->getOption('authazure.enable_sso')) {
                if ($id = $modx->getOption('authazure.login_resource_id')) {
                    $modx->sendForward($id);
                } else {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, '[authAzure] - ' . 'Login Resource ID not found, cannot enable Single Sign-on.');
                }
            }
        }
        break;
    case 'OnWebAuthentication':
        $modx->event->_output = !empty($_SESSION['authAzure']['verified']);
        unset($_SESSION['authAzure']['verified']);
        break;
}
