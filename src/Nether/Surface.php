<?php

namespace Nether;
use \Nether;

////////////////
////////////////

Option::Define([
	'surface-autocapture' => true,
	/*//option//
	@name surface-autocapture
	@type bool
	@default true
	//*/

	'surface-autostash' => true,
	/*//option//
	@name surface-autostash
	@type bool
	@default true
	//*/

	'surface-stash-name' => 'surface',
	/*//option//
	@name surface-stash-name
	@type string
	@default "avenue"
	//*/

	'surface-theme' => 'default',
	/*//option//
	@name surface-theme
	@type string
	@default "default"
	//*/

	'surface-theme-common' => 'common',
	/*//option//
	@name surface-theme-option
	@type string
	@default "common"
	//*/

	'surface-style' => 'default',
	/*//option//
	@name surface-style
	@type string
	@default "default"
	//*/

	'surface-theme-root' => sprintf(
		'%s/themes',
		Option::Get('nether-web-root')
	),
	/*//option//
	@name surface-theme-root
	@type string
	@default "%nether-web-root%/themes"
	the filesystem path to the themes directory.
	//*/

	'surface-theme-path' => sprintf(
		'%s/themes',
		trim(Option::Get('nether-web-path'),'/')
	),
	/*//option//
	@name surface-theme-path
	@type string
	@default "%nether-web-path%/themes"
	the web path to the themes directory.
	//*/

	'surface-title-brand' => true
	/*//option//
	@name surface-title-brand
	@type bool
	@default true
	define if surface should brand the page title with the app-name.
	//*/
]);

////////////////
////////////////

if(class_exists('Nether\Avenue\Router') && class_exists('Nether\Stash'))
Ki::Queue('nether-avenue-redirect',function(){
	// if a redirect was requested shut down the automatic surface instance and
	// throw away whatever it already collected.

	$surface = Stash::Get(Option::Get('surface-stash-name'));

	if($surface && $surface instanceof Surface)
	$surface->Stop(false);

	return;
},true);

////////////////
////////////////

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

	protected $Rendered = false;
	/*//
	@type boolean
	a sentinel marking if this object has performed a render operation yet or
	not.
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

	protected $Style;
	/*//
	@type string
	the name of the subtheme for the theme to use if it wants. the library
	itself does not actually use this, but it is there to make subthemes easier
	to make.
	//*/

	protected $Theme;
	/*//
	@type string
	the name of the theme to render in.
	//*/

	protected $ThemeRoot;
	/*//
	@type string
	the directory all the themes are installed.
	//*/

	////////////////
	////////////////

	public function __construct($opt=null) {
		$opt = new Nether\Object($opt,[
			'Theme' => Option::Get('surface-theme'),
			'ThemeRoot' => Option::Get('surface-theme-root'),
			'Style' => Option::Get('surface-style'),
			'Autocapture' => Option::Get('surface-autocapture'),
			'Autostash' => Option::Get('surface-autostash'),
			'StashName' => Option::Get('surface-stash-name')
		]);

		$this->Storage['stdout'] = '';

		// pull in default settings.
		$this->Theme = $opt->Theme;
		$this->ThemeRoot = $opt->ThemeRoot;
		$this->Style = $opt->Style;

		// stash if autostash is enabled.
		if($opt->Autostash && class_exists('Nether\Stash')) {
			if(!Stash::Has($opt->StashName)) {
				Stash::Set($opt->StashName,$this);
			}
		}

		// begin capture if autocapture is enabled.
		if($opt->Autocapture && php_sapi_name() !== 'cli')
		$this->Start();

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

		if($this->Started && !$this->Rendered)
		$this->Render();

		return;
	}

	////////////////
	////////////////

	public function Render($return=false) {
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

		// allow application components to bolt data into the theme scope via
		// the surface-render-scope ki.
		$scope = $this->GetRenderScope();

		// run through the framework settings to generate some common meta data
		// like page title if the data hasn't already been defined.
		$this->PrepareTitle();
		$this->PrepareKeywords();
		$this->PrepareDescription();

		// attempt to load the page template.

		if($return) ob_start();

		if(!Nether\Util\File::Execute($template = $this->GetTemplateFilename(),$scope))
		throw new \Exception("error opening {$template} for {$this->Theme}");

		$this->Rendered = true;

		if($return) return ob_get_clean();
		else return;
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

	////////////////
	////////////////

	public function Start() {
	/*//
	@return boolean
	begin capturing stdout if this object has not already done so. returns if
	the capture was a success or not... not that i have ever seen ob_start fail.
	//*/

		if(!$this->Started)
		$this->Started = ob_start();

		return $this->Started;
	}

	public function Stop($append=true) {
	/*//
	@return mixed
	@argv boolean Append default true
	stop capturing stdout. if the append argument is true it will throw the
	caught data into the storage array, else it will return and discard it.
	//*/

		if($this->Started) {
			$this->Started = false;
			$stdout = ob_get_clean();

			Ki::Flow('surface-stdout',[&$stdout]);
			/*//ki//
			@name surface-stdout
			@argv &string Output
			allow the captured standard output to be filtered by plugins.
			//*/

			if($append) {
				$this->Storage['stdout'] .= $stdout;
				return;
			} else {
				return $stdout;
			}
		}

		return;
	}

	////////////////
	////////////////

	////////////////////////////////////////////////////////////////////////////
	// new area api ////////////////////////////////////////////////////////////

	public function Area($input,$return=false) {
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

	public function GetArea($which) {

		// construct a stack of themes to search for the area file in. you can
		// define them by prefixing areas and separating multiple themes with
		// a semicolon. the configured theme and configured commonspace will
		// be appended to the end as fallbacks.

		// path/to/area
		// something:path/to/area
		// something:else:path/to/area

		if(strpos($which,':') !== false) {
			$themestack = explode(':',$which);

			end($themestack);
				$areapath = current($themestack);
				unset($themestack[key($themestack)]);
			reset($themestack);
		} else {
			$areapath = $which;
			$themestack = [];
		}

		$themestack[] = $this->Theme;
		$themestack[] = Option::Get('surface-theme-common');

		////////
		////////

		// determine if the area file was found anywhere in our theme stack. we
		// will stop at the first theme that had it and run with it.

		$found = false;

		foreach($themestack as $theme) {
			$areafile = sprintf(
				'%s/%s/area/%s.phtml',
				$this->ThemeRoot,
				$theme,
				$areapath
			);

			if(file_exists($areafile)) {
				$found = true;
				break;
			}
		}

		if(!$found) {
			return "Surface Area {$which} could not be located.";
		}

		////////
		////////

		// run the area file within a protected scope.

		ob_start();
		call_user_func(
			function($__surface_file,$__surface_scope){
				extract($__surface_scope);
				require($__surface_file);
			},
			$areafile,
			$this->GetRenderScope()
		);
		return ob_get_clean();
	}

	public function ShowArea($which) {
		echo $this->GetArea($which);
		return $this;
	}

	////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	public function Get($what) {
	/*//
	@argv string Key
	@argv array(string Key, ...)
	@return mixed
	return the value stored in this surface object. if given a string the value
	associated with it is returned. if given an array of keys, an array of
	values associated with them will be returned instead.
	//*/

		// if given a string find that value and return it.
		if(is_string($what)) {
			if(array_key_exists($what,$this->Storage)) {
				return $this->Storage[$what];
			} else return false;
		}

		// if given an array, search for all the keys and return an array
		// indexed by the keys requested.
		if(is_array($what)) {
			$return = [];
			foreach($what as $key) {
				if(array_key_exists($key,$this->Storage)) {
					$return[$key] = $this->Storage[$key];
				} else $return[$key] = false;
			}
			return $return;
		}

		return false;
	}

	public function Set($what,$value=null) {
	/*//
	@argv string Key, Mixed Value
	@argv array(string Key => mixed Value, ...)
	@return self
	sets values in the surface storage. can take a string key and value, or an
	associative array of multiple keys and values to set.
	//*/

		// if the first parameter was a string, the second is the value and
		// we will store it.
		if(is_string($what)) {
			$this->Storage[$what] = $value;
			return $this;
		}

		// if the first parameter was an array, then it will be treated as a
		// key value list to be stored.
		if(is_array($what)) {
			foreach($what as $key => $value)
			$this->Storage[$key] = $value;
		}

		return $this;
	}

	public function Show($key) {
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

	public function GetStyle() {
	/*//
	@return string or false
	get the name of the substyle the theme will use.
	//*/

		return (($this->Style)?:(false));
	}

	public function GetTheme() {
	/*//
	@return string or false
	get the name of the theme this surface will use.
	//*/

		return (($this->Theme)?:(false));
	}

	public function GetThemeRoot() {
	/*//
	@return string;
	get the path the themes are installed in.
	//*/

		return $this->ThemeRoot;
	}

	public function SetStyle($style) {
	/*//
	@argv string Style
	@return self
	set the name of the substyle to use.
	//*/

		$this->Style = (string)$style;
		return $this;
	}

	public function SetTheme($theme) {
	/*//
	@argv string Theme
	@return self
	set the name of the theme to use.
	//*/

		$this->Theme = (string)$theme;
		return $this;
	}

	public function SetThemeRoot($path) {
	/*//
	@argv string Path
	@return self
	set the path the themes are installed in.
	//*/

		$this->ThemeRoot = $path;
		return $this;
	}

	////////////////
	////////////////

	protected function GetRenderScope() {
	/*//
	@return array
	allow other libraries to attach data to the render system to create a
	variable to be accessable within theme templates.
	//*/

		$scope = ['surface'=>$this];
		Ki::Flow('surface-render-scope',[&$scope]);
		/*//ki//
		@name surface-render-scope
		@argv &array ScopeList
		passes an associative array reference around to anyone who cares to
		listen and add to. the idea is that you can add a key and a value, and
		that will become a variable that is accessable within surface templates.
		//*/

		return $scope;
	}

	protected function GetTemplateFilename($commonfb=true) {
	/*//
	@return string
	return the path to the design.phtml for the current theme.
	//*/

		$filename = sprintf(
			'%s/%s/design.phtml',
			$this->ThemeRoot,
			$this->Theme
		);

		if(!file_exists($filename) && $commonfb) {
			$filename = sprintf(
				'%s/%s/design.phtml',
				$this->ThemeRoot,
				Option::Get('surface-theme-common')
			);
		}

		return $filename;
	}

	////////////////
	////////////////

	public function FromTheme($input,$return=false) {
	/*//
	@argv string Input, boolean Return default false
	@return string
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
	@argv string Input, boolean Return default false
	@return string
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
	gets the value from the post data making it html safe. useful for dropping
	data into html form value attributes easily.
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
