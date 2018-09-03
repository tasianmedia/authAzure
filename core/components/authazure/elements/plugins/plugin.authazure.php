<?php
switch ($modx->event->name) {
    case 'OnHandleRequest':
        if ($modx->context->key !== 'mgr') {
            if ($modx->user->isAuthenticated($modx->context->key)) {
                if (!$modx->user->active || $modx->user->Profile->blocked) {
                    $modx->runProcessor('security/logout');
                    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start'), '', '', 'full'));
                }
                if (!empty($_REQUEST['authAzure_action'])) {
                    switch ($_REQUEST['authAzure_action']) {
                        case 'logout':
                            $modx->runProcessor('security/logout');
                            $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start'), '', '', 'full'));
                            break;
                    }
                }
            } elseif ($modx->getOption('authazure.enable_sso') || !empty($_REQUEST['authAzure_action']) || !empty($_SESSION['authAzure']['active'])) {
                $path = $modx->getOption('authazure.core_path', null, $modx->getOption('core_path') . 'components/authazure/') . 'model/authazure/';
                $params = array(
                    'ctx' => $modx->context->key
                );
                if ($authAzure = $modx->getService('authazure', 'AuthAzure', $path, $params)) {
                    if (empty($_REQUEST['authAzure_action'])) {
                        $authAzure->Login();
                    } else {
                        switch ($_REQUEST['authAzure_action']) {
                            case 'login':
                                $authAzure->Login();
                                break;
                        }
                    }
                } else {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, '[authAzure] - ' . 'Service class cannot be loaded');
                }
            }
        }
        break;
    case 'OnWebAuthentication':
        $modx->event->_output = !empty($_SESSION['authAzure']['verified']);
        unset($_SESSION['authAzure']['verified']);
        break;
}