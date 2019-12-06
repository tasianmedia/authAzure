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
$_lang['setting_authazure.s2s_configs'] = 'Service-to-service Token Configurations';
$_lang['setting_authazure.s2s_configs_desc'] = 'Service-to-service access token request parameters in JSON format - {"service1_name": ["scope1","scope2","scope3"],"service2_name": ["scope1","scope2","scope3"]}. Tokens generated here can be access using \'fetchToken()\' method.';
$_lang['setting_authazure.scopes'] = 'Microsoft Graph API Scopes';
$_lang['setting_authazure.scopes_desc'] = 'A comma separated list of delegated permissions to request from Microsoft Graph API. For more details see: https://docs.microsoft.com/en-us/graph/permissions-reference';

$_lang['setting_authazure.login_resource_id'] = 'Login Resource ID [REQUIRED]';
$_lang['setting_authazure.login_resource_id_desc'] = 'The ID number of your login resource.';

$_lang['setting_authazure.profile_photo_dir'] = 'User Profile Photo Directory';
$_lang['setting_authazure.profile_photo_dir_desc'] = 'Path to directory where User Profile Photo files will be saved. Relative to MODX_BASE_DIR (generally your web root). Make sure this directory is writable by PHP.';

$_lang['setting_authazure.enable_sso'] = 'Enable Single Sign-on';
$_lang['setting_authazure.enable_sso_desc'] = 'If enabled all non mgr contexts will require user authentication unless overridden via context settings.';
$_lang['setting_authazure.enable_seamless'] = 'Enable Seamless Sign-on';
$_lang['setting_authazure.enable_seamless_desc'] = 'If enabled all non mgr contexts will automatically attempt user authentication bypassing the login page.';
$_lang['setting_authazure.enable_cookie_lifetime'] = 'Enable Client Cookie Lifetime';
$_lang['setting_authazure.enable_cookie_lifetime_desc'] = 'If enabled authAzure will set the client session cookie lifetime using the \'session_cookie_lifetime\' system setting when logging in users. Disable and a session cookie is set instead.';

$_lang['setting_authazure.protected_groups'] = 'Protected MODX User Groups [REQUIRED]';
$_lang['setting_authazure.protected_groups_desc'] = 'A comma separated list of user group names which contain users that require access to the MODX Manager (mgr). Members of these groups will be protected from automatic password resets which would otherwise lock them out of the MODX Manager and their accounts.';
$_lang['setting_authazure.default_groups'] = 'Default MODX User Groups ';
$_lang['setting_authazure.default_groups_desc'] = 'A comma separated list of user group names which authenticated users will be given membership to upon login. You can also include optional \'Role\' ID and \'Rank\' number for each group using the format - \'Group1Name:role:rank,Group2Name:role:rank\'';
$_lang['setting_authazure.enable_group_sync'] = 'Enable Azure AD User Group Sync';
$_lang['setting_authazure.enable_group_sync_desc'] = 'To enable auto synchronised Azure Active Directory user group memberships, select a parent group to contain the synced groups.';

// Error lexicons
$_lang['authazure.err.login'] = 'There has been a problem authenticating your account. Please try again or contact your Site Administrator.';
