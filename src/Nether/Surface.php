<?php

namespace Nether;
use \Nether;

////////////////
////////////////

Option::Define([
	'surface-theme'        => 'default',
	'surface-theme-common' => 'common',
	'surface-style'        => 'default',
	'surface-theme-root'   => sprintf('%s/themes',Option::Get('nether-web-root')),
	'surface-theme-path'    => sprintf('%s/themes',trim(Option::Get('nether-web-path'),'/')),
	'surface-automatic'    => true
]);

////////////////
////////////////

/* TODO
Ki::Queue('m-request-redirect',function(){
	// if a redirect was requested shut down the automatic surface instance and
	// throw away whatever it already collected.

	if(!Option::Get('surface-automatic'))
	return;

	Stash::Get('surface')->CaptureStop(false);
	return;
},true);

Ki::Queue('m-setup',function(){
	// start the automatic surface instance if enabled.

	if(!Option::Get('surface-automatic'))
	return;

	Stash::Set('surface',($surface = new Surface));
	$surface->CaptureStart();

	return;
});
*/

////////////////
////////////////

class Surface {

	public $Storage = [];
	/*//
	@type array
	where this surface instance stores the data to be used for rendering.
	//*/

	public $Theme;
	/*//
	@type string
	the name of the theme to render in.
	//*/

	public $Style;
	/*//
	@type string
	the name of the subtheme for the theme to use if it wants. the library
	itself does not actually use this, but it is there to make subthemes easier
	to make.
	//*/

	public $Rendered;
	/*//
	@type boolean
	//*/

	////////////////
	////////////////

	public function __construct() {

		$this->Theme = Option::Get('surface-theme');
		$this->Style = Option::Get('surface-style');

		$this->Rendered = false;
		$this->Storage['stdout'] = '';

		return;
	}

	public function __destruct() {
		$this->Shutdown();
		return;
	}

	////////////////
	////////////////

	public function Shutdown() {
	/*//
	shutdown method provided for the stash automatic shutdown.
	//*/

		if(!$this->Rendered && $this->Capturing) {
			$this->Render();
		}

		return;
	}

	////////////////
	////////////////

	public function Render() {
	/*//
	@return boolean
	begin the rendering operation using the full page template.
	//*/

		if($this->Capturing) $this->CaptureStop(true);

		$template = $this->GetTemplateFilename();
		$scope = $this->GetRenderScope();
		$this->PrepareCommonData();

		if(!m_require($template,$scope))
		throw new \Exception("error opening {$template} for {$this->Theme}");

		$this->Rendered = true;
	}

	protected function GetTemplateFilename($commonfb=true) {
	/*//
	@return string
	return the path to the design.phtml for the current theme.
	//*/

		$filename = m_repath_fs(sprintf(
			'%s/%s/design.phtml',
			Option::Get('surface-theme-root'),
			$this->Theme
		));

		if($commonfb && !file_exists($filename)) {
			$filename = m_repath_fs(sprintf(
				'%s/%s/design.phtml',
				Option::Get('surface-theme-root'),
				Option::Get('surface-theme-common')
			));
		}

		return $filename;
	}

	protected function GetRenderScope() {
	/*//
	@return array
	allow other libraries to attach data to the render system for the scope of
	the template files. works by creating an array that gets passed by reference
	that other instances can be appended to. that scope is then passed to the
	m_require function which allows a file to be included in a clean scope with
	easy access to the objects needed.

	other libraries should Queue Ki on the surface-get-render-scope key, and the
	callable should have 1 argument that is an &$array.
	//*/

		$scope = ['surface'=>$this];
		Ki::Flow('surface-get-render-scope',[&$scope]);
		return $scope;
	}

	protected function PrepareCommonData() {
	/*//
	allow surface to fill in empty spots for commonly needed data using the
	application configuration as a base.
	//*/

		// brand the page title ////////

		if(!$this->Get('page-title'))
		$this->Set('page-title',sprintf(
			'%s - %s',
			Option::Get('app-name'),
			Option::Get('app-short-desc')
		));

		else
		$this->Set('page-title',sprintf(
			'%s - %s',
			$this->Get('page-title'),
			Option::Get('app-name')
		));

		// generate keywords ////////

		if(!$this->Get('page-keywords'))
		$this->Set('page-keywords',implode(',',Option::Get('app-keywords')));

		if(is_array($this->Get('page-keywords')))
		$this->Set('page-keywords',implode(',',$this->Get('page-keywords')));

		// generate descriptions ////////

		if(!$this->Get('page-desc'))
		$this->Set('page-desc',Option::Get('app-long-desc'));

		return;
	}

	////////////////
	////////////////

	protected $Capturing = false;
	/*//
	@type boolean
	a sentinel marking if this object has begin capturing a level of stdout
	or not.
	//*/

	public function CaptureStart() {
	/*//
	@return boolean
	begin capturing stdout if this object has not already done so.
	//*/

		if(!$this->Capturing) {
			$this->Capturing = true;
			ob_start();
			return true;
		} else {
			Ki::Flow(
				'log-warning',
				'this surface object is already capturing stdout.'
			);
			return false;
		}
	}

	public function CaptureStop($append=true) {
	/*//
	@return mixed
	@argv boolean Append default true
	stop capturing stdout. if the append argument is true it will throw the
	caught data into the storage array, else it will return it.
	//*/

		if($this->Capturing) {
			$this->Capturing = false;
			if($append) {
				$stdout = ob_get_clean();
				Ki::Flow('surface-stdout',[&$stdout]);
				$this->Storage['stdout'] .= $stdout;
			}
			return;
		} else {
			Ki::Flow(
				'log-warning',
				'this surface object was not capturing stdout.'
			);
			return false;
		}
	}

	////////////////
	////////////////

	public function Area($input) {
	/*//
	@argv string Area
	load an area file from the theme.
	//*/

		$common = false;
		do {
			$areafile = sprintf(
				'%s/%s/area/%s.phtml',
				Option::Get('surface-theme-root'),
				((!$common)?($this->Theme):(Option::Get('surface-theme-common'))),
				$input
			);

			if(file_exists($areafile)) break;
			else $common = !$common;
		} while($common);

		$scope = $this->GetRenderScope();

		if(!m_require($areafile,$scope))
		throw new \Exception("error loading area file {$input} ({$areafile})");
	}

	public function Get() {
	/*//
	@argv string Key
	@argv array KeyList
	@return mixed
	return the value stored in this surface object. if given a string the value
	associated with it is returned. if given an array of keys, an array of
	values associated with them will be returned instead.
	//*/

		$argv = new \Util\Verse(func_get_args(),[
			['string'],
			[array('string')]
		]);

		if(!$argv->Matched)
		throw new \Exception('expects [string|array(string)]');

		switch($argv->FormatKey) {
			case 0: {
				if(array_key_exists($argv->Input[0],$this->Storage))
				return $this->Storage[$argv->Input[0]];

				else
				return null;
			}

			case 1: {
				$return = array();
				foreach($argv->Input[0] as $key) {
					if(array_key_exists($key,$this->Storage))
					$return[$key] = $this->Storage[$key];

					else
					$return[$key] = null;
				}
				return $return;
			}
		}

		return false;
	}

	public function Set() {
	/*//
	@argv string Key, Mixed Value
	@argv array KeyValueList
	sets values in the surface storage. can take a string key and value, or an
	associative array of multiple keys and values to set.
	//*/

		$argv = new \Util\Verse(func_get_args(),[
			['string','any'],
			['array']
		]);

		if(!$argv->Matched)
		throw new \Exception('expects [string,mixed|array(string,mixed)]');

		switch($argv->FormatKey) {
			case 0: {
				$this->Storage[$argv->Input[0]] = $argv->Input[1];
				break;
			}
			case 1: {
				foreach($argv->Input[0] as $key => $value)
				$this->Storage[$key] = $value;
				break;
			}
		}

		return;
	}

	public function Show($key) {
	/*//
	@argv string Key
	echo the data under the selected key.
	//*/

		if(array_key_exists($key,$this->Storage))
		echo $this->Storage[$key];

		return;
	}

	////////////////
	////////////////

	public function FromTheme($input,$return=false) {
	/*//
	@return string
	@argv string Input, boolean Return default false
	prints or returns the string at the end of the surface uri. useful for
	linking to theme resources.
	//*/

		$output = sprintf(
			'/%s/%s/%s',
			trim(Option::Get('surface-theme-path'),'/'),
			$this->Theme,
			$input
		);

		if(!$return) {
			echo $output;
			return;
		} else {
			return $output;
		}
	}

	public function FromCommon($input,$return=false) {
	/*//
	@return string
	@argv string Input, boolean Return default false
	prints or returns the string at the end of the surface uri. useful for
	linking to shared resources.
	//*/

		$output = sprintf(
			'/%s/%s/%s',
			trim(Option::Get('surface-theme-path'),'/'),
			Option::Get('surface-theme-common'),
			$input
		);

		if(!$return) {
			echo $output;
			return;
		} else {
			return $output;
		}
	}

	public function FromRoot($input,$return=false) {
	/*//
	@return string
	@arg string input, boolean Return default false
	prints or returns the string at the end of the web uri. useful for linking
	to other pages in the app.
	//*/

		$output = sprintf(
			'%s/%s',
			trim(Option::Get('nether-web-path'),'/'),
			trim($input,'/')
		);

		if(!$return) {
			echo $output;
			return;
		} else {
			return $output;
		}
	}

	public function FromPost($key,$return=false) {
	/*//
	@return string
	gets the value from the post data making it html safe.
	//*/

		$string = ((array_key_exists($key,$_POST))?
			(htmlentities($_POST[$key])):
			('')
		);

		if(!$return) {
			echo $string;
			return;
		} else {
			return $string;
		}
	}

}
