<?php

namespace Nether;
use \Nether;
use \Exception;

///////////////////////////////////////////////////////////////////////////////
// library options ////////////////////////////////////////////////////////////

Option::Define([

	'surface-auto-capture' => true,
	/*//
	@option surface-auto-capture boolean
	if true, surface will start recording STDOUT into its overbuffer the
	moment the object is created. if false, you will need to manually call
	the Start method to begin recording.
	//*/

	'surface-auto-stash' => 'surface',
	/*//
	@option surface-auto-stash string
	if a string and Nether\Stash exists, this is the key name Surface will
	automatically tuck itself into. set to false to disable this behaviour.
	//*/

	'surface-auto-render' => true,
	/*//
	@option surface-auto-render boolean
	if true, surface will automatically render the final product when the
	object instance is destroyed.
	//*/

	'surface-theme' => 'default',
	/*//
	@option surface-theme string
	the default value to use as the main page theme. this is the theme we will
	pull the design.phtml for when all is ready to go.
	//*/

	'surface-theme-stack' => [ 'common' ],
	/*//
	@option surface-theme-stack array[string, ...]
	a list of themes to fall back on if a file was requested from the
	configured theme could not be found.
	//*/

	'surface-theme-style' => 'default',
	/*//
	@option surface-theme-style string
	the default value to use as the "Style" of the theme. the style is not
	used by anything in Surface, but exists here to provide a mechanism for
	allowing something like a user selected subtheme, or whatever.
	//*/

	'surface-title-brand' => true,
	/*//
	@option surface-title-brand boolean
	if true it will attempt to brand your page-title value with data from
	app name. if no page-title had ever been set by the app then it will
	attempt ot use both app-name and app-short-desc.
	//*/

	'surface-theme-root' => sprintf('%s/themes',rtrim(Option::Get('nether-web-root'),'/')),
	/*//
	@option surface-theme-root string
	defines the full filepath to the themes directory as the server needs to
	know it.
	//*/

	'surface-theme-path' => sprintf('%s/themes',rtrim(Option::Get('nether-web-path'),'/'))
	/*//
	@option surface-theme-path string
	defines the relative uri to the themes directory as the browser needs to
	know it.
	//*/

]);

///////////////////////////////////////////////////////////////////////////////
// library ki event handlers //////////////////////////////////////////////////

Ki::Queue('avenue-redirect',function(){
	// if a redirect was requested shut down the automatic surface instance and
	// throw away whatever it already collected.

	if(!class_exists('Nether\Stash')) return;

	$surface = Stash::Get(Option::Get('surface-auto-stash'));

	if($surface && $surface instanceof Surface)
	$surface->Stop(false);

	return;
},true);

///////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

class Surface {
/*//
this is the engine engine designed to make it easy to application output into
page templates with ease. there is no template language, the templates are
plain html with embedded php calls to the various surface methods. just in case
you have forgotten, php *is* a templating language.

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

	protected $Rendered = false;
	/*//
	@type boolean
	marks if this object has rendered before. this is mainly to prevent the
	destruct from trying again if we had manually called Render prior.
	//*/

	protected $Storage = [];
	/*//
	@type array
	where this surface instance stores the data to be used for rendering.
	//*/

	////////////////////////
	////////////////////////

	protected $AutoRender = false;
	/*//
	@type bool
	if this object should automatically render when it is destroyed. this value
	is populated by the option surface-auto-render on creation.
	//*/

	public function
	GetAutoRender() { return $this->AutoRender; }

	public function
	SetAutoRender($b) { $this->AutoRender = (bool)$b; return $this; }

	////////
	////////

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

	///////////////////////////////////////////////////////////////////////////
	// magic object behaviour methods /////////////////////////////////////////

	public function
	__Construct($opt=null) {
	/*//
	handle object construction.
	//*/

		$opt = new Nether\Object($opt,[
			'Theme'       => Option::Get('surface-theme'),
			'ThemeRoot'   => Option::Get('surface-theme-root'),
			'Style'       => Option::Get('surface-theme-style'),
			'AutoCapture' => Option::Get('surface-auto-capture'),
			'AutoStash'   => Option::Get('surface-auto-stash'),
			'AutoRender'  => Option::Get('surface-auto-render')
		]);

		$this->Storage['stdout'] = '';

		// pull in default settings.
		$this->AutoRender = $opt->AutoRender;
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
	handle object destruction.
	//*/

		if($this->AutoRender && !$this->Rendered)
		$this->Render();

		return;
	}

	public function
	__Invoke() {
	/*//
	@argv string Key
	provide a shortcut for getting and setting data. if one argument
	is provided then it will get the value. if two, it will set it.
	//*/

		$argv = func_get_args();
		switch(count($argv)) {
			case 1: {
				return $this->Get($argv[0]);
			}
			case 2: {
				$this->Set($argv[0],$argv[1]);
				return $this;
			}
		}

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	// rendering control api //////////////////////////////////////////////////

	/*// these methods will manage the overbuffer recording and deal with
	compiling the resulting page from the configured template. //*/

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
		if(!$this->Started) return false;

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

	public function
	Render($return=false) {
	/*//
	@return boolean
	begin the rendering operation using the full page template.
	//*/

		// flag this as called.
		$this->Rendered = true;

		// fetch whatever is still hanging on in the buffer.
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

	///////////////////////////////////////////////////////////////////////////
	// metadata methods ///////////////////////////////////////////////////////

	/*// these methods will use the application configuration to generate some
	common meta data that is useful for webpages. //*/

	protected function
	PrepareTitle() {
	/*//
	generate or modify a page-title value.
	//*/

		if($this->Get('page-title'))
		$this->PrepareTitle_FromExisting();

		else
		$this->PrepareTitle_FromNothing();

		return;
	}

	protected function
	PrepareTitle_FromNothing() {
	/*//
	and from the ashes will arise a new page title. if our view or app has
	not yet described a page title then attempt to generate one if we have
	other data like app name and description set.
	//*/

		// no name, no branding.
		if(!Option::Get('surface-title-brand') || !Option::Get('app-name'))
		return $this;

		// if we have a description lets use it too.
		if(Option::Get('app-short-desc'))
		$this->Set('page-title',sprintf(
			'%s - %s',
			Option::Get('app-name'),
			Option::Get('app-short-desc')
		));

		// else just use the app name.
		else
		$this->Set('page-title',Option::Get('app-name'));

		return $this;
	}

	protected function
	PrepareTitle_FromExisting() {
	/*//
	append the site name if enabled and defined to the page title that
	was generated at some point by the app.
	//*/

		if(!Option::Get('surface-title-brand') || !Option::Get('app-name'))
		return $this;

		$this->Set('page-title',sprintf(
			'%s - %s',
			$this->Get('page-title'),
			Option::Get('app-name')
		));

		return $this;
	}

	protected function
	PrepareKeywords() {
	/*//
	generate a page-keywords if one has not yet been defined.
	//*/

		// if the app has already defined keywords then do not overwite them.
		if($this->Get('page-keywords'))
		return $this;

		// use the app keywords if defined.
		if(Option::Get('app-keywords'))
		$this->Set('page-keywords',Option::Get('app-keywords'));

		return $this;
	}

	protected function
	PrepareDescription() {
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
	Show($key,$safety=true) {
	/*//
	@argv string Key
	@return self
	echo the data under the selected key.
	//*/

		if(array_key_exists($key,$this->Storage)) {
			if($safety) echo htmlentities($this->Storage[$key]);
			else echo $this->Storage[$key];
		}

		return $this;
	}

	////////////////
	////////////////

	public function
	GetRenderScope() {
	/*//
	@return array
	allow other libraries to attach data to the render system to create a
	variable to be accessable within theme templates. the scope is an
	associative array passed around by reference. in the theme file it will
	extract the array into variables.
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

	public function
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

		// if we passed a stacked request handle it. else assume the stack
		// was passed explictly in the stack argument already.
		if(strpos($name,':') !== false) {
			$stack = explode(':',$name);
			$name = array_pop($stack);
		}

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

	public function
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
		$stack[] = $this->Theme;

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

		return sprintf(
			'/%s/%s/%s',
			trim(Option::Get('surface-theme-path'),'/'),
			$stack[0],
			$file
		);
	}

	///////////////////////////////////////////////////////////////////////////
	// deprecated / backwards compat crap /////////////////////////////////////

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

}
