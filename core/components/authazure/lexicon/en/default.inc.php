<?php
/**
 * Default English Lexicon Entries for authAzure
 *
 * @package authAzure
 * @subpackage lexicon
 */

// Settings lexicons
$_lang['setting_authazure.client_id'] = 'Client ID';
$_lang['setting_authazure.client_id_desc'] = 'Specify the Client ID of the application that is registered in Azure Active Directory';
$_lang['setting_authazure.client_secret'] = 'Client Secret';
$_lang['setting_authazure.client_secret_desc'] = 'Secret key used to establish ownership of the Client ID';
$_lang['setting_authazure.redirect_url'] = 'Redirect URL';
$_lang['setting_authazure.redirect_url_desc'] = 'Default URL to which Azure AD will redirect the user after obtaining authorization';
$_lang['setting_authazure.openid_config_url'] = 'OpenID Connect Metadata';
$_lang['setting_authazure.openid_config_url_desc'] = 'URL for your OpenID Connect metadata document';

$_lang['setting_authazure.login_resource_id'] = 'Login Resource ID [REQUIRED]';
$_lang['setting_authazure.login_resource_id_desc'] = 'The ID number of your login resource.';

$_lang['setting_authazure.enable_sso'] = 'Enable Single Sign-on';
$_lang['setting_authazure.enable_sso_desc'] = 'If enabled all non mgr contexts will require user authentication unless overridden via context settings.';

$_lang['setting_authazure.protected_groups'] = 'Protected MODX User Groups [REQUIRED]';
$_lang['setting_authazure.protected_groups_desc'] = 'A comma separated list of user group names which contain users that require access to the MODX Manager (mgr). Members of these groups will be protected from automatic password resets which would otherwise lock them out of the MODX Manager and their accounts.';
$_lang['setting_authazure.default_groups'] = 'Default MODX User Groups ';
$_lang['setting_authazure.default_groups_desc'] = 'A comma separated list of user group names which authenticated users will be given membership to upon login. You can also include optional \'Role\' ID and \'Rank\' number for each group using the format - \'Group1Name:role:rank,Group2Name:role:rank\'';
$_lang['setting_authazure.enable_group_sync'] = 'Enable Azure AD User Group Sync';
$_lang['setting_authazure.enable_group_sync_desc'] = 'To enable auto synchronised Azure Active Directory user group memberships, select a parent group to contain the synced groups.';
