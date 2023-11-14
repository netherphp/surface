<?php

namespace Nether\Surface;

use Nether\Common;

use Stringable;

#[Common\Meta\Date('2023-09-12')]
#[Common\Meta\Info('Base class for building UI widgets in templates.')]
abstract class Element
extends Common\Prototype
implements Stringable {

	#[Common\Meta\Info('Define the theme template file. Subclasses should (re)define this.')]
	public string
	$Area;

	#[Common\Meta\PropertyFactory('FromArray', 'JSModules')]
	#[Common\Meta\Info('Define any JS module files associated with this widget. Subclasses should (re)define this.')]
	public array|Common\Datastore
	$JSModules = [];

	#[Common\Meta\PropertyFactory('FromArray', 'JSReady')]
	#[Common\Meta\Info('Define any JS lines to print as document ready code.')]
	public array|Common\Datastore
	$JSReady = [];

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	#[Common\Meta\PropertyFactory([ Common\UUID::class, 'V7' ])]
	public string
	$UUID;

	#[Common\Meta\PropertyObjectify]
	public Common\Datastore
	$Data;

	public Engine
	$Surface;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(Engine $Surface) {

		$this->Surface = $Surface;
		parent::__Construct();

		// in this instance it is asked child classes redefine a few
		// properties and that will undo the PropertyFactory if overwritten
		// without one.

		if(is_array($this->JSModules))
		$this->JSModules = Common\Datastore::FromArray($this->JSModules);

		if(is_array($this->JSReady))
		$this->JSReady = Common\Datastore::FromArray($this->JSReady);

		return;
	}

	public function
	__ToString():
	string {

		return $this->Render();
	}

	static public function
	FromSurface(Engine $Surface):
	static {

		return new static($Surface);
	}

	static public function
	FromSurfaceWith(Engine $Surface, iterable $Props):
	static {

		$Output = static::FromSurface($Surface);
		$Key = NULL;
		$Val = NULL;

		foreach($Props as $Key => $Val)
		if(property_exists($Output, $Key)) {

			if(isset($Output->{$Key})) {
				// if we tried to fill a datastore with something it
				// can consume then do it.
				if($Output->{$Key} instanceof Common\Datastore) {
					if(is_iterable($Val)) {
						$Output->{$Key}->SetData($Val);
						continue;
					}
				}
			}

			// else fall back to the assignment.

			$Output->{$Key} = $Val;
		}

		return $Output;
	}

	////////////////////////////////////////////////////////////////
	// LOCAL: Element Events ///////////////////////////////////////

	#[Common\Meta\Info('Runs when a render begins prior to the generation.')]
	protected function
	OnRender():
	static {

		return $this;
	}

	#[Common\Meta\Info('Runs after a render to filter the output.')]
	protected function
	OnRenderPost(string $Output):
	string {

		return $Output;
	}

	////////////////////////////////////////////////////////////////
	// LOCAL: Misc Info API ////////////////////////////////////////

	public function
	GetID():
	string {

		if(Common\Filters\Text::UUID($this->UUID))
		return sprintf(
			'el-%s',
			preg_replace('#[^a-z0-9]#', '', $this->UUID)
		);

		return $this->UUID;
	}

	public function
	GetSelectorID():
	string {

		return "#{$this->GetID()}";
	}

	public function
	GetUUID():
	string {

		return $this->UUID;
	}

	public function
	GetArea():
	string {

		if(!isset($this->Area))
		throw new Common\Error\RequiredDataMissing('Area', 'string');

		return static::ExpandAreaPath($this->Area);
	}

	public function
	GetData():
	Common\Datastore {

		$this->Data['Element'] = $this;

		return $this->Data;
	}

	////////////////////////////////////////////////////////////////
	// LOCAL: JS SCRIPT API ////////////////////////////////////////

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Define the string replacements for imports.')]
	public function
	TokenJSImports():
	Common\Datastore {

		return Common\Datastore::FromArray([
			'ID'         => $this->GetID(),
			'SelectorID' => $this->GetSelectorID()
		]);
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Get list of all the imports.')]
	public function
	GetJSImports():
	Common\Datastore {

		$Output = new Common\Datastore;

		$this->JSModules->Each(
			fn(string $URL, string $Token)
			=> $Output->Push(sprintf('import %s from "%s";', $Token, $URL))
		);

		return $Output;
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Render Javascript import lines to string.')]
	public function
	RenderJSImports():
	string {

		$Output = (
			($this->JSModules)
			->Map(function(string $Line) {
				($this->TokenJSImports())
				->Each(function(string $V, string $T) use(&$Line) {
					$Line = str_replace(
						Common\Text::TemplateMakeToken($T), $V,
						$Line
					);

					return;
				});

				return $Line;
			})
			->RemapKeys(
				fn(string $T, string $V)
				=> [ $T => sprintf('import %s from "%s";', $T, $V) ]
			)
			->Push('')
			->Join(PHP_EOL)
		);

		return $Output;
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Print Javascript import code.')]
	public function
	PrintJSImports():
	static {

		echo $this->RenderJSImports();
		echo PHP_EOL;

		return $this;
	}

	////////

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Define the string replacements for ready lines.')]
	public function
	TokenJSReady():
	Common\Datastore {

		return Common\Datastore::FromArray([
			'ID'         => $this->GetID(),
			'SelectorID' => $this->GetSelectorID()
		]);
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Get list of all the ready lines.')]
	public function
	GetJSReady():
	Common\Datastore {

		return $this->JSReady;
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Render Javascript ready lines to string.')]
	public function
	RenderJSReady():
	string {

		$Code = (
			($this->JSReady)
			->Map(function(string $Line) {
				($this->TokenJSReady())
				->Each(function(string $V, string $T) use(&$Line) {
					$Line = str_replace(
						Common\Text::TemplateMakeToken($T), $V,
						$Line
					);

					return;
				});

				return "\t{$Line}";
			})
			->Push('')
			->Join(PHP_EOL)
		);

		$Output = sprintf(
			"jQuery(function(){\n%s\n\treturn;\n});",
			$Code
		);

		return $Output;
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Print Javascript ready code.')]
	public function
	PrintJSReady():
	static {

		echo $this->RenderJSReady();
		echo PHP_EOL;

		return $this;
	}

	////////

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Render complete Javascript module to string.')]
	public function
	RenderJSModule():
	string {

		$Output = sprintf(
			"<script type=\"module\">\n%s\n%s\n</script>",
			$this->RenderJSImports(),
			$this->RenderJSReady()
		);

		return $Output;
	}

	#[Common\Meta\Date('2023-10-23')]
	#[Common\Meta\Info('Print complete Javascript module.')]
	public function
	PrintJSModule():
	static {

		echo $this->RenderJSModule();
		echo PHP_EOL;

		return $this;
	}

	////////////////////////////////////////////////////////////////
	// LOCAL: Data Management //////////////////////////////////////

	public function
	Get(string $Key):
	mixed {

		return $this->Data->Get($Key);
	}

	public function
	Set(string $Key, mixed $Val):
	static {

		$this->Data[$Key] = $Val;

		return $this;
	}

	////////////////////////////////////////////////////////////////
	// LOCAL: Rendering API ////////////////////////////////////////

	public function
	Render():
	string {

		return (
			$this
			->OnRender()
			->OnRenderPost($this->Surface->GetArea(
				$this->GetArea(),
				$this->GetData()
			))
		);
	}

	public function
	Print():
	static {

		echo $this->Render();
		return $this;
	}

	////////////////////////////////////////////////////////////////
	// Utility Methods /////////////////////////////////////////////

	static public function
	ExpandAreaPath(string $Area):
	string {

		return $Area;
	}

};
