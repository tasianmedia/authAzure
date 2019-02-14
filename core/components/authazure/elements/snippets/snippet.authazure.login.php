<?php
/**
 * Snippet to handle login / logout flow
 *
 * @package authAzure
 * @subpackage snippets
 *
 * @var modX $modx
 * @var array $scriptProperties
 * @var AuthAzure $authazure
 *
 */

$authazure = $modx->getService('authazure', 'AuthAzure', $modx->getOption('authazure.core_path', null, $modx->getOption('core_path') . 'components/authazure/') . 'model/authazure/');
if (!($authazure instanceof AuthAzure)) return $modx->log(MODX::LOG_LEVEL_ERROR, 'Service Class not loaded');

/* set default properties */
$loginTpl = !empty($loginTpl) ? $loginTpl : '';
$logoutTpl = !empty($logoutTpl) ? $logoutTpl : '';
$output = '';
$phs = array();

//TODO add sys setting to make optional
$modx->regClientStartupHTMLBlock('<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">');
$modx->regClientHTMLBlock('<script defer src="https://use.fontawesome.com/releases/v5.3.1/js/all.js" integrity="sha384-kW+oWsYx3YpxvjtZjFXqazFpA7UP/MbiY4jvs+RWZo2+N94PFZ36T6TFkc9O3qoB" crossorigin="anonymous"></script>');

if (!isset($_SESSION['authAzure']['error'])) {
    $phs['aaz']['login_url'] = $authazure->getActionUrl('login',true);
} else {
    $phs['aaz']['login_url'] = $authazure->getActionUrl('login');
    $phs['aaz']['error'] = $modx->lexicon('authazure.err.login');
    unset($_SESSION['authAzure']['error']);
}
if (!$modx->user->isAuthenticated($modx->context->key)) {
    $output = $modx->getChunk($loginTpl, $phs);
} else {
    $phs['aaz']['logout_url'] = $authazure->getActionUrl('logout');
    $phs['aaz']['logout_azure_url'] = $authazure->getLogoutUrl();
    $output = $modx->getChunk($logoutTpl, $phs);
}

return $output;