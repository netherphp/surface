<?php

namespace Nether\Surface;

use Nether\Common;

use Nether\Ki\CallbackPackage;

class Engine {

	use
	CallbackPackage;

	const
	ThemePageScripts = 'Theme.Page.Scripts',
	ThemePageStyles = 'Theme.Page.Styles';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public Common\Datastore
	$Data;

	public array
	$Themes;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected string
	$Content = '';

	protected bool
	$Capturing = FALSE;

	public string
	$ThemeRoot;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(Common\Datastore $Config) {

		$this->Data = new Common\Datastore;
		$this->ThemeRoot = $Config[Library::ConfThemeRoot];

		////////

		$Themes = $Config[Library::ConfThemes];

		if(is_array($Themes))
		$this->Themes = $Themes;

		elseif(is_string($Themes))
		$this->Themes = [ $Themes ];

		else
		$this->Themes = [];

		////////

		$this->Define(static::ThemePageScripts, new Common\Datastore);
		$this->Define(static::ThemePageStyles, new Common\Datastore);
		$this->LoadThemeFile();

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	CaptureBegin():
	static {

		$this->Capturing = ob_start(NULL, 0);

		return $this;
	}

	public function
	CaptureEnd(?bool $Append=TRUE):
	static {

		if($this->Capturing)
		match(TRUE) {

			// append content if boolean true.
			($Append === TRUE)
			=> $this->Content .= $this->CaptureFilter(ob_get_clean()),

			// replace content if boolean false.
			($Append === FALSE)
			=> $this->Content = $this->CaptureFilter(ob_get_clean()),

			// discard content if null.
			default
			=> ob_end_clean()

		};

		$this->Capturing = FALSE;

		return $this;
	}

	public function
	CaptureFilter(string $Input):
	string {

		$Output = $Input;

		return $Output;
	}

	public function
	IsCapturing():
	bool {

		return $this->Capturing;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Define(string $Key, mixed $Data):
	static {

		if(!$this->Data->HasKey($Key))
		$this->Data[$Key] = $Data;

		return $this;
	}

	public function
	Has(string $Key):
	bool {

		return isset($this->Data[$Key]) && $this->Data[$Key];
	}

	public function
	Get(string $Key):
	mixed {

		return $this->Data[$Key];
	}

	public function
	Set(string $Key, mixed $Data):
	static {

		$this->Data[$Key] = $Data;

		return $this;
	}

	public function
	Show(string $Key, bool $Encode=TRUE):
	static {

		if($Encode)
		echo htmlspecialchars($this->Get($Key) ?? '');

		else
		echo $this->Get($Key);

		return $this;
	}

	public function
	AddScriptURL(string $URL):
	static {

		if(!$this->Data[static::ThemePageScripts]->HasValue($URL))
		$this->Data[static::ThemePageScripts]->Push($URL);

		return $this;
	}

	public function
	AddStyleURL(string $URL):
	static {

		if(!$this->Data[static::ThemePageStyles]->HasValue($URL))
		$this->Data[static::ThemePageStyles]->Push($URL);

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Wrap(string $Area, array $Scope=[], ?string $Masq=NULL, ?string $Wrapper=NULL):
	static {

		$Scope['Area'] = $Area;
		$Wrapper ??= $this->Get('Theme.Page.Wrapper') ?? 'page-wrapper';
		$Masq ??= $Area;

		return $this->Area($Wrapper, $Scope, $Masq);
	}

	public function
	Area(string $Area, array $Scope=[], ?string $Masquerade=NULL):
	static {

		$Area = Util::MakePathableKey($Area);
		$Scope = $this->BuildRenderScope($Area, $Scope, $Masquerade);
		$File = $this->FindAreaFile($Area);

		if($File === NULL) {
			echo "[Area Not Found: {$Area}]\n";
			return $this;
		}

		echo $this->ExecAreaFile($File, $Scope);
		return $this;
	}

	public function
	GetArea(string $Area, array $Scope=[], ?string $Masquerade=NULL):
	string {

		ob_start();
		$this->Area($Area, $Scope, $Masquerade);

		return ob_get_clean();
	}

	public function
	GetContent():
	string {

		return $this->Content;
	}

	public function
	GetTheme():
	string {

		return $this->Themes[array_key_first($this->Themes)];
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Render():
	static {

		$DFile = $this->FindDesignFile();
		$Scope = $this->BuildRenderScope();
		$Scope['Output'] = $this->Content;

		if($DFile === NULL) {
			echo "[No Design Templates Found]";
			return $this;
		}

		/*
		$TFile = str_replace('design.phtml', 'design.php', $DFile);

		if(file_exists($TFile))
		$this->ExecThemeFile($TFile, $Scope);
		*/

		echo $this->ExecDesignFile($DFile, $Scope);

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	LoadThemeFile():
	static {

		$Design = $this->FindDesignFile();
		$Scope = [ 'Surface'=> $this ];

		if(!$Design)
		return $this;

		$File = str_replace(
			Common\Filesystem\Util::Repath('/design.phtml'),
			Common\Filesystem\Util::Repath('/design.php'),
			$Design
		);

		if(file_exists($File))
		$this->ExecThemeFile($File, $Scope);

		return $this;
	}

	public function
	BuildRenderScope(?string $Area=NULL, array $Scope=[], ?string $Masquerade=NULL):
	array {

		$Area = $Masquerade ?? $Area;

		$Output = [
			'Surface' => $this,
			'Area'    => $Area
		];

		// merge in globally bound items.

		$this->Flow('BuildGlobalScope', [ &$Output ]);

		// merge in area-specific items.

		if($Area !== NULL)
		$this->Flow(sprintf('BuildAreaScope(%s)', $Area), [ &$Output ]);

		// merge in deliberate items.

		$Output = array_merge($Output, $Scope);

		return $Output;
	}

	protected function
	ExecAreaFile(string $__FILENAME, array $__SCOPE=[]):
	string {

		extract($__SCOPE);

		ob_start();
		require($__FILENAME);

		return ob_get_clean();
	}

	protected function
	ExecDesignFile(string $__FILENAME, array $__SCOPE=[]):
	string {

		extract($__SCOPE);

		ob_start();
		require($__FILENAME);

		return ob_get_clean();
	}

	protected function
	ExecThemeFile(string $__FILENAME, array $__SCOPE=[]):
	void {

		$Jail = function(string $__FILENAME, $__SCOPE) {
			extract($__SCOPE);
			require_once($__FILENAME);
			return;
		};

		$Jail($__FILENAME, $__SCOPE);

		return;
	}

	protected function
	FindAreaFile(string $Area):
	?string {

		$Path = NULL;
		$Theme = NULL;

		foreach($this->Themes as $Theme) {
			$Path = realpath(sprintf(
				'%s/%s/area/%s.phtml',
				$this->ThemeRoot,
				$Theme,
				$Area
			));

			if(file_exists($Path))
			return $Path;
		}

		return NULL;
	}

	protected function
	FindDesignFile():
	?string {

		$Path = NULL;
		$Theme = NULL;

		foreach($this->Themes as $Theme) {
			$Path = realpath(sprintf(
				'%s/%s/design.phtml',
				$this->ThemeRoot,
				$Theme
			));

			if(file_exists($Path))
			return $Path;
		}

		return NULL;
	}

}
