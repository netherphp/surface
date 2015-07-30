<?php

namespace Nether\Surface\Test;
use \Nether;
use \PHPUnit_Framework_TestCase;

////////
////////

class SurfaceGeneralTest
extends PHPUnit_Framework_TestCase {

	/** @before */
	public function
	PrepareSurfaceEnv() {

		Nether\Option::Set([
			'nether-web-root'      => dirname(dirname(__FILE__)),
			'nether-web-path'      => '/',
			'surface-theme'        => 'testing',
			'surface-auto-capture' => false,
			'surface-auto-render'  => false
		]);

		return;
	}

	/** @test */
	public function
	TestGeneralSurfaceProperties() {

		$surface = new Nether\Surface;
		$this->AssertTrue($surface->GetTheme() === 'testing');
		return;
	}

}
