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

	'surface-title-brand' => TRUE,
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

	if(!class_exists('Nether\Stash'))
	return;

	$Surface = Stash::Get(Option::Get('surface-auto-stash'));

	if($Surface && $Surface instanceof Surface)
	$Surface->SetAutoRender(FALSE)->Stop(FALSE);

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

	protected
	$Started = FALSE;
	/*//
	@type boolean
	a sentinel marking if this object has begun capturing a level of stdout
	or not.
	//*/

	protected
	$Rendered = FALSE;
	/*//
	@type boolean
	marks if this object has rendered before. this is mainly to prevent the
	destruct from trying again if we had manually called Render prior.
	//*/

	protected
	$Storage = [];
	/*//
	@type array
	where this surface instance stores the data to be used for rendering.
	//*/

	////////////////////////
	////////////////////////

	protected
	$AutoRender = FALSE;
	/*//
	@type bool
	if this object should automatically render when it is destroyed. this value
	is populated by the option surface-auto-render on creation.
	//*/

	public function
	GetAutoRender():
	Bool {

		return $this->AutoRender;
	}

	public function
	SetAutoRender(Bool $Val):
	self {

		$this->AutoRender = $Val;
		return $this;
	}

	////////
	////////

	protected
	$Style = '';
	/*//
	@type string
	the name of the subtheme for the theme to use if it wants. the library
	itself does not actually use this, but it is there to make subthemes easier
	to make.
	//*/

	public function
	GetStyle():
	String {

		return $this->Style;
	}

	public function
	SetStyle(String $Val):
	self {

		$this->Style = $Val;
		return $this;
	}

	////////
	////////

	protected
	$Theme = '';
	/*//
	@type string
	the name of the theme to render in.
	//*/

	public function
	GetTheme():
	String {

		return $this->Theme;
	}

	public function
	SetTheme(String $Val):
	self {
		$this->Theme = $Val;
		return $this;
	}

	////////
	////////

	protected
	$ThemeRoot = '';
	/*//
	@type string
	the directory all the themes are installed.
	//*/

	public function
	GetThemeRoot():
	String {

		return $this->ThemeRoot;
	}

	public function
	SetThemeRoot(String $Val):
	self {

		$this->ThemeRoot = $Val;
		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	// magic object behaviour methods /////////////////////////////////////////

	public function
	__Construct(?Array $Opt=NULL) {
	/*//
	handle object construction.
	//*/

		$Opt = new Nether\Object\Mapped($Opt,[
			'Theme'       => Option::Get('surface-theme'),
			'ThemeRoot'   => Option::Get('surface-theme-root'),
			'Style'       => Option::Get('surface-theme-style'),
			'AutoCapture' => Option::Get('surface-auto-capture'),
			'AutoStash'   => Option::Get('surface-auto-stash'),
			'AutoRender'  => Option::Get('surface-auto-render')
		]);

		$Env = php_sapi_name();
		$this->Storage['stdout'] = '';

		// pull in default settings.

		$this->AutoRender = (($Env === 'cli')?(FALSE):($Opt->AutoRender));
		$this->Theme = $Opt->Theme;
		$this->ThemeRoot = $Opt->ThemeRoot;
		$this->Style = $Opt->Style;

		// if auto stashing is enabled.

		if(is_string($Opt->AutoStash))
		if(class_exists('Nether\Stash'))
		if(!Stash::Has($Opt->AutoStash))
		Stash::Set($Opt->AutoStash,$this);

		// begin capture if autocapture is enabled and this is not the
		// command line interface.

		if($Opt->AutoCapture && $Env !== 'cli')
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

		$Argv = func_get_args();

		switch(count($Argv)) {
			case 1: {
				return $this->Get(...$Argv);
			}
			case 2: {
				$this->Set(...$Argv);
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
	Start():
	Bool {
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
	Stop(Bool $Keep=TRUE):
	?String {
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

		$this->Started = NULL;
		$Output = ob_get_clean();

		// allow things to filter this content.

		Ki::Flow('surface-stdout',[&$Output]);

		// append it if we elected to keep it.

		if($Keep) {
			$this->Storage['stdout'] .= $Output;
			return NULL;
		}

		// else return it and forget it.
		return $Output;
	}

	public function
	Render(Bool $Return=FALSE):
	?String {
	/*//
	@return boolean
	begin the rendering operation using the full page template.
	//*/

		// flag this as called.

		$this->Rendered = TRUE;

		// fetch whatever is still hanging on in the buffer.

		$this->Stop(TRUE);

		// check if we had decided on a theme yet.

		if(!$this->Theme)
		$this->Theme = Nether\Option::Get('surface-theme');

		// determine the template file to use.

		if(!($Template = $this->GetThemeFile('design.phtml')))
		throw new Exception("error opening {$Template} for {$this->Theme}");

		// notification of beginning a render process.

		Nether\Ki::Flow('surface-render-init',[$this],FALSE);

		// run through the framework settings to generate some common meta data
		// like page title if the data hasn't already been defined.

		$this->PrepareTitle();
		$this->PrepareKeywords();
		$this->PrepareDescription();

		////////
		////////

		ob_start();

		call_user_func(
			function($__Filename,$__Scope){
				extract($__Scope); unset($__Scope);
				require($__Filename);
				return;
			},
			$Template,
			$this->GetRenderScope('design')
		);

		if(!$Return) {
			// print it out if we didn't want it back.
			echo ob_get_clean();
			return NULL;
		}

		// hand it back if we wanted it.
		return ob_get_clean();
	}

	///////////////////////////////////////////////////////////////////////////
	// metadata methods ///////////////////////////////////////////////////////

	/*// these methods will use the application configuration to generate some
	common meta data that is useful for webpages. //*/

	protected function
	PrepareTitle():
	Void {
	/*//
	generate or modify a page-title value.
	//*/

		$Title = NULL;

		if(!Option::Get('surface-title-brand'))
		return;

		////////

		$Title = $this->Get('Page.Title') ?? $this->Get('page-title');
		if($Title) {
			$this->PrepareTitle_FromValue($Title);
			return;
		}

		$Title = Option::Get('App.Name') ?? Option::Get('app-name');
		if($Title) {
			$this->PrepareTitle_FromNothing();
			return;
		}

		return;
	}

	protected function
	PrepareTitle_FromValue(String $Input):
	Void {

		$Brand = '';
		$Final = '';

		$Brand = Option::Get('App.Name') ?? Option::Get('app-name');

		if(!$Brand)
		return;

		$Final = "{$Input} - {$Brand}";

		// modify the original value then.

		$this
		->Set('Page.Title',$Final)
		->Set('page-title',$Final);

		return;
	}

	protected function
	PrepareTitle_FromNothing():
	Void {
	/*//
	and from the ashes will arise a new page title. if our view or app has
	not yet described a page title then attempt to generate one if we have
	other data like app name and description set.
	//*/

		$Brand = '';
		$Desc = '';
		$Final = '';

		$Brand = Option::Get('App.Name') ?? Option::Get('app-name');
		$Desc = Option::Get('App.Desc.Short') ?? Option::Get('app-short-desc');

		if(!$Brand && !$Desc)
		return;

		// generate a final string.

		if($Brand && $Desc)
		$Final = "{$Brand} - {$Desc}";
		elseif($Brand)
		$Final = $Brand;

		if(!$Final)
		return;

		// modify the original value then.

		$this
		->Set('Page.Title',$Final)
		->Set('page-title',$Final);

		return;
	}

	protected function
	PrepareKeywords():
	Void {
	/*//
	generate a page-keywords if one has not yet been defined.
	//*/

		$Keywords = $this->Get('Page.Keywords') ?? $this->Get('page-keywords');

		if(!$Keywords)
		$Keywords = Option::Get('App.Keywords') ?? Option::Get('App.Keywords');

		if(!$this->Has('Page.Keywords'))
		$this->Set('Page.Keywords',$Keywords);

		if(!$this->Has('page-keywords'))
		$this->Set('page-keywords',$Keywords);

		return;
	}

	protected function
	PrepareDescription():
	Void {
	/*//
	generate a page-desc if one has not yet been defined.
	//*/

		$Desc = $this->Get('Page.Desc') ?? Option::Get('app-long-desc');
		if(!$Desc) return;

		if(!$this->Has('Page.Desc'))
		$this->Set('Page.Desc',$Desc);

		if(!$this->Has('page-desc'))
		$this->Set('page-desc',$Desc);

		return;
	}

	////////////////////////////////////////////////////////////////////////////
	// new area api ////////////////////////////////////////////////////////////

	public function
	GetArea(String $Which, Array|Object $Opt=NULL):
	String {
	/*//
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

		$Stack = explode(':',$Which);
		$Area = array_pop($Stack);

		$Filename = $this->GetThemeFile(
			"area/{$Area}.phtml",
			$Stack
		);

		if(!$Filename)
		throw new Exception("no surface area matching {$Which} could be located.");

		// notification of beginning a render process.
		Nether\Ki::Flow('surface-render-init',[$this],FALSE);

		////////
		////////

		ob_start();
		call_user_func(
			function($__Filename,$__Scope){
				extract($__Scope); unset($__Scope);
				require($__Filename);
				return;
			},
			$Filename,
			$this->GetRenderScope($Area,$Opt)
		);
		return ob_get_clean();
	}

	public function
	ShowArea(String $Which, Array|Object $Opt=NULL):
	self {
	/*//
	wraps the GetArea method for instant printign.
	//*/

		echo $this->GetArea($Which,$Opt);
		return $this;
	}

	////////////////////////////////////////////////////////////////////////////
	// data storage api ////////////////////////////////////////////////////////

	public function
	Define($Key,$Val):
	self {
	/*//
	@date 2017-12-20
	add data in storage only if it does not yet exist.
	//*/

		if(!array_key_exists($Key,$this->Storage))
		$this->Storage[$Key] = $Val;

		return $this;
	}

	public function
	Get($What) {
	/*//
	@argv string Key
	@argv array(string Key, ...)
	@return mixed
	return the value stored in this surface object. if given a string the value
	associated with it is returned. if given an array of keys, an array of
	values associated with them will be returned instead.
	//*/

		if(is_string($What))
		return $this->Get_ByString($What);

		if(is_array($What))
		return $this->Get_ByArray($What);

		return NULL;
	}

	protected function
	Get_ByString(String $Key) {
	/*//
	@argv string Key
	@return mixed
	return the data stored in the specified key.
	//*/

		if(array_key_exists($Key,$this->Storage))
		return $this->Storage[$Key];

		return NULL;
	}

	protected function
	Get_ByArray(Array $List):
	Array {
	/*//
	given an array of keys to lookup, return an array of data
	indexed by those same keys.
	//*/

		$Return = [];
		$Key = NULL;

		foreach($List as $Key)
		$Return[$Key] = $this->Get_ByString($Key);

		return $Return;
	}

	public function
	Has(String $Key):
	Bool {
	/*//
	@date 2020-05-25
	//*/

		return array_key_exists($Key,$this->Storage);
	}

	public function
	Set($What, $Value=NULL):
	self {
	/*//
	@argv string Key, Mixed Value
	@argv array(string Key => mixed Value, ...)
	sets values in the surface storage. can take a string key and value, or an
	associative array of multiple keys and values to set.
	//*/

		if(is_string($What))
		return $this->Set_ByString($What,$Value);

		if(is_array($What))
		return $this->Set_ByArray($What);

		return $this;
	}

	protected function
	Set_ByString($Key,$Val):
	self {
	/*//
	@argv string Key, mixed Val
	@return self
	//*/

		$this->Storage[$Key] = $Val;
		return $this;
	}

	protected function
	Set_ByArray(Array $Data):
	self {
	/*//
	@argv array Dataset
	@return self
	//*/

		foreach($Data as $Key => $Val)
		$this->Set_ByString($Key,$Val);

		return $this;
	}

	public function
	Show(String $Key, Bool $Safety=TRUE):
	self {
	/*//
	echo the data under the selected key.
	//*/

		if(array_key_exists($Key,$this->Storage)) {
			if($Safety) echo htmlentities($this->Storage[$Key]);
			else echo $this->Storage[$Key];
		}

		return $this;
	}

	public function
	Push(Array $Items, ?String $Area=NULL):
	self {
	/*//
	@date 2020-05-24
	pushes an array of items into the surface scope system to make variables
	inside of area files automatically. the second argument will restrict
	the created variables to the specified area file.
	//*/

		$Event = "surface-render-scope";

		if($Area !== NULL)
		$Event = "surface-render-scope-{$Area}";

		Nether\Ki::Queue(
			$Event,
			function(Array &$Scope) use ($Items):
			Void {
				$Key = NULL;
				$Val = NULL;

				foreach($Items as $Key => $Val)
				$Scope[$Key] = $Val;

				return;
			},
			TRUE
		);

		return $this;
	}

	////////////////
	////////////////

	public function
	GetRenderScope($Area=NULL, $Opt=NULL):
	Array {
	/*//
	@date 2015-07-30
	allow other libraries to attach data to the render system to create a
	variable to be accessable within theme templates. the scope is an
	associative array passed around by reference. in the theme file it will
	extract the array into variables. can be given specific area files to
	scope for.
	//*/

		$Opt = new Nether\Object\Mapped($Opt,[
			'Masquerade' => NULL,
			'Scope'      => NULL // @todo 2021-01-18
		]);

		$Area = $Opt->Masquerade ?? $Area;

		////////

		$Scope = [
			'Surface' => $this,
			'Area'    => $Area
		];

		// compile any global scope items.

		Nether\Ki::Flow(
			"surface-render-scope",
			[&$Scope]
		);

		// compile any area specific scope items.

		if($Area !== NULL)
		Nether\Ki::Flow(
			"surface-render-scope-{$Area}",
			[&$Scope]
		);

		return $Scope;
	}

	////////////////
	////////////////

	public function
	GetThemeFile(String $Name, $Stack=NULL):
	?String {
	/*//
	run through the theme stack and attempt to locate a file that matches the
	request. if found it returns the full filepath to that file - if not then
	it returns boolean false.
	//*/

		$Theme = NULL;

		// if we passed a stacked request handle it. else assume the stack
		// was passed explictly in the stack argument already.

		if(strpos($Name,':') !== FALSE) {
			$Stack = explode(':',$Name);
			$Name = array_pop($Stack);
		}

		foreach($this->GetThemeStack($Stack) as $Theme) {
			$Filename = sprintf(
				'%s/%s/%s',
				$this->ThemeRoot,
				$Theme,
				$Name
			);

			if(!file_exists($Filename) || !is_readable($Filename))
			continue;

			return $Filename;
		}

		return NULL;
	}

	public function
	GetThemeStack($Input=NULL):
	Array {
	/*//
	@argv string ThemeStackSpecification
	@argv array ThemeStackList
	if given a string it will split the string on colons to generate the
	input list of the theme stack. this is how stacks will be custom
	selected by the area method. if given an array then that is just it.
	it returns a list of all the themes to check for requested files.
	//*/

		$Stack = NULL;

		// accept a string:like:this for custom stacking.
		if(is_string($Input))
		$Stack = explode(':',$Input);

		// accept a straight array.
		elseif(is_array($Input))
		$Stack = $Input;

		// else start with an empty slate.
		else
		$Stack = [];

		////////

		$Stack[] = $this->Theme;

		if(is_array(Option::Get('surface-theme-stack')))
		$Stack = array_merge(
			$Stack,
			Option::Get('surface-theme-stack')
		);

		elseif(is_string(Option::Get('surface-theme-stack')))
		$Stack[] = Option::Get('surface-theme');

		// and return the stack.
		return $Stack;
	}

	public function
	GetThemeURI(String $Input) {
	/*//
	@argv string Input
	@return string
	prints or returns the string at the end of the surface uri. useful for
	linking to theme resources. accepts theme stacking to the point where
	you can overload the theme, but we will not test that the file exists
	in this case, so it will just be from the top of the stack.
	//*/

		$Stack = explode(':',$Input);
		$File = array_pop($Stack);

		$Stack = $this->GetThemeStack($Stack);

		return sprintf(
			'/%s/%s/%s',
			trim(Option::Get('surface-theme-path'),'/'),
			$Stack[0],
			$File
		);
	}

	///////////////////////////////////////////////////////////////////////////
	// magic api //////////////////////////////////////////////////////////////

	// some apis for contextual aware usages.

	public function
	Area(String $Input, $Opt=NULL) {
	/*//
	@date 2015-07-28
	//*/

		$Opt = new Nether\Object\Mapped($Opt,[
			'Return'     => FALSE,
			'Masquerade' => NULL,
			'Scope'      => NULL
		]);

		////////

		if(!$Opt->Return)
		return $this->ShowArea($Input,$Opt);

		return $this->GetArea($Input,$Opt);
	}

}
