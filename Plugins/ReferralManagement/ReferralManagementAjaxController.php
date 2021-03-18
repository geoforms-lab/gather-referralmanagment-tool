<?php

class ReferralManagementAjaxController extends core\AjaxController implements core\PluginMember {
	use core\PluginMemberTrait;

	protected function uploadTus($json) {

		if (count($json->data) == 0) {
			return $this->setError('Empty data set');
		}

		return array(
			'subscription' => (new \core\LongTaskProgress())
				->emit('onTriggerImportTusFile', array('data' => $json->data))
				->getSubscription(),
		);

	}

	protected function getDashboardConfig($json) {

		return array('parameters'=>array_merge(
			GetWidget('dashboardConfig')->getConfigurationValues(), 
			GetWidget('dashboardContentConfig')->getConfigurationValues()
		));
	}

	protected function getUserRoles($json) {

		include_once __DIR__ . '/lib/UserRoles.php';

		return array(
			'roles'=>(new \ReferralManagement\UserRoles())->listRoles(),
			'icons'=>(new \ReferralManagement\UserRoles())->listRoleIcons()
		);
	}


	protected function listLayerItems($json){

		return GetWidget('layerGroups')->getAjaxController()->executeTask('get_configuration', $json);

	}
	
	protected function saveTeamMemberPermissions($json) {

		//$projectData=$this->getPlugin()->getProposal($json->project);

		$teamMembers = $this->getPlugin()->getTeamMembersForProject($json->project);

		$teamMembers = array_map(function ($item) use ($json) {

			if ($item->id == $json->id) {
				$item->permissions = $json->permissions;
			}
			return $item;

		}, $teamMembers);

		$this->getPlugin()->setTeamMembersForProject($json->project, $teamMembers);
		$this->getPlugin()->notifier()->onUpdateProjectPermissions($json);

		Emit('onSaveMemberPermissions', array(
			'json' => $json,
			'team' => $teamMembers,
		));

		return array(
			'team' => $teamMembers,
			'project' => $json->project,
		);

	}

	protected function createDashboard($json) {

		include_once __DIR__ . '/lib/Deployment.php';

		(new \ReferralManagement\Deployment())
			->fromParameters($json)
			->respondToEmailRequest()
			->deployToElasticBeanstalk();

		return true;

	}

	protected function listProjects( /*$json*/) {

		$response = array(
			'results' => $this->getPlugin()->getActiveProjectList(),
			'debug'=> $this->getPlugin()->cache()->getProjectsListCacheStatus(
				array('status' => array('value' => 'archived', 'comparator' => '!='))),
		);

		//$userCanSubscribe = GetClient()->isAdmin();
		//if ($userCanSubscribe) {
			$response['subscription'] = array(
				'channel' => 'proposals',
				'event' => 'update',
			);
		//}

		return $response;

	}


	protected function projectSearch( $json) {


		GetPlugin('Attributes');
		return array('results'=>(new \attributes\RecordQuery('proposalAttributes'))->searchValues($json->search->name, 'title'));


		$response = array('results' => $this->getPlugin()->getActiveProjectList(array(
			'LIMIT'=>5
		)));

		return $response;

	}

	protected function getProject($json) {



		$response = array('results' => GetPlugin('ReferralManagement')->listProjectsMetadata(array('id'=>$json->project)));

			//$this->getPlugin()->getProjectList(array('id'=>$json->project)));

		return $response;

	}


	protected function getProjectLayers($json){

		/**
		 * TODO: Projects will have additional editable layers
		 */
		

	}

	protected function getCommunityLayers($json){

		/**
		 * TODO: Communities will have additional private layers
		 */
		
	}

	

	protected function addDocument($json) {

		if (!Auth('extend', $json->id, $json->type)) {
			return $this->setError('No access or does not exist');
		}

		if (!in_array($json->type, array('ReferralManagement.proposal', 'Tasks.task'))) {
			return $this->setError('Invalid item type');

		}

		try {

			include_once __DIR__ . '/lib/Attachments.php';

			return array(
				'new' => (new \ReferralManagement\Attachments())->add($json->id, $json->type, $json),
			);

		} catch (Exception $e) {
			return $this->setError($e->getMessage());
		}

	}

	protected function removeDocument($json) {

		if (!Auth('extend', $json->id, $json->type)) {
			return $this->setError('No access or does not exist');
		}

		if (!in_array($json->type, array('ReferralManagement.proposal', 'Tasks.task'))) {
			return $this->setError('Invalid item type');

		}

		try {

			include_once __DIR__ . '/lib/Attachments.php';

			return array(
				'new' => (new \ReferralManagement\Attachments())->remove($json->id, $json->type, $json),
			);

		} catch (Exception $e) {
			return $this->setError($e->getMessage());
		}

	}

	protected function saveGuestProposal($json) {

		if (key_exists('email', $json) && key_exists('token', $json)) {

			if (filter_var($json->email, FILTER_VALIDATE_EMAIL)) {

				$clientToken = ($links = GetPlugin('Links'))->createLinkEventCode('onActivateEmailForGuestProposal', array(
					'validationData' => $json,
				));

				$clientLink = HtmlDocument()->website() . '/' . $links->actionUrlForToken($clientToken);

				$subject = (new \core\Template(
					'activate.proposal.email.subject', "Verify your email address to submit your proposal"))
					->render(GetClient()->getUserMetadata());
				$body = (new \core\Template(
					'activate.proposal.email.body', "Its almost done, just click the link to continue: <a href=\"{{link}}\" >Click Here</a>"))
					->render(array_merge(GetClient()->getUserMetadata(), array("link" => $clientLink)));

				GetPlugin('Email')->getMailer()
					->mail($subject, $body)
					->to($json->email)
					->send();

				return true;
			}

			return false;

		}

		$clientToken = (GetPlugin('Links'))->createDataCode('guestProposalData', array(
			'proposalData' => $json,
		));

		Emit('onQueueGuestProposal', array(
			'proposalData' => $json,
			'token' => $clientToken,
		));

		return array(
			'token' => $clientToken,
		);

	}

	protected function saveProposal($json) {

		if (key_exists('id', $json) && (int) $json->id > 0) {

			if (!Auth('write', $json->id, 'ReferralManagement.proposal')) {
				return $this->setError('No access or does not exist');
			}

			try {

				return array(
					'id' => $json->id,
					'data' => (new \ReferralManagement\Project())->updateFromJson($json)->toArray(),
				);

			} catch (Exception $e) {
				return $this->setError($e->getMessage());
			}

		}

		try {

			$data = (new \ReferralManagement\Project())->createFromJson($json)->toArray();
			return array(
				'id' => $data['id'],
				'data' => $data,
			);

		} catch (Exception $e) {
			return $this->setError($e->getMessage());
		}

	}

	protected function deleteTask($json) {

		if ((int) $json->id > 0) {

			if (!Auth('write', $json->id, 'ReferralManagement.proposal')) {
				return $this->setError('No access or does not exist');
			}

			if (GetPlugin('Tasks')->deleteTask($json->id)) {

				$this->getPlugin()->notifier()->onDeleteTask($json);

				return true;
			}
		}

		return $this->setError('Unable to delete');

	}

	protected function saveTask($json) {

		
		if (key_exists('id', $json)&& (int) $json->id > 0) {


			if (!Auth('write', $json->id, 'Tasks.task')) {
				return $this->setError('No access or does not exist');
			}

			try {

				return array(
					'id' => $json->id,
					'data' => (new \ReferralManagement\Task())->updateFromJson($json)->toArray(),
				);

			} catch (Exception $e) {
				return $this->setError($e->getMessage());
			}

		}

		try {

			if (!Auth('write', $json->itemId, $json->itemType)) {
				return $this->setError('No access or does not exist');
			}

			$data = (new \ReferralManagement\Task())->createFromJson($json)->toArray();
			return array(
				'id' => $data['id'],
				'data' => $data,
			);

		} catch (Exception $e) {
			return $this->setError($e->getMessage());
		}

	}

	protected function defaultTaskTemplates($json) {
		return array('taskTemplates' => $this->getPlugin()->getDefaultProposalTaskTemplates($json->proposal));
	}
	protected function createDefaultTasks($json) {
		$taskIds = $this->getPlugin()->createDefaultProposalTasks($json->proposal);

		$this->getPlugin()->notifier()->onCreateDefaultTasks($taskIds, $json);

		return array("tasks" => $taskIds, 'tasksData' => array_map(function ($taskId) {
			return $this->getPlugin()->getTaskData($taskId);
		}, $taskIds));
	}


	protected function getUser($json) {

		$user=$this->getPlugin()->getUsersMetadata($json->id);

		return array(
			
			"result" =>$user
		);


	}

	protected function listUsers($json) {

		return array(
			'subscription' => array(
				'channel' => 'userlist',
				'event' => 'update',
			),
			"results" =>$this->getPlugin()->getClientsUserList() //,
			//"communities"=>$this->getPlugin()->listCommunities()
		);
	}

	protected function listDevices() {

		return array(
			'subscription' =>array(
				'channel' => 'devicelist',
				'event' => 'update',
			),
			"debug"=>$this->getPlugin()->cache()->getDeviceListCacheStatus(),
			"results" => $this->getPlugin()->getClientsDeviceList()
		);
	}

	protected function listArchivedProjects( /*$json*/) {

		$response = array(
			'results' => $this->getPlugin()->getArchivedProjectList(),
			'debug'=> $this->getPlugin()->cache()->getProjectsListCacheStatus(
				array('status' => 'archived'))
		);
		return $response;

	}

	protected function getUsersTasks( /*$json*/) {

		return array('results' => GetPlugin('Tasks')->getItemsTasks(GetClient()->getUserId(), "user"));

	}

	protected function setProposalStatus($json) {

		/* @var $database ReferralManagementDatabase */
		$database = $this->getPlugin()->getDatabase();

		if (key_exists('id', $json) && (int) $json->id > 0) {

			if (!Auth('write', $json->id, 'ReferralManagement.proposal')) {
				return $this->setError('No access or does not exist');
			}

			$database->updateProposal(array(
				'id' => (int) $json->id,
				'status' => $json->status,
			));

			$this->getPlugin()->notifier()->onUpdateProposalStatus($json);

			return array('id' => (int) $json->id);

		}

		return $this->setError('Proposal does not exist');

	}

	protected function deleteProposal($json) {

		$this->info('ReferralManagement', 'Delete proposal');

		/* @var $database ReferralManagementDatabase */
		$database = $this->getPlugin()->getDatabase();

		if ((int) $json->id <= 0) {
			return $this->setError('Invalid id: ' . $json->id);
		}

		if (!Auth('write', (int) $json->id, 'ReferralManagement.proposal')) {
			return $this->setError('No access or does not exist');
		}

		$this->info('ReferralManagement', 'Delete proposal: ' . $json->id);

		$data = $this->getPlugin()->getProposalData($json->id);

		if ($database->deleteProposal((int) $json->id)) {

			$this->getPlugin()->notifier()->onDeleteProposal($json);

			Emit('onDeleteProposal', $data);

			Broadcast('proposals', 'update', array(
				'user' => GetClient()->getUserId(),
				'deleted' => array($json->id),
			));
			return true;
		}

	}
	

	protected function generateReport($json) {

		include_once __DIR__ . '/lib/Report.php';
		(new \ReferralManagement\Report($json->proposal))
			->generateReport('proposal.report', 'Hello World')
			->renderPdf();
		exit();

	}

	protected function downloadFiles($json) {

		include_once __DIR__ . '/lib/ComputedData.php';
		$parser = new \ReferralManagement\ComputedData();

		$localPath = function ($url) {
			if ((new \core\html\Path())->isHostedLocally($url)) {
				return PathFrom($url);
			}

			return $url;
		};

		$data = $this->getPlugin()->getProposalData($json->proposal);

		$zip = new ZipArchive();
		$filename = tempnam(__DIR__, '_zip');

		if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
			exit("cannot open <" . $filename . ">\n");
		}

		foreach (array_map($localPath, $parser->parseProposalFiles($data)) as $url) {
			$zip->addFromString(basename($url), file_get_contents($url));
		}

		foreach ($data['tasks'] as $task) {
			foreach (array_map($localPath, $parser->parseTaskFiles($task)) as $url) {
				$zip->addFromString(basename($url), file_get_contents($url));
			}
		}

		$zip->close();
		$content = file_get_contents($filename);
		unlink($filename);

		$title = $data['attributes']['title'];

		header("Content-Type: application/zip");
		header("Content-Length: " . mb_strlen($content, "8bit"));
		header("Content-Disposition: attachment; filename=\"" . $title . "-attachments-" . time() . ".zip\"");
		exit($content);

		return array('files' => $data['files'], 'proposal' => $data);

	}

	protected function getReserveMetadata($json) {

		GetPlugin('Maps');
		$marker =(new \spatial\FeatureLoader())->fromId($json->id);

		$str = $marker->getDescription();

		$getUrls = function ($str) {

			$urls = array();

			$links = explode('<a ', $str);
			array_shift($links);

			foreach ($links as $l) {

				$a = explode('href', $l);
				$a = ltrim(ltrim(ltrim($a[1]), '='));

				$q = $a{0};
				$a = substr($a, 1);

				$a = explode($q, $a);
				$a = $a[0];

				$urls[] = $a;
			}

			return $urls;
		};

		$url = $getUrls($str)[0];

		$page = file_get_contents($url);
		$urls = $getUrls($page);

		$website = '';
		foreach ($urls as $u) {

			if (strpos($u, 'http://pse5-esd5.ainc-inac.gc.ca') !== false) {
				break;
			}
			$website = $u;

		}

		if (strpos($website, 'https://apps.gov.bc.ca') !== false) {
			return array('result' => false);
		}

		return array('result' => Core::LoadPlugin('ExternalContent')->ParseHTML($website));

	}

	protected function exportProposals($json) {


		include_once __DIR__.'/lib/Export.php';
		$export=(new \ReferralManagement\Export());

		if(key_exists('secret', $json)&&$json->secret===$this->getPlugin()->getParameter('exportSecret')){
			$export->showAllProposals();
		}

		$export->exportProposals();

		if(key_exists('format',$json)&&$json->format=='json'){
			return $export->toArrayResult();
		}

		$export->printCsv();
		exit();

	}



	protected function addItemUser($json) {

		if (!Auth('write', $json->item, $json->type)) {
			return $this->setError('No access or does not exist');
		}

		if ($json->type == "ReferralManagement.proposal") {
			return array(
				'team' => $this->getPlugin()->addTeamMemberToProject($json->user, $json->item),
			);
		}

		if ($json->type == "Tasks.task") {
			return array(
				'team' => $this->getPlugin()->addTeamMemberToTask($json->user, $json->item),
			);
		}

		throw new Exception('Invalid type');

	}

	protected function removeItemUser($json) {
		if (!Auth('write', $json->item, $json->type)) {
			return $this->setError('No access or does not exist');
		}

		if ($json->type == "ReferralManagement.proposal") {
			return array(
				'team' => $this->getPlugin()->removeTeamMemberFromProject($json->user, $json->item),
			);
		}

		if ($json->type == "Tasks.task") {
			return array(
				'team' => $this->getPlugin()->removeTeamMemberFromTask($json->user, $json->item),
			);
		}

		throw new Exception('Invalid type');

	}

	protected function setStarredTask($json) {
		if (!Auth('write', $json->task, 'ReferralManagement.proposal')) {
			return $this->setError('No access or does not exist');
		}

		GetPlugin('Attributes');

		$attributes = (new attributes\Record('taskAttributes'))->getValues($json->task, 'Tasks.task');

		$starUsers = $attributes['starUsers'];
		if (empty($starUsers)) {
			$starUsers = array();
		}

		$starUsers = array_diff($starUsers, array(GetClient()->getUserId()));
		
		if ($json->starred) {
			$starUsers = array_merge($starUsers, array(GetClient()->getUserId()));
		}

		$starUsers = array_values(array_unique($starUsers));

		(new attributes\Record('taskAttributes'))->setValues($json->task, 'Tasks.task', array(
			'starUsers' => $starUsers,
		));

		$this->getPlugin()->notifier()->onUpdateTaskStar($json);

		return true;
	}

	protected function setPriorityTask($json) {
		if (!Auth('write', $json->task, 'Tasks.task')) {
			return $this->setError('No access or does not exist');
		}

		GetPlugin('Attributes');

		(new attributes\Record('taskAttributes'))->setValues($json->task, 'Tasks.task', array(
			'isPriority' => $json->priority,
		));

		$this->getPlugin()->notifier()->onUpdateTaskPriority($json);

		return true;
	}
	protected function setDuedateTask($json) {
		if (!Auth('write', $json->task, 'Tasks.task')) {
			return $this->setError('No access or does not exist');
		}

		$taskId = (int) $json->task;
		if ($taskId > 0) {
			if (GetPlugin('Tasks')->updateTask($taskId, array(
				"dueDate" => $json->date,
			))) {

				$this->getPlugin()->notifier()->onUpdateTaskDate($json);

				return true;

			}

			return $this->setError('Unable to update task date');
		}

		return $this->setError('Invalid task');

	}

	protected function setUserRole($json) {

		if (!GetClient()->isAdmin()) {

			$userRoles = $this->getPlugin()->getUserRoles($json->user);
			$canSetList = $this->getPlugin()->getRolesUserCanEdit();

			if (empty($canSetList)) {
				return $this->setError('User does not have permission to set any roles');
			}
			$canSetList[] = "none";

			if (!in_array($json->role, $canSetList)) {
				return $this->setError('User cannot apply role: ' . $json->role . ' not in: ' . json_encode($canSetList));
			}

			if (empty(array_intersect($userRoles, $canSetList)) && !empty($userRoles)) {
				return $this->setError('Target user: ' . json_encode($userRoles) . ' is not in role that is editable by user: ' . json_encode($canSetList));
			}

			(new \core\LongTaskProgress())->throttle('onTriggerUpdateDevicesList', array('team' => 1),array('interval'=>30));
			(new \core\LongTaskProgress())->throttle('onTriggerUpdateUserList', array('team' => 1), array('interval'=>30));

		}

		$values = array();
		foreach ($this->getPlugin()->getGroupAttributes() as $role => $field) {

			if ($role === $json->role) {
				$values[$field] = true;
				continue;
			}

			$values[$field] = false;

		}

		GetPlugin('Attributes');

		(new attributes\Record('userAttributes'))->setValues($json->user, 'user', $values);

		$this->getPlugin()->notifier()->onUpdateUserRole($json);

		return $values;

	}


	protected function usersOnline(){


		return array(
			'results'=>GetClient()->isOnlineGroup(array_map(function($user){
				return $user->id;
			}, $this->getPlugin()->getClientsUserList()))
		);
		
	}


	protected function devicesOnline(){

		$deviceIds=array();
		foreach($this->getPlugin()->getClientsDeviceList() as $user){
			foreach($user->devices as $deviceId){
				$deviceIds[]=$deviceId;
			}
		}

		$devicesOnlineStatus=GetPlugin('Apps')->isOnlineGroup($deviceIds);




		return array(
			'extra'=>$devicesOnlineStatus,

			'results'=>array_map(function($device)use($devicesOnlineStatus){

			


			$anyOnline=false;
			foreach ($devicesOnlineStatus as $deviceStatus) {
				if($deviceStatus->online&&in_array($deviceStatus->id, $device->devices)){
					$anyOnline=true;
					break;
				}
			}

			return array(
				'id'=>(int) $device->id,
				'devices'=>$device->devices,
				'online'=>$anyOnline
			);
			
		}, $this->getPlugin()->getClientsDeviceList()));
		
	}


	protected function getServerConfig($json){

		if(!key_exists('server', $json)){	
			return $this->setError('Invalid server');
		}

		$server=$json->server;
		$controller=\rmt\DomainController::SharedInstance();
		if(!$controller){
			return $this->setError("No controller exists");
		}




		return array(
			'info'=>$controller->getDomainInfo($server)
		);
	}


	protected function listDepartments($json){

		$deps=array_map(function($department){


				return $department;
			}, $this->getPlugin()->getDatabase()->getDepartments());

		return array(
			'departments'=>$deps?$deps:array()
		);
	}

	protected function listTags($json){

		$tags=array_map(function($category){

				$category->metadata=json_decode($category->metadata);

				$category->category=$category->type;
				$category->color="#eeeeee";
				if($category->metadata&&key_exists('color', $category->metadata)){
					$category->color=$category->metadata->color;
				}

				$category->shortName=$category->shortName?$category->shortName:$category->name;

				unset($category->metadata);
				unset($category->type);

				return $category;
			}, $this->getPlugin()->getDatabase()->getCategorys());

		return array(
			'tags'=>$tags?$tags:array()
		);
	}


	protected function saveTag($json){

		$updateData=array(
			'name'=>$json->name,
			'shortName'=>$json->shortName?$json->shortName:$json->name,
			'description'=>$json->description,
			'type'=>$json->category,
			'metadata'=>json_encode(array('color'=>$json->color))
		);


		if(key_exists('id',$json)){

			$updateData['id']=$json->id;
			$this->getPlugin()->getDatabase()->updateCategory($updateData);
			return array('tag'=>$updateData);
		}


		$updateData['id']=$this->getPlugin()->getDatabase()->createCategory($updateData);
		return array('tag'=>$updateData);


	}

	protected function saveDepartment($json){

		$updateData=array(
			'name'=>$json->name,
			'description'=>$json->description,
			'metadata'=>json_encode((object)array())
		);


		if(key_exists('id',$json)){

			$updateData['id']=$json->id;
			$this->getPlugin()->getDatabase()->updateDepartment($updateData);
			return array('department'=>$updateData);
		}


		$updateData['id']=$this->getPlugin()->getDatabase()->createDepartment($updateData);
		return array('department'=>$updateData);


	}



}
