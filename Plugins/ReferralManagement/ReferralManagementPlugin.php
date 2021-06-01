<?php
Authorizer();

include_once __DIR__ . '/lib/Project.php';
include_once __DIR__ . '/lib/Task.php';
include_once __DIR__ . '/lib/User.php';
include_once __DIR__ . '/lib/UserRoles.php';

class ReferralManagementPlugin extends Plugin implements
core\ViewController,
core\WidgetProvider,
core\DataTypeProvider,
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
	use core\DataTypeProviderTrait;
	use core\EventListenerTrait;
	use core\TemplateRenderer;

	public function formatMobileConfig($parameters) {

		$parameters['client'] = GetPlugin('ReferralManagement')->getUsersMetadata();
		$parameters['communities'] = GetPlugin('ReferralManagement')->listCommunities();

		return $parameters;

	}

	protected function onCreateProposal($params) {

		$config = GetWidget('dashboardConfig');
		if ($config->getParameter("autoCreateDefaultTasks", false)) {
			$this->createDefaultProposalTasks($params->id);
		}

		$this->onUpdateProposal($params);

	}

	protected function onUpdateProposal($params) {

		Throttle('onTriggerVersionControlProject', $params, array('interval' => 30), 60);

	}

	protected function onTriggerVersionControlProject($params) {

		sleep(5);
		include_once __DIR__ . '/lib/VersionControl.php';
		(new \ReferralManagement\VersionControl())->updateProject($params->id);

	}

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

	protected function onActivateMobileDevice($params) {

		$user = $params->account->uid;
		$config = GetWidget('dashboardConfig');

		if ($config->getParameter('autoApproveMobileCommunity') || $config->getParameter('autoApproveMobileCommunityOnce')) {

			GetPlugin('Attributes');
			(new attributes\Record('userAttributes'))->setValues($user, 'user', array(
				"community-member" => true,
				"community" => (new \ReferralManagement\User())->communityCollective(),
			));
			//$this->getPlugin()->notifier()->onUpdateUserRole($json);
		}

	}

	protected function onUpdateAttributeRecord($params) {

		if ($params->itemType === "user") {
			(new \core\LongTaskProgress())
				->throttle('onTriggerUpdateUserList', array('team' => 1), array('interval' => 30));
			(new \core\LongTaskProgress())
				->throttle('onTriggerUpdateDevicesList', array('team' => 1), array('interval' => 30));
			return;
		}

		//error_log($params->itemType);

	}

	public function getClientsUserList() {
		$users = $this->cache()->getUsersMetadataList();
		$users = array_values(array_filter($users, $this->shouldShowUserFilter()));
		return $users;
	}

	protected function onTriggerUpdateUserList($params) {
		$this->cache()->cacheUsersMetadataList($params);
	}

	public function getClientsDeviceList() {

		$devices = $this->cache()->getDevicesMetadataList();
		$devices = array_values(array_filter($devices, $this->shouldShowUserFilter()));
		return $devices;
	}

	protected function onTriggerUpdateDevicesList($params) {

		$this->cache()->cacheDevicesMetadataList($params);

	}

	protected function onCreateUser($params) {
		foreach ($this->listTeams() as $team) {
			(new \core\LongTaskProgress())
				->throttle('onTriggerUpdateUserList', array('team' => $team), array('interval' => 30));
		}
	}

	protected function onDeleteUser($params) {
		foreach ($this->listTeams() as $team) {
			(new \core\LongTaskProgress())
				->throttle('onTriggerUpdateUserList', array('team' => $team), array('interval' => 30));
		}
	}

	protected function listTeams($fn = null) {
		return (new \ReferralManagement\User())->listTeams();
	}

	protected function onTriggerImportTusFile($params) {

		Emit('onImportTusFile', array());

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
	public function cache() {
		include_once __DIR__ . '/lib/ListItemCache.php';
		return (new \ReferralManagement\ListItemCache());
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

			$type = '[DoCuMeNt]';
			if (strpos($path, $type) !== false) {
				$type = "";
			}

			$kmlDoc = substr($path, 0, strrpos($path, '.')) . $type . '.kml';
			file_put_contents($kmlDoc . '.info.json', json_encode(array(
				'source' => basename($path),
			)));

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

		IncludeJSBlock(function () {
			?><script type="text/javascript">

				var Community={
					domain:<?php

			$domain = HtmlDocument()->getDomain();
			echo json_encode(substr($domain, 0, strpos($domain, '.')));

			?>,
					collective:<?php echo json_encode($this->communityCollective()); ?>,
					teams:[<?php echo json_encode($this->communityCollective()); ?>],
					territories:<?php echo json_encode($this->listTerritories()); ?>,
					communities:<?php echo json_encode($this->listCommunities()); ?>

				}


			</script><?php
});

		IncludeJS(__DIR__ . '/js/DashboardConfig.js');
		IncludeJS(__DIR__ . '/js/DashboardPageLayout.js');
		IncludeJS(__DIR__ . '/js/ReferralManagementDashboard.js');
		IncludeJS(__DIR__ . '/js/DashboardLoader.js');
		IncludeJS(__DIR__ . '/js/UILeftPanel.js');
		IncludeJS(__DIR__ . '/js/UIInteraction.js');
		IncludeJS(__DIR__ . '/js/OrganizationalUnit.js');
		IncludeJS(__DIR__ . '/js/NamedCategory.js');
		IncludeJS(__DIR__ . '/js/NamedCategoryList.js');

		IncludeJS(__DIR__ . '/js/GuestNavigationMenu.js');

		IncludeJS(__DIR__ . '/js/ProjectSelection.js');
		IncludeJS(__DIR__ . '/js/UserNotifications.js');

		IncludeJS(__DIR__ . '/js/UserGroups.js');
		IncludeJS(__DIR__ . '/js/ConfigItem.js');
		IncludeJS(__DIR__ . '/js/HtmlContent.js');

		IncludeJS(__DIR__ . '/js/SpatialProject.js');
		IncludeJS(__DIR__ . '/js/SpatialDocumentPreview.js');
		IncludeJS(__DIR__ . '/js/ProjectLayer.js');

		IncludeJS(__DIR__ . '/js/MainNavigationMenu.js');
		IncludeJS(__DIR__ . '/js/ProjectsOverviewNavigationMenu.js');
		IncludeJS(__DIR__ . '/js/ProjectNavigationMenu.js');
		IncludeJS(__DIR__ . '/js/ProfileNavigationMenu.js');
		IncludeJS(__DIR__ . '/js/MapNavigationMenu.js');
		IncludeJS(__DIR__ . '/js/DashboardUser.js');
		IncludeJS(__DIR__ . '/js/MobileDeviceList.js');

		IncludeJS(__DIR__ . '/js/ProjectQueries.js');

		IncludeJS(__DIR__ . '/js/ItemCollection.js');
		IncludeJS(__DIR__ . '/js/ItemUsersCollection.js');
		IncludeJS(__DIR__ . '/js/ItemProjectsCollection.js');
		IncludeJS(__DIR__ . '/js/ItemTasksCollection.js');
		IncludeJS(__DIR__ . '/js/ItemPending.js');
		IncludeJS(__DIR__ . '/js/ItemArchive.js');
		IncludeJS(__DIR__ . '/js/ItemDeadline.js');
		IncludeJS(__DIR__ . '/js/ItemAttachments.js');
		IncludeJS(__DIR__ . '/js/ItemFlags.js');
		IncludeJS(__DIR__ . '/js/ItemEvents.js');
		IncludeJS(__DIR__ . '/js/ItemStars.js');
		IncludeJS(__DIR__ . '/js/ItemDiscussion.js');
		IncludeJS(__DIR__ . '/js/ItemContact.js');
		IncludeJS(__DIR__ . '/js/ItemNavigationTagLinks.js');
		IncludeJS(__DIR__ . '/js/ItemCategories.js');

		IncludeJS(__DIR__ . '/js/Project.js');

		IncludeJS(__DIR__ . '/js/GuestProject.js');

		IncludeJS(__DIR__ . '/js/Dataset.js');

		IncludeJS(__DIR__ . '/js/ProjectList.js');
		IncludeJS(__DIR__ . '/js/ProjectTeam.js');
		IncludeJS(__DIR__ . '/js/ProjectCalendar.js');
		IncludeJS(__DIR__ . '/js/ProjectActivityChart.js');
		IncludeJS(__DIR__ . '/js/ProjectFilesNavigationMenu.js');
		IncludeJS(__DIR__ . '/js/ProjectFiles.js');
		IncludeJS(__DIR__ . '/js/TaskItem.js');
		IncludeJS(__DIR__ . '/js/RecentItems.js');
		IncludeJS(__DIR__ . '/js/ProjectMap.js');
		IncludeJS(__DIR__ . '/js/ProjectSearch.js');
		IncludeJS(__DIR__ . '/js/PostContent.js');
		IncludeJS(__DIR__ . '/js/UserIcon.js');
		IncludeJS(__DIR__ . '/js/LayerGroup.js');
		IncludeJS(__DIR__ . '/js/LayerGroupLegend.js');

		if (GetClient()->isAdmin()) {
			IncludeJS(__DIR__ . '/js/AdminMonitor.js');
		}

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
	public function getActiveProjectList($filter = array()) {

		return $this->getProjectList(array_merge($filter, array('status' => array('value' => 'archived', 'comparator' => '!='))));

	}
	public function getArchivedProjectList($filter = array()) {

		return $this->getProjectList(array_merge($filter, array('status' => 'archived')));

	}
	public function getProjectList($filter = array()) {

		if (!Auth('memberof', 'lands-department', 'group')) {
			return array();
		}

		if ($this->getParameter('enableProjectListCaching')) {

			$list = $this->cache()->getProjectsMetadataList($filter);
		} else {
			$list = $this->listProjectsMetadata($filter);
		}

		//
		return array_values(array_filter(array_map(function ($project) {

			$project->visible = $this->shouldShowProjectFilter()($project);
			return $project;

		}, $list), function ($project) {return !!$project->visible;}));

	}

	protected function onTriggerUpdateProjectList($params) {
		$this->cache()->cacheProjectsMetadataList($params->filter);
	}

	public function listProjectsMetadata($filter) {

		$database = GetPlugin('ReferralManagement')->getDatabase();
		$results = $database->getAllProposals($filter);

		return array_map(function ($result) {

			$project = $this->analyze('formatProjectResult.' . $result->id, function () use ($result) {

				return (new \ReferralManagement\Project())
					->fromRecord($result)
					->toArray();
			});
			$project['profileData'] = $this->getLastAnalysis();
			//$project['visible'] = $this->shouldShowProjectFilter()($project);

			return (object) $project;

		}, $results);

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

	public function getChildProjectsForProject($pid, $attributes = null) {

		include_once __DIR__ . '/lib/Project.php';
		return (new \ReferralManagement\Project())->fromId($pid)->toArray()['attributes']['childProjects'];
	}

	public function setChildProjectsForProject($pid, $childProjects) {

		GetPlugin('Attributes');
		(new attributes\Record('proposalAttributes'))->setValues($pid, 'ReferralManagement.proposal', array(
			'childProjects' => json_encode($childProjects),
		));

		Emit('onSetChildProjectsForProject', array(
			'project' => $pid,
			'childProjects' => $childProjects,
		));

	}

	public function addProjectToProject($child, $project) {

		$childProjects = $this->getChildProjectsForProject($project);

		$childProjects[] = $child;
		$childProjects = array_unique($childProjects);

		Emit('onAddProjectToProject', array(
			'project' => $project,
			'child' => $child,
		));

		$this->setChildProjectsForProject($project, $childProjects);

		//$this->notifier()->onAddTeamMemberToProject($user, $project);

		return $childProjects;

	}

	public function removeProjectFromProject($child, $project) {

		$childProjects = $this->getChildProjectsForProject($project);

		$childProjects = array_filter($childProjects, function ($item) use ($child, $project) {

			if (($item == $child)) {

				Emit('onRemoveTeamMemberFromProject', array(
					'project' => $project,
					'member' => $item,
				));
				return false;
			}

			return true;
		});

		$this->setChildProjectsForProject($project, $childProjects);
		//$this->notifier()->onRemoveTeamMemberFromProject($user, $project);

		return $childProjects;

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

		\core\DataStorage::LogQuery("Create User Filter");

		$roles = (new \ReferralManagement\UserRoles());
		$managerRoles = $roles->listManagerRoles();
		if (GetClient()->isAdmin()) {

			//show all users;
			return function (&$userMetadata) {

				if (is_array($userMetadata)) {
					$userMetadata['visibleBecuase'] = "You are admin";
				}

				if (is_object($userMetadata)) {
					$userMetadata->visibleBecuase = "You are admin";
				}

				return true;
			};

		}

		$clientMetadata = $this->getUsersMetadata(GetClient()->getUserId());
		$groupCommunity = $this->communityCollective();

		if (!$roles->userHasAnyOfRoles($roles->listManagerRoles())) {

			//non managers can only see 'collective/groupCommunity' users (ie wabun) and thier own community users'

			return function ($userMetadata) use ($clientMetadata, $groupCommunity) {

				if ($clientMetadata['community'] === $groupCommunity) {
					$userMetadata->visibleBecuase = "your " . $groupCommunity;
					return true;
				}

				if ( /*$userMetadata->community === $groupCommunity ||*/$userMetadata->community === $clientMetadata['community']) {
					$userMetadata->visibleBecuase = "same community";
					return true;
				}

				return false;
			};

		}

		return function ($userMetadata) use ($clientMetadata, $managerRoles, $groupCommunity) {

			if ($clientMetadata['community'] === $groupCommunity) {
				$userMetadata->visibleBecuase = "your admin/" . $groupCommunity;
				return true;
			}

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

		\core\DataStorage::LogQuery("Create Project Filter");

		$clientId = GetClient()->getUserId();

		if (!Auth('memberof', 'lands-department-manager', 'group')) {

			return function (&$item) use ($clientId) {

				if ($item->user == $clientId) {
					$item->visibleBecuase = "You created";
					return true;
				}

				if (in_array($clientId, $item->attributes->teamMemberIds)) {
					$item->visibleBecuase = "You are a team member";
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

			$nationsInvolved = $item->attributes->firstNationsInvolved;
			if (empty($nationsInvolved)) {
				$nationsInvolved = array();
			}

			$nationsInvolved = array_map(function ($community) {return strtolower($community);}, $nationsInvolved);

			$collective = $this->communityCollective();
			if (!in_array($collective, $nationsInvolved)) {
				$nationsInvolved[] = $collective;
			}

			if (in_array(strtolower($clientMetadata['community']), $nationsInvolved)) {
				//error_log("Your community is involved ".$item['id']);
				$item->visibleBecuase = "Your community is involved";
				return true;
			}

			if ($item->user == $clientId) {
				$item->visibleBecuase = "You created";
				return true;
			}

			if (in_array($clientId, $item->attributes->teamMemberIds)) {
				$item->visibleBecuase = "You are a team member";
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

			$user['devices'] = GetPlugin('Apps')->getUsersDeviceIds($u['id']);
			return $user;

		}, $list);

	}

	protected function _isDevice($user) {
		return strpos($user['email'], 'device.') === 0;
	}

}
