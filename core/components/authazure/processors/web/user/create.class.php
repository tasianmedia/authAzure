<?php
/**
 * Create a MODX user from Azure Active Directory data
 *
 * @package authAzure
 * @subpackage processors/web/user
 */
require MODX_CORE_PATH . 'model/modx/processors/security/user/create.class.php';

class authAzureUserCreateProcessor extends modUserCreateProcessor
{
	public $classKey = 'modUser';
	public $languageTopics = array('core:default', 'core:user');
	public $permission = '';
	public $objectType = 'user';
	public $beforeSaveEvent = 'OnBeforeUserFormSave';
	public $afterSaveEvent = 'OnUserFormSave';

	/**
	 * @return bool|null|string
	 */
	public function beforeSet()
	{
		$this->setProperty('passwordnotifymethod', 's');
		if (!$this->getProperty('username')) {
			$this->addFieldError('username', $this->modx->lexicon('field_required'));
		}
		if (!$this->getProperty('email')) {
			$this->addFieldError('username', $this->modx->lexicon('field_required'));
		}
		return parent::beforeSet();
	}
	/**
	 * @return array
	 */
	public function setUserGroups()
	{
        //TODO separate azure ad groups and modx groups
	    $memberships = array();
		$groups = $this->getProperty('groups', null);
		if ($groups !== null) {
            //sync user groups
			$groups = explode(',', $groups);
			foreach ($groups as $tmp) {
                @list($group, $role, $rank) = explode(':', $tmp);
                if (empty($role)) {
                    $role = 1;
                }
                if (empty($rank)) {
                    $rank = 1;
                }
                //create group if new
				if ($this->modx->getCount('modUserGroup', array('name' => $group)) === 0) {
					/** @var modUserGroup $newGroup */
					$newGroup = $this->modx->newObject('modUserGroup');
					$newGroup->set('name', $group);
					$newGroup->save();
				}
				if ($tmp = $this->modx->getObject('modUserGroup', array('name' => $group))) {
					$gid = $tmp->get('id');
					/** @var modUserGroupMember $membership */
					$membership = $this->modx->newObject('modUserGroupMember');
					$membership->set('user_group', $gid);
					$membership->set('role', $role);
					$membership->set('member', $this->object->get('id'));
					$membership->set('rank', $rank);
					$membership->save();
					$memberships[] = $membership;
				}
			}
		}
		return $memberships;
	}
	/**
	 * @return modUserProfile
	 */
	public function addProfile()
	{
		$this->profile = $this->modx->newObject('modUserProfile');
		$this->profile->fromArray($this->getProperties());
		$this->profile->set('blocked', $this->getProperty('blocked', false));
		$this->object->addOne($this->profile, 'Profile');
		return $this->profile;
	}
}
return 'authAzureUserCreateProcessor';
