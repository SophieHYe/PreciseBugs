<?php
namespace TYPO3\Form\Tests\Functional;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Form".            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */
use Symfony\Component\DomCrawler\Field\InputFormField;

/**
 * Testcase for Simple Form
 *
 * @group large
 */
class SimpleFormTest extends AbstractFunctionalTestCase {
	/**
	 * @test
	 */
	public function goingForthAndBackStoresFormValuesOfFirstPage() {
		$this->browser->request('http://localhost/test/form/simpleform/ThreePageFormWithValidation');

		$form = $this->browser->getForm();
		$form['--three-page-form-with-validation']['text1-1']->setValue('My Text on the first page');

		$this->gotoNextFormPage($form);

		$this->gotoPreviousFormPage($this->browser->getForm());

		$form = $this->browser->getForm();
		$this->assertSame('My Text on the first page', $form['--three-page-form-with-validation']['text1-1']->getValue());
	}

	/**
	 * @test
	 */
	public function goingForthAndBackStoresFormValuesOfSecondPage() {
		$this->browser->request('http://localhost/test/form/simpleform/ThreePageFormWithValidation');

		$this->gotoNextFormPage($this->browser->getForm());

		$form = $this->browser->getForm();
		$form['--three-page-form-with-validation']['text2-1']->setValue('My Text on the second page');
		$this->gotoPreviousFormPage($form);
		$this->gotoNextFormPage($this->browser->getForm());

		$form = $this->browser->getForm();
		$this->assertSame('My Text on the second page', $form['--three-page-form-with-validation']['text2-1']->getValue());
	}

	/**
	 * @test
	 */
	public function goingForthAndBackStoresFormValuesOfSecondPageAndTriggersValidationOnlyWhenGoingForward() {
		$this->browser->request('http://localhost/test/form/simpleform/ThreePageFormWithValidation');

		$this->gotoNextFormPage($this->browser->getForm());

		$form = $this->browser->getForm();
		$form['--three-page-form-with-validation']['text2-1']->setValue('My Text on the second page');
		$this->gotoPreviousFormPage($form);
		$this->gotoNextFormPage($this->browser->getForm());
		$r = $this->gotoNextFormPage($this->browser->getForm());

		$this->assertSame(' error', $this->browser->getCrawler()->filterXPath('//*[contains(@class,"error")]//input[@id="three-page-form-with-validation-text2-1"]')->attr('class'));
		$form = $this->browser->getForm();
		$form['--three-page-form-with-validation']['text2-1']->setValue('42');
		$this->gotoNextFormPage($form);

		$form = $this->browser->getForm();
		$this->assertSame('', $form['--three-page-form-with-validation']['text3-1']->getValue());
	}

}
