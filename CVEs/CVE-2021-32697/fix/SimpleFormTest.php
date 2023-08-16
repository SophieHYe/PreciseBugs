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
		$this->gotoNextFormPage($this->browser->getForm());

		$this->assertSame(' error', $this->browser->getCrawler()->filterXPath('//*[contains(@class,"error")]//input[@id="three-page-form-with-validation-text2-1"]')->attr('class'));
		$form = $this->browser->getForm();
		$form['--three-page-form-with-validation']['text2-1']->setValue('42');
		$this->gotoNextFormPage($form);

		$form = $this->browser->getForm();
		$this->assertSame('', $form['--three-page-form-with-validation']['text3-1']->getValue());
	}


	/**
	 * This is an edge-case which occurs if somebody makes the formState persistent, which can happen when subclassing the FormRuntime.
	 *
	 * The goal is to build a GET request *only* containing the form state, and nothing else. Furthermore, we need to make sure
	 * that we do NOT send any of the parameters with the form; as we only want the form state to be applied.
	 *
	 * So, if the form state contains some values, we want to be sure these values are re-displayed.
	 *
	 * @test
	 */
	public function goingForthAndBackStoresFormValuesOfSecondPageEvenWhenSecondPageIsManuallyCalledAsGetRequest() {
		// 1. TEST SETUP: FORM STATE PREPARATION
		// - go to the 2nd page of the form, and fill in text2-1.
		$this->browser->request('http://localhost/test/form/simpleform/ThreePageFormWithValidation');

		$this->gotoNextFormPage($this->browser->getForm());

		$form = $this->browser->getForm();
		$form['--three-page-form-with-validation']['text2-1']->setValue('My Text on the second page');

		// - then, go back and forth, in order to get an *up-to-date* form state having the right values inside.
		$this->gotoPreviousFormPage($form);
		$this->gotoNextFormPage($this->browser->getForm());

		// 2. TEST SETUP: BUILD GET REQUEST ONLY CONTAINING FORM STATE
		$form = $this->browser->getForm();
		\TYPO3\Flow\Reflection\ObjectAccess::setProperty($form, 'method', 'GET', TRUE);

		// we want to stay on the current page, that's why we send __currentPage = 1. (== 2nd page of the form)
		$doc = new \DOMDocument();
		$doc->loadXML('<input type="hidden" name="--three-page-form-with-validation[__currentPage]" value="1" />');
		$node = $doc->getElementsByTagName('input')->item(0);
		$form->set(new InputFormField($node));

		// We do *not* send any form content with us, as we want to test these are properly reconstituted from the form state.
		$form->offsetUnset('--three-page-form-with-validation[text2-1]');

		// 3. TEST RUN
		// submit the GET request ONLY containing formState.
		$this->browser->submit($form);

		// now, make sure the text2-1 (which has been persisted in the form state) gets reconstituted and shown properly.
		$form = $this->browser->getForm();
		$this->assertSame('My Text on the second page', $form['--three-page-form-with-validation']['text2-1']->getValue());
	}

}
