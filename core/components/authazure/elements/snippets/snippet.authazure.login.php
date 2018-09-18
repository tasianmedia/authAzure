<?php
/**
 * Handles login / logout flow
 *
 * @package authAzure
 * @subpackage snippets
 *
 * @var modX $modx
 * @var AuthAzure $authazure
 *
 */

$authazure = $modx->getService('authazure', 'AuthAzure', $modx->getOption('authazure.core_path', null, $modx->getOption('core_path') . 'components/authazure/') . 'model/authazure/');
if (!($authazure instanceof AuthAzure)) return $modx->log(MODX::LOG_LEVEL_ERROR, 'Service Class not loaded');

$modx->setPlaceholders(array(
    'login_url' => $authazure->getRedirectUrl() . '?authAzure_action=login',
),'aaz.');

return true;