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
	/*//
	dump some settings into the option manager to temper the surface
	instances we create for testing. forcing a few defaults that i
	want to test against just incase the defaults change a little
	in the future.
	//*/

		Nether\Option::Set([
			'nether-web-root'      => dirname(dirname(__FILE__)),
			'nether-web-path'      => '/',
			'surface-theme'        => 'testing',
			'surface-theme-stack'  => ['common'],
			'surface-auto-capture' => false,
			'surface-auto-render'  => false
		]);

		return;
	}

	/** @test */
	public function
	TestThemeProperty() {
		$surface = new Nether\Surface;

		$this->AssertTrue(
			($surface->GetTheme() === 'testing'),
			'sucked up the correct theme from config'
		);

		$surface->SetTheme('still-testing');
		$this->AssertTrue(
			($surface->GetTheme() === 'still-testing'),
			'explicit set theme for surface instance'
		);

		return;
	}

	/** @test */
	public function
	TestThemeStackingFromDefaults() {
		$surface = new Nether\Surface;

		$stack = $surface->GetThemeStack();
		$this->AssertTrue(
			(is_array($stack) && count($stack) === 2),
			'check we got a stack array of two in this case'
		);
		$this->AssertTrue($stack[0] === 'testing');
		$this->AssertTrue($stack[1] === 'common');

		return;
	}

	/** @test */
	public function
	TestThemeStackingExplictArray() {
		$surface = new Nether\Surface;

		$stack = $surface->GetThemeStack(['zomg','bbq']);
		$this->AssertTrue(
			(is_array($stack) && count($stack) === 4),
			'check that we got a stack array of four in this case'
		);
		$this->AssertTrue($stack[0] === 'zomg');
		$this->AssertTrue($stack[1] === 'bbq');
		$this->AssertTrue($stack[2] === 'testing');
		$this->AssertTrue($stack[3] === 'common');

		return;
	}

	/** @test */
	public function
	TestThemeStackingExplicitString() {
		$surface = new Nether\Surface;

		$stack = $surface->GetThemeStack('zomg:bbq');
		$this->AssertTrue(
			(is_array($stack) && count($stack) === 4),
			'check that we got a stack array of four in this case'
		);
		$this->AssertTrue($stack[0] === 'zomg');
		$this->AssertTrue($stack[1] === 'bbq');
		$this->AssertTrue($stack[2] === 'testing');
		$this->AssertTrue($stack[3] === 'common');

		return;
	}

	/** @test */
	public function
	TestThemeFileFinding() {
		$surface = new Nether\Surface;

		$filename = $surface->GetThemeFile('design.nope');
		$this->AssertTrue(
			($filename === false),
			'that we failed to find a non-existant file'
		);

		$filename = $surface->GetThemeFile('design.phtml');
		$this->AssertTrue(
			($filename === "{$surface->GetThemeRoot()}/testing/design.phtml"),
			'that we found an existing file'
		);

		return;
	}

	/** @test */
	public function
	TestStackedThemeFileFinding() {
		$surface = new Nether\Surface;

		$filename = $surface->GetThemeFile('testing-also:design.nope');
		$this->AssertTrue(
			($filename === false),
			'that we failed to find a non-existant file'
		);

		$filename = $surface->GetThemeFile('testing-also:design.phtml');
		$this->AssertTrue(
			($filename === "{$surface->GetThemeRoot()}/testing-also/design.phtml"),
			"we found an existing file via stacked name"
		);

		return;
	}

	/** @test */
	public function
	TestAreaFileFinding() {
		$surface = new Nether\Surface;
		$this->AssertTrue($surface->GetArea('index') === 'this is the index page');
		$this->AssertTrue($surface->GetArea('about') === 'this is the about page');
		return;
	}

	/** @test */
	public function
	TestStackedAreaFileFinding() {
		$surface = new Nether\Surface;
		$this->AssertTrue($surface->GetArea('testing-also:index') === 'this is the index page also');
		$this->AssertTrue($surface->GetArea('testing-also:about') === 'this is the about page also');
		return;
	}

	/** @test */
	public function
	TestFinalRenderingSolution() {
		ob_start();
		// main screen turn on.
			$surface = new Nether\Surface;
			$surface->Start();
			$surface->ShowArea('index');
			$surface->Render();
		$content = ob_get_clean();

		$this->AssertTrue($content === 'output: this is the index page');
		return;
	}

	/** @test */
	public function
	TestFinalRenderingSolutionReturn() {
		$surface = new Nether\Surface;
		$surface->Start();
		$surface->ShowArea('index');
		$content = $surface->Render(true);

		$this->AssertTrue($content === 'output: this is the index page');
		return;
	}

}