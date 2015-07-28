<?php

namespace Nether;

use \Nether;
use \Exception;

////////////////////////
////////////////////////

Option::Define([
	'surface-auto-capture' => true,
	'surface-auto-stash' => 'surface',
	'surface-theme' => 'default',
	'surface-theme-stack' => [ 'common' ],
	'surface-style' => 'default',
	'surface-title-brand' => true,
	'surface-theme-root' => sprintf(
		'%s/themes',
		Option::Get('nether-web-root')
	),
	'surface-theme-path' => sprintf(
		'%s/themes',
		trim(Option::Get('nether-web-path'),'/')
	)
]);

////////////////////////
////////////////////////

if(class_exists('Nether\Avenue\Router') && class_exists('Nether\Stash'))
Ki::Queue('nether-avenue-redirect',function(){
	// if a redirect was requested shut down the automatic surface instance and
	// throw away whatever it already collected.

	$surface = Stash::Get(Option::Get('surface-stash-name'));

	if($surface && $surface instanceof Surface)
	$surface->Stop(false);

	return;
},true);

////////////////////////
////////////////////////

class Surface {
/*//
this is the engine engine designed to make it easy to application output into
page templates with ease. there is no template language, the templates are
plain html with embedded php calls to the various surface methods.

to work as intended surface needs to know two things, which you should configure
as early as possible in the application real booting process via Nether\Option.

* nether-web-root - the filepath to the root of the public web directory.
* nether-web-path - the web url base directory from the base domain. e.g. "/"

armed with those values, surface will extrapolate where the themes are stored
by way of the suggested Nether application structure. if you wish to be specific
instead of allowing it to guess, you may specify the root and path for the
theme directory via surface-theme-root (filesystem path) and
surface-theme-path (web url path).
//*/

	protected $Started = false;
	/*//
	@type boolean
	a sentinel marking if this object has begun capturing a level of stdout
	or not.
	//*/

	protected $Storage = [];
	/*//
	@type array
	where this surface instance stores the data to be used for rendering.
	//*/

	////////////////////////
	////////////////////////

	protected $Style;
	/*//
	@type string
	the name of the subtheme for the theme to use if it wants. the library
	itself does not actually use this, but it is there to make subthemes easier
	to make.
	//*/

	public function
	GetStyle() { return $this->Style; }

	public function
	SetStyle($style) { $this->Style = $style; return $this; }

	////////
	////////

	protected $Theme;
	/*//
	@type string
	the name of the theme to render in.
	//*/

	public function
	GetTheme() { return $this->Theme; }

	public function
	SetTheme($theme) { $this->Theme = $theme; return $this; }

	////////
	////////

	protected $ThemeRoot;
	/*//
	@type string
	the directory all the themes are installed.
	//*/

	public function
	GetThemeRoot() { return $this->ThemeRoot; }

	public function
	SetThemeRoot($path) { $this->ThemeRoot = $path; return $this; }

	////////////////////////
	////////////////////////

	public function
	__Construct($opt=null) {
	/*//
	handle object init.
	//*/

		$opt = new Nether\Object($opt,[
			'Theme'       => Option::Get('surface-theme'),
			'ThemeRoot'   => Option::Get('surface-theme-root'),
			'Style'       => Option::Get('surface-style'),
			'AutoCapture' => Option::Get('surface-auto-capture'),
			'AutoStash'   => Option::Get('surface-auto-stash')
		]);

		$this->Storage['stdout'] = '';

		// pull in default settings.
		$this->Theme = $opt->Theme;
		$this->ThemeRoot = $opt->ThemeRoot;
		$this->Style = $opt->Style;

		// if auto stashing is enabled.
		if(is_string($opt->AutoStash) && class_exists('Nether\Stash')) {
			if(!Stash::Has($opt->AutoStash))
			Stash::Set($opt->AutoStash,$this);
		}

		// begin capture if autocapture is enabled and this is not the
		// command line interface.
		if($opt->AutoCapture && php_sapi_name() !== 'cli')
		$this->Start();

		return;
	}

	public function
	__Destruct() {
	/*//
	handle object destruct.
	//*/

		$this->Render();
		return;
	}

	////////////////////////
	////////////////////////

	public function
	Start() {
	/*//
	@return boolean
	begin capturing stdout if this object has not already done so. returns if
	the capture was a success or not... not that i have ever seen ob_start fail.
	//*/

		// don't allow obception.
		if(!$this->Started)
		$this->Started = ob_start();

		return $this->Started;
	}

	public function
	Stop($keep=true) {
	/*//
	@return mixed
	@argv boolean KeepBuffer default true
	stop capturing stdout. if the append argument is true it will throw the
	caught data into the storage array, else it will return and discard it.
	//*/

		// nothing if we haven't started.
		if(!$this->Started)
		return false;

		// else fetch the buffer.
		$this->Started = false;
		$stdout = ob_get_clean();

		// allow things to filter this content.
		Ki::Flow('surface-stdout',[&$stdout]);

		// append it if we elected to keep it.
		if($keep) {
			$this->Storage['stdout'] .= $stdout;
			return true;
		}

		// else return it and forget it.
		return $stdout;
	}

	////////////////////////
	////////////////////////

	public function
	Render($return=false) {
	/*//
	@return boolean
	begin the rendering operation using the full page template.
	//*/

		// grab the standard output if we were recording it.
		if($this->Started)
		$this->Stop(true);

		// check if we had decided on a theme yet.
		if(!$this->Theme)
		$this->Theme = Nether\Option::Get('surface-theme');

		// determine the template file to use.
		if(!($template = $this->GetThemeFile('design.phtml')))
		throw new Exception("error opening {$template} for {$this->Theme}");

		// run through the framework settings to generate some common meta data
		// like page title if the data hasn't already been defined.
		$this->PrepareTitle();
		$this->PrepareKeywords();
		$this->PrepareDescription();

		////////
		////////

		ob_start();
		call_user_func(function($__filename,$__scope){
			extract($__scope); unset($__scope);
			require($__filename);
		},$template,$this->GetRenderScope());

		if(!$return) {
			// print it out if we didn't want it back.
			echo ob_get_clean();
			return;
		} else {
			// hand it back if we wanted it.
			return ob_get_clean();
		}
	}

	////////////////
	////////////////

	protected function PrepareTitle() {
	/*//
	generate a page-title if one has not yet been defined. also perform branding
	if page title branding is enabled.
	//*/

		// if no page title has been defined attempt to auto generate one from
		// the application name and description.
		if(!$this->Get('page-title')) {
			if(Option::Get('app-name') && Option::Get('app-short-desc')) {
				$this->Set('page-title',sprintf(
					'%s - %s',
					Option::Get('app-name'),
					Option::Get('app-short-desc')
				));
			} else if(Option::Get('app-name')) {
				$this->Set('page-title',Option::Get('app-name'));
			}
		}

		// if we have a page title, attempt to seo brand it with the application
		// name as defined.
		else {
			if(Option::Get('surface-title-brand') && Option::Get('app-name')) {
				$this->Set('page-title',sprintf(
					'%s - %s',
					$this->Get('page-title'),
					Option::Get('app-name')
				));
			}
		}

		return;
	}

	protected function PrepareKeywords() {
	/*//
	generate a page-keywords if one has not yet been defined.
	//*/

		// if no keywords have been defined then attempt to pull them from the
		// application config.
		if(!$this->Get('page-keywords')) {
			if(is_array(Option::Get('app-keywords'))) {
				$this->Set(
					'page-keywords',
					join(',',Option::Get('app-keywords'))
				);
			}
		}


		// we promote storing keywords as an array while the application is
		// processing, but we will convert that array into a string for render
		// happy fun render time.
		if(is_array($this->Get('page-keywords')))
		$this->Set('page-keywords',join(',',$this->Get('page-keywords')));

		return;
	}

	protected function PrepareDescription() {
	/*//
	generate a page-desc if one has not yet been defined.
	//*/

		// if no description is set, attempt to get one from the application
		// configuration.
		if(!$this->Get('page-desc') && Option::Get('app-long-desc'))
		$this->Set('page-desc',Option::Get('app-long-desc'));

		return;
	}

	////////////////////////////////////////////////////////////////////////////
	// new area api ////////////////////////////////////////////////////////////

	public function
	Area($input,$return=false) {
	/*//
	@argv string Area, bool ShouldReturn default false
	@return string or null
	@deprecated
	this function exists for backwards compat and is basically a binary alias
	to the GetArea and ShowArea methods.
	//*/

		if(!$return) return $this->ShowArea($input);
		else return $this->GetArea($input);
	}

	public function
	GetArea($which) {
	/*//
	@argv string AreaFileRequest
	@return string
	attempt to fetch the result of the specified area file. it takes a string
	to the requested area relative to the current theme. it can also be
	prefixed with a theme stack with colons to customise which theme this
	request comes from.

	* index/home
	* alt:index/home
	* alt1:alt2:index/home

	with a technically infinite number of stacks. throws an exception if no
	files were found to handle the surface area.
	//*/

		$stack = explode(':',$which);
		$area = array_pop($stack);

		$filename = $this->GetThemeFile(
			"area/{$area}.phtml",
			$stack
		);

		if(!$filename)
		throw new Exception("no surface area matching {$which} could be located.");

		////////
		////////

		ob_start();
		call_user_func(function($__filename,$__scope){
			extract($__scope); unset($__scope);
			require($__filename);
		},$filename,$this->GetRenderScope());
		return ob_get_clean();
	}

	public function
	ShowArea($which) {
	/*//
	@argv string AreaFileRequest
	@return self
	wraps the GetArea method for instant printign.
	//*/

		echo $this->GetArea($which);
		return $this;
	}

	////////////////////////////////////////////////////////////////////////////
	// data storage api ////////////////////////////////////////////////////////

	public function
	Get($what) {
	/*//
	@argv string Key
	@argv array(string Key, ...)
	@return mixed
	return the value stored in this surface object. if given a string the value
	associated with it is returned. if given an array of keys, an array of
	values associated with them will be returned instead.
	//*/

		if(is_string($what))
		return $this->Get_ByString($what);

		if(is_array($what))
		return $this->Get_ByArray($what);

		return false;
	}

	protected function
	Get_ByString($key) {
	/*//
	@argv string Key
	@return mixed
	return the data stored in the specified key.
	//*/

		if(array_key_exists($key,$this->Storage))
		return $this->Storage[$key];

		return false;
	}

	protected function
	Get_ByArray($list) {
	/*//
	@argv array KeyList
	@return array
	given an array of keys to lookup, return an array of data
	indexed by those same keys.
	//*/

		$return = [];

		foreach($list as $key)
		$return[$key] = $this->Get_ByString($key);

		return $return;
	}

	public function
	Set($what,$value=null) {
	/*//
	@argv string Key, Mixed Value
	@argv array(string Key => mixed Value, ...)
	@return self
	sets values in the surface storage. can take a string key and value, or an
	associative array of multiple keys and values to set.
	//*/

		if(is_string($what))
		return $this->Set_ByString($what,$value);

		if(is_array($what))
		return $this->Set_ByArray($what);

		return $this;
	}

	protected function
	Set_ByString($key,$val) {
	/*//
	@argv string Key, mixed Val
	@return self
	//*/

		$this->Storage[$key] = $val;
		return $this;
	}

	protected function
	Set_ByArray($data) {
	/*//
	@argv array Dataset
	@return self
	//*/

		foreach($data as $key => $val)
		$this->Set_ByString($key,$val);

		return $this;
	}

	public function
	Show($key) {
	/*//
	@argv string Key
	@return self
	echo the data under the selected key.
	//*/

		if(array_key_exists($key,$this->Storage))
		echo $this->Storage[$key];

		return $this;
	}

	////////////////
	////////////////

	protected function
	GetRenderScope() {
	/*//
	@return array
	allow other libraries to attach data to the render system to create a
	variable to be accessable within theme templates.
	//*/

		$scope = ['surface'=>$this];
		Ki::Flow(
			'surface-render-scope',
			[&$scope]
		);

		return $scope;
	}

	////////////////
	////////////////

	protected function
	GetThemeFile($name,$stack=null) {
	/*//
	@argv string Filename
	@argv string Filename, string StackDefine
	@argv string Filename, array StackList
	@return string or false.
	run through the theme stack and attempt to locate a file that matches the
	request. if found it returns the full filepath to that file - if not then
	it returns boolean false.
	//*/

		foreach($this->GetThemeStack($stack) as $theme) {
			$filename = sprintf(
				'%s/%s/%s',
				$this->ThemeRoot,
				$theme,
				$name
			);

			// if this theme file was not found pray continue.
			if(!file_exists($filename) || !is_readable($filename))
			continue;

			// else it seems valid enough so use it.
			return $filename;
		}

		return false;
	}

	protected function
	GetThemeStack($input=null) {
	/*//
	@argv string ThemeStackSpecification
	@argv array ThemeStackList
	@return array
	if given a string it will split the string on colons to generate the
	input list of the theme stack. this is how stacks will be custom
	selected by the area method. if given an array then that is just it.
	it returns a list of all the themes to check for requested files.
	//*/

		if(is_string($input)) {
			// accept a string:like:this for custom stacking.
			$stack = explode(':',$input);
		}
		elseif(is_array($input)) {
			// accept a straight array.
			$stack = $input;
		}
		else {
			// else start with an empty slate.
			$stack = [];
		}

		// append the configured theme.
		$stack[] = Option::Get('surface-theme');

		// append the default theme stack.
		if(is_array(Option::Get('surface-theme-stack'))) {
			$stack = array_merge(
				$stack,
				Option::Get('surface-theme-stack')
			);
		}
		elseif(is_string(Option::Get('surface-theme-stack'))) {
			$stack[] = Option::Get('surface-theme');
		}

		// and return the stack.
		return $stack;
	}

	public function
	GetThemeURI($input) {
	/*//
	@argv string Input
	@return string
	prints or returns the string at the end of the surface uri. useful for
	linking to theme resources. accepts theme stacking to the point where
	you can overload the theme, but we will not test that the file exists
	in this case, so it will just be from the top of the stack.
	//*/

		$stack = explode(':',$input);
		$file = array_pop($stack);

		$stack = $this->GetThemeStack($stack);
		reset($stack);

		return sprintf(
			'/%s/%s/%s',
			trim(Option::Get('surface-theme-path'),'/'),
			current($stack),
			$input
		);
	}

}
