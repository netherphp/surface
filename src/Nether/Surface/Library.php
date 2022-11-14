<?php

namespace Nether\Surface;
use Nether;

use SplFileInfo;
use Nether\Object\Datastore;

class Library
extends Nether\Common\Library {

	public const
	ConfThemes       = 'Nether.Surface.Themes',
	ConfThemeRoot    = 'Nether.Surface.Theme.Root';

	static public function
	Init(...$Argv):
	void {

		static::OnInit(...$Argv);

		return;
	}

	static public function
	InitDefaultConfig(?Datastore $Config=NULL):
	Datastore {

		parent::InitDefaultConfig($Config);

		$Config->BlendRight([
			static::ConfThemeRoot    => './themes',
			static::ConfThemes       => [ 'local', 'default' ]
		]);

		return $Config;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	OnInit(Datastore $Config=NULL, ...$Argv):
	void {

		static::InitDefaultConfig($Config);

		return;
	}

}
