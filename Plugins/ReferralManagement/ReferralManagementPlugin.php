<?php
Authorizer();

include_once __DIR__ . '/lib/Project.php';
include_once __DIR__ . '/lib/Task.php';
include_once __DIR__ . '/lib/User.php';
include_once __DIR__ . '/lib/UserRoles.php';

class ReferralManagementPlugin extends Plugin implements
core\ViewController,
core\WidgetProvider,
core\PluginDataTypeProvider,
core\ModuleProvider,
core\TaskProvider,
core\AjaxControllerProvider,
core\DatabaseProvider,
core\EventListener {

	protected $description = 'ReferralManagement specific views, etc.';

	use core\WidgetProviderTrait;
	use core\ModuleProviderTrait;
	use core\AjaxControllerProviderTrait;
	use core\DatabaseProviderTrait;
	use core\PluginDataTypeProviderTrait;
	use core\EventListenerTrait;
	use core\TemplateRenderer;
	

	protected function onFacebookRegister($params) {

		$photoUrl = 'https://graph.facebook.com/' . $params->fbuser->id . '/picture?type=large';
		//error_log($photoUrl);
		GetPlugin('Attributes');
		$icon = '<img src="' . $photoUrl . '" />';
		(new \attributes\Record('userAttributes'))->setValues($params->user, "user", array(
			"profileIcon" => '<img src="' . $photoUrl . '" />',
			"firstName" => $params->fbuser->first_name,
			"lastName" => $params->fbuser->last_name,
		));

	}

	protected function onUpdateAttributeRecord($params) {

		if ($params->itemType === "user") {
			(new \core\LongTaskProgress())
				->emit('onTriggerUpdateUserList', array('team' => 1));
			(new \core\LongTaskProgress())
				->emit('onTriggerUpdateDeviceList', array('team' => 1));
			return;
		}

		//error_log($params->itemType);

	}



	public function getClientsUserList(){

		$cacheName="ReferralManagement.userList.json";
		$cacheData = HtmlDocument()->getCachedPage($cacheName);
		if (!empty($cacheData)) {
			$users=json_decode($cacheData);
		}else{
			$users=$this->listAllUsersMetadata();
			HtmlDocument()->setCachedPage($cacheName, json_encode($users));
		}


		$users=array_values(array_filter($users, $this->shouldShowUserFilter()));

		//TODO: throttle this
		(new \core\LongTaskProgress())->emit('onTriggerUpdateUserList', array());

		return $users;

	}

	protected function onTriggerUpdateUserList($params) {

		$cacheName = "ReferralManagement.userList.json";
		$cacheData = HtmlDocument()->getCachedPage($cacheName);

		$users = $this->listAllUsersMetadata();

		$newData = json_encode($users);
		HtmlDocument()->setCachedPage($cacheName, $newData);
		if ($newData != $cacheData) {
			$this->notifier()->onTeamUserListChanged($params->team);
		}

	}


	public function getClientsDeviceList(){

		$cacheName="ReferralManagement.deviceList.json";
		$cacheData = HtmlDocument()->getCachedPage($cacheName);
		if (!empty($cacheData)) {
			$devices=json_decode($cacheData);
		}else{
			$devices=$this->listAllDevicesMetadata();
			HtmlDocument()->setCachedPage($cacheName, json_encode($devices));
		}

		$devices=array_values(array_filter($devices, $this->shouldShowDeviceFilter()));

		//TODO: throttle this
		(new \core\LongTaskProgress())->emit('onTriggerUpdateDevicesList', array());

		return $devices;
	}

	protected function onTriggerUpdateDevicesList($params) {

		$cacheName = "ReferralManagement.deviceList.json";
		$cacheData = HtmlDocument()->getCachedPage($cacheName);

		$devices = $this->listAllDevicesMetadata();

		$newData = json_encode($devices);
		HtmlDocument()->setCachedPage($cacheName, $newData);
		if ($newData != $cacheData) {
			$this->notifier()->onTeamDeviceListChanged($params->team);
		}

	}

	protected function onCreateUser($params) {
		foreach ($this->listTeams() as $team) {
			(new \core\LongTaskProgress())
				->emit('onTriggerUpdateUserList', array('team' => $team));
		}
	}
	protected function onDeleteUser($params) {
		foreach ($this->listTeams() as $team) {
			(new \core\LongTaskProgress())
				->emit('onTriggerUpdateUserList', array('team' => $team));
		}
	}

	protected function listTeams($fn) {
		return (new \ReferralManagement\User())->listTeams();
	}

	protected function onTriggerImportTusFile($params) {

		include_once __DIR__ . '/lib/TusImportTask.php';
		return (new \ReferralManagement\TusImportTask())->import($params);

	}

	/**
	 * returns an indexed array of available tasks and method names.
	 * array keys should be task names, and values should correspond to task methods defined within the
	 * plugin class.
	 */
	public function getTaskMap() {
		return array(
			'layer.upload' => array(
				'access' => 'public',
				'method' => 'taskUploadlayer',
			),
		);
	}

	/**
	 * returns activity feed object for submitting activity actions
	 *
	 * @return \ReferralManagement\ActivityFeed
	 */
	public function notifier() {
		include_once __DIR__ . '/lib/Notifications.php';
		return (new \ReferralManagement\Notifications());
	}

	protected function taskUploadlayer() {
		GetUserFiles();
		GetPlugin('Maps');

		if (($path = GetUserFiles()->getUploader()->uploadFile(
			array(
				'kml',
				'kmz',
				'zip',
				'shp',
			))) && $path != "") {

			include_once MapsPlugin::Path() . DS . 'lib' . DS . 'SpatialFile.php';

			$kmlDoc = substr($path, 0, strrpos($path, '.')) . '.kml';

			SpatialFile::Save(SpatialFile::Open($path), $kmlDoc);

			Emit('onUploadSpatialFile', array(
				'path' => $kmlDoc,
			));

			$this->setParameter('layer', $kmlDoc);
			return true;

		}

		return $this->setTaskError(
			array(
				'Upload Failed',
				GetUserFiles()->getUploader()->lastError(),
			));

	}

	public function includeScripts() {


		IncludeJSBlock(function(){
			?><script type="text/javascript">
				
				var Community={
					domain:<?php 

					$domain=HtmlDocument()->getDomain();
					echo json_encode(substr($domain, 0, strpos($domain, '.')));

					?>,
					collective:<?php echo json_encode($this->communityCollective()); ?>,
					teams:[<?php echo json_encode($this->communityCollective()); ?>],
					territories:<?php echo json_encode($this->listTerritories()); ?>,
					communities:<?php echo json_encode($this->listCommunities()); ?>

				}


			</script><?php
		});

		IncludeJS(__DIR__ . '/js/ReferralManagementDashboard.js');
		IncludeJS(__DIR__ . '/js/ReferralManagementUser.js');
		IncludeJS(__DIR__ . '/js/UserTeamCollection.js');
		IncludeJS(__DIR__ . '/js/Proposal.js');
		IncludeJS(__DIR__ . '/js/ProjectTeam.js');
		IncludeJS(__DIR__ . '/js/ProjectCalendar.js');
		IncludeJS(__DIR__ . '/js/TaskItem.js');
	}

	protected function onCreateProposal($params) {

		$this->createDefaultProposalTasks($params->id);

	}

	protected function onActivateEmailForGuestProposal($params) {

		if (key_exists('validationData', $params) && key_exists('token', $params->validationData)) {
			$links = GetPlugin('Links');
			$tokenInfo = $links->peekDataToken($params->validationData->token);
			$data = $tokenInfo->data;

			$database = $this->getDatabase();

			if (($id = (int) $database->createProposal(array(
				'user' => GetClient()->getUserId(),
				'metadata' => json_encode(array("email" => $params->validationData->email)),
				'createdDate' => ($now = date('Y-m-d H:i:s')),
				'modifiedDate' => $now,
				'status' => 'active',
			)))) {

				$this->notifier()->onGuestProposal($id, $params);

				GetPlugin('Attributes');
				if (key_exists('attributes', $data->proposalData)) {
					foreach ($data->proposalData->attributes as $table => $fields) {
						(new attributes\Record($table))->setValues($id, 'ReferralManagement.proposal', $fields);
					}
				}

				Emit('onCreateProposalForGuest', array(
					'params' => $params,
					'proposalData' => $data,
				));

				Emit('onCreateProposal', array('id' => $id));

			}

		}

	}

	protected function onPost($params) {

		include_once __DIR__ . '/lib/CommentBot.php';

		(new \ReferralManagement\CommentBot())
			->scanPostForEventTriggers($params);

	}
	public function getActiveProjectList() {

		return $this->getProjectList(array('status' => array('value' => 'archived', 'comparator' => '!=')));

	}
	public function getArchivedProjectList() {

		return $this->getProjectList(array('status' => 'archived'));

	}
	public function getProjectList($filter = array()) {

		if (!Auth('memberof', 'lands-department', 'group')) {
			return array();
		}

		return $this->getAllProjectsList($filter);

	}

	protected function getAllProjectsList($filter = array()){

		$database = $this->getDatabase();
		$results = $database->getAllProposals($filter);

		return array_values(array_filter(array_map(function ($result) {

			$project = $this->analyze('formatProjectResult.' . $result->id, function () use ($result) {

				return (new \ReferralManagement\Project())
					->fromRecord($result)
					->toArray();
			});
			$project['profileData'] = $this->getLastAnalysis();
			$project['visible'] = $this->shouldShowProjectFilter()($project);

			return $project;

		}, $results), function ($project) {return !!$project['visible'];}));
	}

	protected function availableProjectPermissions() {

		return array(
			'adds-tasks',
			'assigns-tasks',
			'adds-members',
			'sets-roles',
			'recieves-notifications',
		);
	}

	public function defaultProjectPermissionsForUser($user, $project) {

		if (is_numeric($user)) {
			$user = $this->getUsersMetadata(GetClient()->userMetadataFor($user));
		}

		if (is_numeric($project)) {
			$project = $this->getProposalData($project);
		}

		if (is_object($project)) {
			$project = get_object_vars($project);
		}

		if ($user['id'] == $project['user']) {
			return $this->availableProjectPermissions();
		}

		if (in_array('lands-department', $roles = $this->getRolesUserCanEdit($user['id']))) {
			return array_merge($this->availableProjectPermissions());
		}

		return array(
			'adds-tasks',
			'recieves-notifications',
		);

	}

	protected function usersProjectPermissions() {
		return $this->availableProjectPermissions();
	}

	public function getTeamMembersForProject($project, $attributes = null) {

		include_once __DIR__ . '/lib/Teams.php';
		return (new \ReferralManagement\Teams())->listMembersOfProject($project, $attributes);
	}

	public function getTeamMembersForTask($task, $attributes = null) {

		include_once __DIR__ . '/lib/Teams.php';
		return (new \ReferralManagement\Teams())->listMembersOfTask($task, $attributes);

	}

	private function setTeamMembersForTask($tid, $teamMembers) {

		GetPlugin('Attributes');
		(new attributes\Record('taskAttributes'))->setValues($tid, 'Tasks.task', array(
			'teamMembers' => array_map(function ($item) {
				if (is_numeric($item)) {
					return $item;
				}
				return json_encode($item);
			}, $teamMembers),
		));

		Emit('onSetTeamMembersForTask', array(
			'task' => $tid,
			'team' => $teamMembers,
		));

	}

	protected function onTriggerProjectUpdateEmailNotification($args) {

		$teamMembers = $this->getTeamMembersForProject($args->project->id);

		if (empty($teamMembers)) {
			Emit('onEmptyTeamMembersTask', $args);
		}

		foreach ($teamMembers as $user) {

			$to = $this->emailToAddress($user, "recieves-notifications");
			if (!$to) {
				continue;
			}

			GetPlugin('Email')->getMailerWithTemplate('onProjectUpdate', array_merge(
				get_object_vars($args),
				array(
					'teamMembers' => $teamMembers,
					'editor' => $this->getUsersMetadata(),
					'user' => $this->getUsersMetadata($user->id),
				)))
				->to($to)
				->send();

		}
	}

	protected function emailToAddress($user, $permissionName = '') {

		$shouldSend = false;
		if (empty($permissionName)) {
			$shouldSend = true;
		}

		if (!empty($permissionName)) {
			if (in_array($permissionName, $user->permissions)) {
				$shouldSend = true;
			}
		}

		Emit("onCheckEmailPermission", array_merge(get_object_vars($user), array(
			'shouldSend' => $shouldSend,
			'permission' => $permissionName,
		)));

		if (!$this->getParameter('enableEmailNotifications')) {
			return 'nickblackwell82@gmail.com';
		}

		$addr = (new \ReferralManagement\User())->getEmail($user->id);
		return $addr;

	}

	protected function onTriggerTaskUpdateEmailNotification($args) {

		if ($args->task->itemType !== "ReferralManagement.proposal") {
			Emit('onNotProposalTask', $args);
			return;
		}

		$project = $this->getProposalData($args->task->itemId);
		$teamMembers = $this->getTeamMembersForProject($project);
		$assignedMembers = $this->getTeamMembersForTask($args->task->id);

		if (empty($teamMembers)) {
			Emit('onEmptyTeamMembersTask', $args);
		}

		foreach ($teamMembers as $user) {

			$to = $this->emailToAddress($user, "recieves-notifications");
			if (!$to) {
				continue;
			}

			GetPlugin('Email')->getMailerWithTemplate('onTaskUpdate', array_merge(
				get_object_vars($args),
				array(
					'project' => $project,
					'teamMembers' => $teamMembers,
					'assignedMembers' => $assignedMembers,
					'editor' => $this->getUsersMetadata(),
					'user' => $this->getUsersMetadata($user->id),
				)))
				->to('nickblackwell82@gmail.com')
				->send();

		}

	}

	public function addTeamMemberToProject($user, $project) {

		$teamMembers = $this->getTeamMembersForProject($project);

		$member = (object) array('id' => $user, 'permissions' => $this->defaultProjectPermissionsForUser($user, $project));
		$teamMembers[] = $member;
		$teamMembers = $this->_uniqueIds($teamMembers);

		Emit('onAddTeamMemberToProject', array(
			'project' => $project,
			'member' => $member,
		));

		$this->setTeamMembersForProject($project, $teamMembers);

		$this->notifier()->onAddTeamMemberToProject($user, $project);

		return $teamMembers;

	}

	protected function onAddTeamMemberToProject($args) {

		GetPlugin('Email')->getMailerWithTemplate('onAddTeamMemberToProject', array_merge(
			get_object_vars($args),
			array(
				'editor' => $this->getUsersMetadata(),
				'user' => $this->getUsersMetadata($args->member->id),
				'project' => $this->getProposalData($args->project),
			)
		))
			->to('nickblackwell82@gmail.com')
			->send();

	}

	protected function onRemoveTeamMemberFromProject($args) {

		GetPlugin('Email')->getMailerWithTemplate('onRemoveTeamMemberFromProject', array_merge(
			get_object_vars($args),
			array(
				'editor' => $this->getUsersMetadata(),
				'user' => $this->getUsersMetadata($args->member->id),
				'project' => $this->getProposalData($args->project),
			)
		))
			->to('nickblackwell82@gmail.com')
			->send();

	}

	public function removeTeamMemberFromProject($user, $project) {

		$teamMembers = $this->getTeamMembersForProject($project);

		$teamMembers = array_filter($teamMembers, function ($item) use ($user, $project) {

			if (($item == $user || $item->id == $user)) {

				Emit('onRemoveTeamMemberFromProject', array(
					'project' => $project,
					'member' => $item,
				));
				return false;
			}

			return true;
		});

		$this->setTeamMembersForProject($project, $teamMembers);
		$this->notifier()->onRemoveTeamMemberFromProject($user, $project);

		return $teamMembers;

	}

	public function setTeamMembersForProject($pid, $teamMembers) {

		GetPlugin('Attributes');
		(new attributes\Record('proposalAttributes'))->setValues($pid, 'ReferralManagement.proposal', array(
			'teamMembers' => array_map(function ($item) {
				if (is_numeric($item)) {
					return $item;
				}
				return json_encode($item);
			}, $teamMembers),
		));

		Emit('onSetTeamMembersForProject', array(
			'project' => $pid,
			'team' => $teamMembers,
		));

	}

	private function _uniqueIds($list) {
		$ids = array();
		$items = array();
		foreach ($list as $item) {

			if (!in_array($item->id, $ids)) {
				$ids[] = $item->id;
				$items[] = $item;
			}

		}

		return $items;
	}

	public function addTeamMemberToTask($user, $task) {

		$teamMembers = $this->getTeamMembersForTask($task);

		$member = (object) array('id' => $user);
		$teamMembers[] = $member;

		Emit('onAddTeamMemberToTask', array(
			'task' => $task,
			'member' => $member,
		));

		$teamMembers = $this->_uniqueIds($teamMembers);

		$this->setTeamMembersForTask($task, $teamMembers);

		$this->notifier()->onAddTeamMemberToTask($user, $task);

		return $teamMembers;

	}

	protected function onAddTeamMemberToTask($args) {

		GetPlugin('Email')->getMailerWithTemplate('onAddTeamMemberToTask', array_merge(
			get_object_vars($args),
			array(
				'editor' => $this->getUsersMetadata(),
				'user' => $this->getUsersMetadata($args->member->id),
			)
		))
			->to('nickblackwell82@gmail.com')
			->send();

	}
	protected function onRemoveTeamMemberFromTask($args) {

		GetPlugin('Email')->getMailerWithTemplate('onRemoveTeamMemberFromTask', array_merge(
			get_object_vars($args),
			array(
				'editor' => $this->getUsersMetadata(),
				'user' => $this->getUsersMetadata($args->member->id),
			)
		))
			->to('nickblackwell82@gmail.com')
			->send();

	}

	public function removeTeamMemberFromTask($user, $task) {

		$teamMembers = $this->getTeamMembersForTask($task);

		$teamMembers = array_filter($teamMembers, function ($item) use ($user, $task) {

			if ($item == $user || $item->id == $user) {
				Emit('onRemoveTeamMemberFromTask', array(
					'task' => $task,
					'member' => $item,
				));
				return false;
			}
			return true;
		});

		$this->setTeamMembersForTask($task, $teamMembers);

		$this->notifier()->onRemoveTeamMemberFromTask($user, $task);

		return $teamMembers;

	}

	public function getTaskData($id) {

		return (new \ReferralManagement\Task())
			->fromId($id)
			->toArray();
	}

	public function getProposalData($id) {

		return (new \ReferralManagement\Project())
			->fromId($id)
			->toArray();
	}

	/**
	 * Used in custom user auth
	 */
	public function isUserInGroup($role) {
		return (new \ReferralManagement\UserRoles())->userHasRole($role);
	}

	public function getGroupMembersOfGroup($group) {

		$map = (new \ReferralManagement\UserRoles())->listRoles();

		$i = array_search($group, $map);
		if ($i !== false) {
			return array_slice($map, 0, $i + 1);
		}

		return array();
	}

	/**
	 * used in custom style script
	 */
	public function getRoleIcons() {
		return (new \ReferralManagement\UserRoles())->listRoleIcons();
	}

	public function getUserRoles($id = -1) {
		return (new \ReferralManagement\UserRoles())->getUsersRoles($id);
	}

	public function getRolesUserCanEdit($id = -1) {
		return (new \ReferralManagement\UserRoles())->getRolesUserCanEdit($id);
	}

	public function getGroupAttributes() {
		return (new \ReferralManagement\UserRoles())->listRoleAttributes();
	}

	public function getRoles() {
		return (new \ReferralManagement\UserRoles())->listRoles();
	}

	public function getUserAttributes($userId) {
		return (new \ReferralManagement\User())->getAttributes($userId);
	}

	public function getUsersMetadata($id = -1) {
		return (new \ReferralManagement\User())->getMetadata($id);
	}

	/**
	 * return a closure
	 */
	public function shouldShowUserFilter() {

		$roles = (new \ReferralManagement\UserRoles());
		$managerRoles = $roles->listManagerRoles();
		if (GetClient()->isAdmin()) {

			//show all users;
			return function (&$userMetadata) {
				$userMetadata->visibleBecuase = "You are admin";
				return true;
			};

		}

		$clientMetadata = $this->getUsersMetadata(GetClient()->getUserId());
		$groupCommunity = $this->communityCollective();

		if (!$roles->userHasAnyOfRoles($roles->listManagerRoles())) {

			//non managers can only see 'collective/groupCommunity' users (ie wabun) and thier own community users'

			return function ($userMetadata) use ($clientMetadata, $groupCommunity) {

				if ($clientMetadata['community'] === $groupCommunity ) {
					$userMetadata->visibleBecuase = "your ".$groupCommunity;
					return true;
				}

				if (/*$userMetadata->community === $groupCommunity ||*/ $userMetadata->community === $clientMetadata['community']) {
					$userMetadata->visibleBecuase = "same community";
					return true;
				}

				return false;
			};

		}

		return function ($userMetadata) use ($clientMetadata, $managerRoles, $groupCommunity) {


			if ($clientMetadata['community'] === $groupCommunity ) {
				$userMetadata->visibleBecuase = "your admin/".$groupCommunity;
				return true;
			}

			// 
			// if ($userMetadata->community === $groupCommunity) {
			// 	$userMetadata->visibleBecuase = "they're wabun";
			// 	return true;
			// }

			if ($userMetadata->community === $clientMetadata['community']) {
				$userMetadata->visibleBecuase = "Same community";
				return true;
			}

			if (count(array_intersect($managerRoles, $userMetadata->roles)) > 0) {
				$userMetadata->visibleBecuase = "You are both managers";
				return true;
			}

			return false;
		};
	}

	public function shouldShowProjectFilter() {

		$clientId = GetClient()->getUserId();

		if (!Auth('memberof', 'lands-department-manager', 'group')) {

			return function (&$item) use ($clientId) {

				if ($item['user'] == $clientId) {
					$item['visibleBecuase'] = "You created";
					return true;
				}

				if (in_array($clientId, $item['attributes']['teamMemberIds'])) {
					$item['visibleBecuase'] = "You are a team member";
					return true;
				}

				return false;

			};
		}

		$clientMetadata = $this->getUsersMetadata(GetClient()->getUserId());
		//$groupCommunity=$this->communityCollective();

		/**
		 * Lands Dept Managers+
		 */

		return function (&$item) use ($clientId, $clientMetadata) {

			$nationsInvolved=$item['attributes']['firstNationsInvolved'];
			if(empty($nationsInvolved)){
				$nationsInvolved=array();
			}

			$nationsInvolved=array_map(function ($community) {return strtolower($community);}, $nationsInvolved);
			
			$collective=$this->communityCollective();
			if(!in_array($collective, $nationsInvolved)){
				$nationsInvolved[]=$collective;
			}



			if (in_array(strtolower($clientMetadata['community']), $nationsInvolved)) {
				//error_log("Your community is involved ".$item['id']);
				$item['visibleBecuase'] = "Your community is involved";
				return true;
			}

			if ($item['user'] == $clientId) {
				$item['visibleBecuase'] = "You created";
				return true;
			}

			if (in_array($clientId, $item['attributes']['teamMemberIds'])) {
				$item['visibleBecuase'] = "You are a team member";
				return true;
			}

			return false;
		};


	}

	public function shouldShowDeviceFilter() {
		return $this->shouldShowUserFilter();
	}

	public function listCommunities() {
		return (new \ReferralManagement\User())->listCommunities();
	}
	public function communityCollective() {
		return (new \ReferralManagement\User())->communityCollective();
	}
	public function listTerritories() {
		return (new \ReferralManagement\User())->listTerritories();
	}

	public function getLayersForGroup($name) {
		$config = new core\Configuration('layerGroups');
		return $config->getParameter($name, array());
	}
	public function getMouseoverForGroup($name) {
		$config = new core\Configuration('iconset');
		return $config->getParameter($name . "Mouseover", "{configuration.iconset." . $name . "Mouseover}");
	}

	public function getDefaultProposalTaskTemplates($proposal) {
		include_once __DIR__ . '/lib/DefaultTasks.php';
		return (new \ReferralManagement\DefaultTasks())->getTemplatesForProposal($proposal);
	}
	public function createDefaultProposalTasks($proposal) {
		include_once __DIR__ . '/lib/DefaultTasks.php';
		return (new \ReferralManagement\DefaultTasks())->createTasksForProposal($proposal);
	}

	public function listAllUsersMetadata() {

		$list = array_values(array_filter(GetClient()->listUsers(), function ($u) {
			return !$this->_isDevice($u);
		}));

		return array_map(function ($u) {

			//die(json_encode($u));

			$user = $this->getUsersMetadata($u);
			return $user;

		}, $list);

	}

	public function listAllDevicesMetadata() {

		$list = array_values(array_filter(GetClient()->listUsers(), function ($u) {
			//prefilter
			return $this->_isDevice($u);
		}));

		return array_map(function ($u) {


			$user = $this->getUsersMetadata($u);

			$user['devices']=GetPlugin('Apps')->getUsersDeviceIds($u['id']);
			return $user;

		}, $list);

	}

	protected function _isDevice($user) {
		return strpos($user['email'], 'device.') === 0;
	}

}
