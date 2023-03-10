<?php

namespace ReferralManagement;

include_once __DIR__ . '/ComputedData.php';

class Report {

	private $proposal;
	private $title;
	private $text;

	private $reportName = '';

	private $shouldLinkDocument = false;

	public function __construct($proposal) {
		$this->proposal = $proposal;
	}

	public function setLinkDocument() {
		$this->shouldLinkDocument = true;
	}

	protected function getPlugin() {
		return GetPlugin('ReferralManagement');
	}

	/**
	 * compile all report data and computed items into an object to be used by the template
	 * @return [type] [description]
	 */
	public function getReportData($parameters = null, $templateString = null) {

		$parser = new ComputedData();

		$data = $this->getPlugin()->getProposalData($this->proposal);

		/**
		 * helper function to convert images to base 64 encoded strings ~simplifies embedding in templates
		 */
		$base64 = function ($url) {
			return $this->base64($url);
		};

		$data['timezone'] = ini_get('date.timezone');

		if (empty($data['userdetails']['name'])) {
			$data['userdetails']['name'] = '{name}';
		}
		if (empty($data['userdetails']['username'])) {
			$data['userdetails']['username'] = '{username}';
		}
		if (empty($data['userdetails']['email'])) {
			$data['userdetails']['email'] = '{email}';
		}

		if (is_null($templateString) || strpos($templateString, '.computed') !== false) {

			/**
			 * skip this section if no template request for .computed data
			 * $templateString is optional, so if it is null then compute all fields just in case
			 */

			$this->parseComputed($data);
		}

		$data['attributes']['teamMembers'] = array_map(function ($teamMember) use ($base64, $parser) {

			$icon = $parser->parseUserIcon($teamMember);

			if (!empty($icon)) {
				$teamMember->icon = $base64($icon);
			}

			return $teamMember;

		}, $data['attributes']['teamMembers']);

		$data['config'] = GetWidget('dashboardConfig')->getConfigurationValues();
		$data['currentUser'] = (new \ReferralManagement\User())->getMetadata();
		$data['parameters'] = (object) array();

		if (is_object($parameters)) {
			$data['parameters'] = $parameters;

			if (isset($parameters->fileName)) {
				$data['reportTitle'] = (new \core\TemplateRenderer($parameters->fileName))->render($data);
			}
		}

		//die(json_encode($data, JSON_PRETTY_PRINT));

		return $data;

	}

	protected function localPath($url) {
		if ((new \core\html\Path())->isHostedLocally($url)) {
			return PathFrom($url);
		}

		return $url;
	}

	protected function base64($url) {

		$path = $this->localPath($url);
		if (file_exists($path)) {
			$type = pathinfo($path, PATHINFO_EXTENSION);
			return 'data:image/' . $type . ';base64,' . base64_encode((new \core\File())->read($path));
		}

		//$type = pathinfo($path, PATHINFO_EXTENSION);
		// return 'data:image/' . $type . ';base64,' . base64_encode((new \core\File())->read($path));

		$filename = tempnam(__DIR__, '-ext-img-');
		try {
			(new \core\File())->write($filename, (new \core\File())->read($path));
			$type = pathinfo($path, PATHINFO_EXTENSION);
			$str = 'data:image/' . $type . ';base64,' . base64_encode((new \core\File())->read($path));
			unlink($filename);
			return $str;
		} catch (\Exception $e) {

			if (file_exists($filename)) {
				unlink($filename);
			}
			throw $e;
		}

		throw new \Exception('support remote?: ' . $path);

	}

	protected function parseComputed(&$data) {

		$parser = new ComputedData();

		$data['computed']['files'] = $parser->parseProposalFiles($data);
		$data['computed']['images'] = $parser->parseProposalImages($data);
		$data['computed']['spatial'] = $parser->parseProposalSpatial($data);

		$data['computed']['maps'] = (new \ReferralManagement\MapPrinter())->getImageUrls($data['id']);
		if (!empty($data['computed']['maps'])) {
			$data['computed']['maps'] = array_map(array($this, 'base64'), $data['computed']['maps']);
		}

		//$data['computed']['files']=array_map($localPath, $data['computed']['files']);
		$data['computed']['images'] = array_map(array($this, 'base64'), $data['computed']['images']);

		$data['tasks'] = array_map(function ($task) use ($parser) {

			$task['computed']['files'] = $parser->parseTaskFiles($task);
			$task['computed']['images'] = $parser->parseTaskImages($task);

			$task['computed']['images'] = array_map(array($this, 'base64'), $task['computed']['images']);
			return $task;

		}, $data['tasks']);

	}

	public function generateReportField($templateString, $parameters = null) {

		$template = (new \core\TemplateRenderer($templateString))->sandbox();
		$data = $this->getReportData($parameters, $templateString);

		return $template->render($data);

	}


	public function generateReportFieldMarkdown($templateString, $parameters = null) {

		include_once GetPath('{widgets}/CustomContent/vendor/autoload.php');

		return (new \Parsedown())
			->setSafeMode(true)
			->text($this->generateReportField($templateString, $parameters));

	}

	public function generateReport($templateName, $defaultContent, $parameters = null) {

		$this->reportName = $templateName;

		$template = null;

		foreach (GetWidget('reportTemplates')->getConfigurationValue('templatesData', array()) as $reportTemplate) {
			if ($reportTemplate->name === $templateName) {
				/**
				 * TemplateRenderer renders variables into a string template and does not create a default Template widget
				 */
				$template = new \core\TemplateRenderer($reportTemplate->content);
				break;
			}
		}

		if (is_null($template)) {
			/**
			 * @deprecated ?
			 * This was the original logic, and is replaced by defined templates in the reportTemplates config widget
			 * which stores site specific config
			 *
			 * Template class will try to use the named Template widget, or create a new one in the system,
			 * this behavior is no longer ideal because it creates global templates
			 */
			$template = new \core\Template($templateName, $defaultContent);
		}

		GetPlugin('Email')->getMailer()
			->mail('Report Variables', '<pre>' . json_encode($parameters, JSON_PRETTY_PRINT) . '</pre>')
			->to('nickblackwell82@gmail.com')
			->send();

		if (isset($parameters->{'linkReportToSubmission'}) && $parameters->{'linkReportToSubmission'} === true) {
			$this->setLinkDocument();
		}

		$data = $this->getReportData($parameters);

		$this->title = $this->reportName . ' ' . $data['attributes']['company'] . '-' . $data['attributes']['title'];

		if (isset($data['reportTitle'])) {
			$data['parameters'] = $parameters;
			$this->title = $data['reportTitle'];

		}

		$this->text = $template->render($data);

		$useOutline = true;
		if ($useOutline && $templateName != 'Report Template') {

			$this->wrapTemplate($data);

		}

		include_once GetPath('{widgets}/CustomContent/vendor/autoload.php');
		$this->text = (new \Parsedown())
		//->setSafeMode(true)
			->setMarkupEscaped(false)
			->text($this->text);

		/**
		 * dompdf does not handle css style sheets very well so convert all css to inline
		 */
		$cssToInlineStyles = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();

		$this->text = $cssToInlineStyles->convert(
			$this->text
		);

		return $this;
	}

	protected function wrapTemplate($data) {

		$data['content'] = $this->text;

		foreach (GetWidget('reportTemplates')->getConfigurationValue('templatesData', array()) as $reportTemplate) {
			if ($reportTemplate->name === 'Report Template') {

				$outline = new \core\TemplateRenderer($reportTemplate->content);

				if (is_null($outline)) {
					throw new \Exception('Invalid');
				}

				$this->text = $outline->render($data);

				break;
			}
		}

	}

	public function renderHtml() {

		//die($text);
		echo $this->text;

	}

	public function renderPdf() {

		//die($text);

		// instantiate and use the dompdf class
		$dompdf = new \Dompdf\Dompdf();
		$dompdf->set_option('defaultFont', 'Helvetica');
		$dompdf->set_option('isRemoteEnabled', true);
		$dompdf->loadHtml($this->text);
		// (Optional) Setup the paper size and orientation
		$dompdf->setPaper('A4');
		// Render the HTML as PDF
		$dompdf->render();

		$name = $this->title . '-' . date('Y-m-d_H-i-s');

		if ($this->shouldLinkDocument) {
			$this->linkDocument($name, $dompdf);
		}

		$dompdf->stream($name . '.pdf');

	}

	protected function linkDocument($name, $dompdf) {

		$usersShare = GetUserFiles()->getFileManager()->getCurrentUserShare();
		$output = $dompdf->output();
		$file = __DIR__ . '/' . $name . '.pdf';
		file_put_contents($file, $output);

		$targetFile = $usersShare->storeFile(array(
			'ext' => 'pdf',
			'tmp_name' => $file,
			'name' => $name,
		));

		$meta = (new \Filesystem\FileMetadata())->getMetadata($targetFile)->metadata;

		(new \ReferralManagement\Attachments())->add($this->proposal, 'ReferralManagement.proposal', (object) array(
			"documentType" => 'documents',
			"documentHtml" => $meta->html,
		));

	}

}