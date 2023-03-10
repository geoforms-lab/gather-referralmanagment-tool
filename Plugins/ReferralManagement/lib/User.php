<?php

namespace ReferralManagement;

class User {

	private static $_cachedUserAttribs = array();

	private static $_communityConfig = null;
	private static $_dashboardConfig = null;

	protected function getPlugin() {
		return GetPlugin('ReferralManagement');
	}

	public function clearCache() {

		self::$_cachedUserAttribs = array();
		return $this;

	}

	public function getMetadata($userId = -1) {

		$metadata = null;

		if (is_array($userId)) {
			$metadata = $userId;
			if (!key_exists('id', $metadata)) {
				throw new \Exception('Expected user metadata with id: ' . json_encode($metadata));
			}
			$userId = $metadata['id'];
		}

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}
		if (!$metadata) {
			$metadata = GetClient()->userMetadataFor($userId);
		}

		$metadata['device'] = false;
		if (strpos($metadata['email'], 'device.') === 0) {
			$metadata['device'] = true;
		}

		GetPlugin('Attributes');
		$this->_withUserAttributes($userId,
			function ($attributes) use (&$metadata, $userId) {

				$communities = $this->listCommunities();

				$communitiesComparison = array_map(function ($c) {
					$c = strtolower($c);
					$c = explode('|', $c);
					return array_shift($c);

				}, $communities);

				$metadata['community'] = 'none';

				$uc = strtolower($attributes['community']);
				$uc = explode('|', $uc);
				$uc = array_shift($uc);

				if (in_array($uc, $communitiesComparison)) {
					$i = array_search($uc, $communitiesComparison);
					$metadata['community'] = $communities[$i];;

				} else {
					if (is_string($attributes['community'])) {
						$metadata['_community'] = $attributes['community'];
						$metadata['_communities'] = $communities;
					}
				}

				if (count($communities) == 1 && $metadata['community'] == 'none') {
					$metadata['community'] = $communities[0];
				}

				//$metadata['-community'] = $attributes['community'];
				//$metadata['-communityList'] = $this->listCommunities();

				$metadata['status'] = !!$attributes['registeredStatus'];

				$metadata['address'] = $attributes['address'];
				$metadata['position'] = $attributes['role'];
				$metadata['department'] = $attributes['department'];

				$metadata['reviewed'] = $attributes['reviewed'];

				$metadata['communityId'] = array_search($metadata['community'], $this->listCommunities());

				$metadata['role-icon'] = $this->getUserRoleIcon($userId);
				$metadata['user-icon'] = $this->getUserRoleLabel($userId);
				$metadata['can-create'] = $this->canCreateCommunityContent($userId);
				$metadata['communities'] = $this->getCommunities($userId);
				// $metadata['community'] = $metadata['communities'][0];
				// $metadata['communityId'] = 0;
				$metadata['teams'] = $this->getTeams($userId);
				$metadata['avatar'] = $this->getUsersAvatar($userId);
				$metadata['name'] = $this->getUsersName($userId, $metadata['name']);
				$metadata['lastName'] = $this->getUsersLastName($userId, '');
				$metadata['number'] = $this->getUsersNumber($userId);
				$metadata['email'] = $this->getEmail($userId, $metadata['email']);
				$metadata['can-assignroles'] = $this->getRolesUserCanEdit($userId);

				$metadata['roles'] = $this->getUserRoles($userId);
				$metadata['bio'] = $this->getUsersBio($userId);

				$metadata['online-status'] = 'auto';

				if (isset($attributes['onlineStatus'])) {
					if (in_array($attributes['onlineStatus'], array('auto', 'invisible'))) {
						$metadata['online-status'] = $attributes['onlineStatus'];
					}
				}

				// if($metadata['online-status']=='invisible'){
				// 	$metadata['online-status']='auto';
				// 	$metadata['online-status__']='invisible';
				// }

			});

		return $metadata;

	}

	protected function getRolesUserCanEdit($userId = -1) {
		return (new \ReferralManagement\UserRoles())->getRolesUserCanEdit($userId);
	}

	protected function getUserRoles($userId = -1) {
		return (new \ReferralManagement\UserRoles())->getUsersRoles($userId);
	}

	public function getUsersAvatar($userId = -1, $default = null) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$attribs = $this->_getUserAttributes($userId);
		if ($attribs["profileIcon"]) {
			return HtmlDocument()->parseImageUrls($attribs["profileIcon"])[0];
		}

		if ($default) {
			return $default;
		}
		return UrlFrom($this->getDashboardConfig()->getParameter('defaultUserImage')[0]);

	}

	public function getTeams($userId = -1) {
		return $this->getCommunities($userId);
	}

	public function getCommunities($userId = -1) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}
		$attribs = $this->_getUserAttributes($userId);
		$communities = array();

		if (in_array($attribs['community'], $this->listCommunities())) {
			$communities[] = $attribs['community'];
		}

		return $communities;
	}

	protected function canCreateCommunityContent($userId = -1) {

		return $this->getUserRoleLabel($userId) !== 'none';
	}

	protected function getUserRoleLabel($userId = -1) {

		if ($this->getDashboardConfig()->getParameter('allowUnappovedMobileSubmissions') === true) {
			return true;
		}

		return (new \ReferralManagement\UserRoles())->getUsersRoleLabel($userId);
	}

	public function listTeams() {
		return $this->listCommunities();
	}
	public function listCommunities() {
		return array_merge(array($this->communityCollective()), $this->listTerritories());
	}

	protected function getCommunityConfig() {
		if (!self::$_communityConfig) {
			self::$_communityConfig = GetWidget('communityConfiguration');
		}
		return self::$_communityConfig;
	}

	protected function getDashboardConfig() {
		if (!self::$_dashboardConfig) {
			self::$_dashboardConfig = GetWidget('dashboardConfig');
		}
		return self::$_dashboardConfig;
	}

	public function listTerritories() {


		return GetWidget('organizationalUnits')->getConfigurationValue('groups');

		// $communities = $this->getCommunityConfig()->getParameter("communities");
		// return array_map(function ($community) {
		// 	return $community;
		// }, $communities);
	}

	public function listDefaultTerritories() {
		$communities = $this->getCommunityConfig()->getParameter("communities");
		return array_map(function ($community) {
			return $community;
		}, $communities);
	}


	public function communityCollective() {

		$collective = $this->getCommunityConfig()->getParameter("collective");

		if ($collective == "{subdomain}") {
			$domain = HtmlDocument()->getDomain();
			$collective = substr($domain, 0, strpos($domain, '.'));
		}

		return $collective;
	}

	protected function _withUserAttributes($userId, $callbackFn) {
		$callbackFn($this->_getUserAttributes($userId));
		//$this->cachedUserAttribs = null;
	}

	protected function getUserRoleIcon($userId = -1) {
		return (new \ReferralManagement\UserRoles())->getUserRoleIcon($userId);
	}

	protected function getUsersName($userId = -1, $default = null) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$attribs = $this->_getUserAttributes($userId);

		if ($attribs["firstName"]) {
			return $attribs["firstName"];
		}

		if ($default) {
			return $default;
		}

		return GetClient()->getRealName();

	}

	protected function getUsersBio($userId = -1, $default = null) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$attribs = $this->_getUserAttributes($userId);

		if ($attribs["bio"]) {
			return $attribs["bio"];
		}

		if ($default) {
			return $default;
		}

		return '';

	}

	public function setUserStatus($status) {

		$validModes = array('auto', 'invisible');

		if (!in_array($status, $validModes)) {
			throw new \Exception('Invalid status: ' . $status . ' Expected one of: ' . implode(', ', $validModes));
		}

		GetPlugin('Attributes');

		(new \attributes\Record('userAttributes'))->setValues(GetClient()->getUserId(), 'user', array(
			'onlineStatus' => $status,
		));

		$this->getPlugin()->cache()->needsUserListUpdate();

		return $this;

	}


	public function canEditUsersRole($userId){



		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$usersRoles = (new \ReferralManagement\UserRoles())->getUsersRoles($userId);

		if (!GetClient()->isAdmin()) {

			$canSetList = $this->getPlugin()->getRolesUserCanEdit();

			if (empty($canSetList)) {
				return false;
			}
			
			$canSetList[] = "none";

			$intersection=array_intersect($usersRoles, $canSetList);

			if (empty($intersection) && !empty($usersRoles)) {

				return false;
			}

			
		}




		return true;

	}

	public function setUserRole($role, $userId = -1) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$yourRoles = (new \ReferralManagement\UserRoles())->getUsersRoles();
		$usersRoles = (new \ReferralManagement\UserRoles())->getUsersRoles($userId);

		if (!GetClient()->isAdmin()) {

			$canSetList = $this->getPlugin()->getRolesUserCanEdit();

			if (empty($canSetList)) {
				throw new \Exception('User does not have permission to set any roles');
			}
			$canSetList[] = "none";

			if (!in_array($role, $canSetList)) {
				throw new \Exception('User cannot apply role: ' . $role . ' not in: ' . json_encode($canSetList));
			}

			if (empty(array_intersect($usersRoles, $canSetList)) && !empty($usersRoles)) {
				throw new \Exception('Target user: ' . json_encode($usersRoles) . ' is not in role that is editable by you: ' . json_encode($canSetList));
			}

		}

		$values = array();
		foreach ($this->getPlugin()->getGroupAttributes() as $r => $field) {

			if ($r === $role) {
				$values[$field] = true;
				continue;
			}

			$values[$field] = false;

		}

		$values['reviewed'] = true;

		GetPlugin('Attributes');

		(new \attributes\Record('userAttributes'))->setValues($userId, 'user', $values);

		$update = array(
			'role' => (new \ReferralManagement\UserRoles())->clearCache()->getUsersRoles($userId),
			'previous' => $usersRoles,
			'update' => $values,
		);

		$this->getPlugin()->notifier()->onUpdateUserRole((object) array_merge(array('user' => $userId, 'role' => $role), $update));
		$this->getPlugin()->cache()->needsDeviceListUpdate();
		$this->getPlugin()->cache()->needsUserListUpdate();

		return $update;

	}

	public function getUsersOnline() {

		$users = $this->getPlugin()->getClientsUserList();

		$results = GetClient()->isOnlineGroup(array_map(function ($user) {
			return $user->id;
		}, $users));



		//error_log(print_r($users[0], true));
		//error_log(print_r($results[0], true));


		return array_map(function ($onlineResult) use ($users) {


			foreach($users as $user){
				if(intval($user->id)==intval($onlineResult->id))
				{
					if($user->{'online-status'}==='invisible'){
						
						/**
						 * force online to false for users who are set to invisible 
						 */
						$onlineResult->online=false;
					}

					break;
				}
			}

			unset($onlineResult->_channel);
			return $onlineResult;

		}, $results);

	}

	public function listUsers() {
		return $this->getPlugin()->getClientsUserList();
	}

	protected function getUsersLastName($userId = -1, $default = null) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$attribs = $this->_getUserAttributes($userId);

		if ($attribs["lastName"]) {
			return $attribs["lastName"];
		}

		if ($default) {
			return $default;
		}

		return '';

	}

	protected function getUsersNumber($userId = -1, $default = null) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$attribs = $this->_getUserAttributes($userId);
		if ($attribs["phone"]) {
			return $attribs["phone"];
		}

		if ($default) {
			return $default;
		}

		return '';

	}

	public function getEmail($userId = -1, $default = null) {

		if ($userId < 1) {
			$userId = GetClient()->getUserId();
		}

		$attribs = $this->_getUserAttributes($userId);
		if ($attribs["email"]) {
			return $attribs["email"];
		}

		if ($default) {
			return $default;
		}

		return GetClient()->userMetadataFor($userId)['email'];
	}

	public function getAttributes($userId) {
		return $this->_getUserAttributes($userId);
	}

	protected function _getUserAttributes($userId) {

		if (!isset(self::$_cachedUserAttribs[$userId])) {

			//\core\DataStorage::LogQuery('Get User Attributes: ' . $userId);

			GetPlugin('Attributes');
			self::$_cachedUserAttribs[$userId] = (new \attributes\Record('userAttributes'))->getValues($userId, 'user');

		}

		return self::$_cachedUserAttribs[$userId];

	}

}