<?php

namespace Nether\Surface;
use Nether;

use Nether\Object\Datastore;
use Nether\Object\Prototype;
use Nether\Ki\CallbackPackage;

class Engine {

	use
	CallbackPackage;

	public Datastore
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
	__Construct(Datastore $Config) {

		$this->Data = new Datastore;
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

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

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

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

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

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Render():
	static {

		$File = $this->FindDesignFile();
		$Scope = $this->BuildRenderScope();
		$Scope['Output'] = $this->Content;

		if($File === NULL) {
			echo "[No Design Templates Found]";
			return $this;
		}

		echo $this->ExecDesignFile($File, $Scope);
		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

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
	FindAreaFile(string $Area):
	?string {

		$Path = NULL;

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
