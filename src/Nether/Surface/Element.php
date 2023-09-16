<?php

namespace Nether\Surface;

use Nether\Common;

use Stringable;

#[Common\Meta\Date('2023-09-12')]
#[Common\Meta\Info('Base class for building UI widgets in templates.')]
abstract class Element
extends Common\Prototype
implements Stringable {

	#[Common\Meta\Info('Define the theme template file. Subclasses should define this.')]
	public string
	$Area;

	public ?string
	$ParentType = NULL;

	public ?string
	$ParentUUID = NULL;

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
		parent::__Construct();

		$this->Surface = $Surface;

		return;
	}

	static public function
	FromSurface(Engine $Surface):
	static {

		return new static($Surface);
	}

	public function
	__ToString():
	string {

		return $this->Render();
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

		$Output = sprintf(
			'el-%s',
			preg_replace('#[^a-z0-9]#', '', $this->UUID)
		);

		return $Output;
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

	abstract static public function
	ExpandAreaPath(string $Area):
	string;

};
