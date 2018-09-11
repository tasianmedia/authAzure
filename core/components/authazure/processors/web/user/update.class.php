<?php
/**
 * Update a MODX user from Azure Active Directory data
 *
 * @package authAzure
 * @subpackage processors/web/user
 */
require MODX_CORE_PATH . 'model/modx/processors/security/user/update.class.php';

class authAzureUserUpdateProcessor extends modUserUpdateProcessor
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
        $memberships = array();

        $defaultGroups = $this->getProperty('defaultGroups', null);
        $adGroups = $this->getProperty('adGroups', null);
        $adGroupsParent = $this->getProperty('adGroupsParent', 0);

        //remove all ad group memberships
        if ($adGroupsParent) {
            $query = $this->modx->newQuery('modUserGroupMember');
            $query->leftJoin('modUserGroup', 'UserGroup');
            $query->select(array('modUserGroupMember.*,UserGroup.parent'));
            $query->where(array(
                'member' => $this->getProperty('id'),
                'UserGroup.parent' => $adGroupsParent
            ));
            /** @var modUserGroupMember $row */
            $rows = $this->modx->getCollection('modUserGroupMember', $query);
            foreach ($rows as $row) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Label: ' . print_r($row->toArray(), true));
                $row->remove();
            }
        }
        if ($defaultGroups !== null) {
            $defaultGroups = explode(',', $defaultGroups);
            foreach ($defaultGroups as $tmp) {
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
                    if (!$this->object->isMember($group)) {
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
        }
        if ($adGroups) {
            $adGroups = explode(',', $adGroups);
            foreach ($adGroups as $group) {
                //create group if new
                if ($this->modx->getCount('modUserGroup', array('name' => $group)) === 0) {
                    /** @var modUserGroup $newGroup */
                    $newGroup = $this->modx->newObject('modUserGroup');
                    $newGroup->set('name', $group);
                    $newGroup->set('parent', $adGroupsParent);
                    $newGroup->save();
                }
                if ($tmp = $this->modx->getObject('modUserGroup', array('name' => $group))) {
                    $gid = $tmp->get('id');
                    /** @var modUserGroupMember $membership */
                    $membership = $this->modx->newObject('modUserGroupMember');
                    $membership->set('user_group', $gid);
                    $membership->set('role', 1);
                    $membership->set('member', $this->object->get('id'));
                    $membership->set('rank', 2);
                    $membership->save();
                    $memberships[] = $membership;
                }
            }
        }
        return $memberships;
    }
}

return 'authAzureUserUpdateProcessor';
