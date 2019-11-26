<?php
/**
 * Snippet to handle login / logout flow
 *
 * @package authAzure
 * @subpackage snippets
 *
 * @var modX $modx
 * @var array $scriptProperties
 * @var AuthAzure $authAzure
 *
 */

// Instantiate service class
$authAzure = $modx->getService('authazure', 'AuthAzure', $modx->getOption('authazure.core_path', null, $modx->getOption('core_path') . 'components/authazure/') . 'model/authazure/', array('ctx' => $modx->context->key));
if (!($authAzure instanceof AuthAzure)) {
    $modx->log(MODX::LOG_LEVEL_ERROR, '[authAzure] - ' . 'Service class not loaded');
    return;
};

/* set default properties */
$loginTpl = !empty($loginTpl) ? $loginTpl : '';
$logoutTpl = !empty($logoutTpl) ? $logoutTpl : '';
$output = '';
$phs = array();

//TODO add sys setting to make optional
$modx->regClientStartupHTMLBlock('<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">');
$modx->regClientHTMLBlock('<script defer src="https://use.fontawesome.com/releases/v5.3.1/js/all.js" integrity="sha384-kW+oWsYx3YpxvjtZjFXqazFpA7UP/MbiY4jvs+RWZo2+N94PFZ36T6TFkc9O3qoB" crossorigin="anonymous"></script>');

if (!isset($_SESSION['authAzure']['error'])) {
    $phs['aaz']['login_url'] = $authAzure->getActionUrl('login',true);
} else {
    $phs['aaz']['login_url'] = $authAzure->getActionUrl('login',false);
    $phs['aaz']['error'] = $modx->lexicon('authazure.err.login');
    $phs['aaz']['error_id'] = $_SESSION['authAzure']['error'];
    unset($_SESSION['authAzure']['error']);
}
if (!$modx->user->isAuthenticated($modx->context->key)) {
    $output = $modx->getChunk($loginTpl, $phs);
} else {
    $phs['aaz']['logout_url'] = $authAzure->getActionUrl('logout', false);
    $phs['aaz']['logout_azure_url'] = $authAzure->getLogoutUrl();
    $output = $modx->getChunk($logoutTpl, $phs);
}

return $output;