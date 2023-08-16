<?php
require_once __DIR__ . '/../database/DatabaseInteractions.php';

class RequirementsBypassPage {
    private $pageContext;

    //the mapping of (form field name => function to call with it)
    const requestVariableActionMapping = [
        'bypassAddUsername' => 'addUsernameRequirementsBypass',
        'bypassRemoveUsername' => 'removeUsernameRequirementsBypass'
    ];

    function __construct(SpecialPage $pageContext) {
        $this->pageContext = $pageContext;
    }

    function handleFormSubmission() {
        $request = $request = $this->pageContext->getRequest();

        $dbw = getTransactableDatabase('scratch-confirmaccount-bypasses');

        foreach (self::requestVariableActionMapping as $fieldKey => $action) {
            if ($request->getText($fieldKey)) {
                $action($request->getText($fieldKey), $dbw);
            }
        }

        commitTransaction($dbw, 'scratch-confirmaccount-bypasses');

        $this->render();
    }

    function showAddBypassForm() {
        $output = $this->pageContext->getOutput();
        $request = $this->pageContext->getRequest();
        
        $output->addHTML(
            new OOUI\FormLayout([
                'action' => SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-requirements-bypasses-url')->text())->getFullURL(),
                'method' => 'post',
                'items' => [
                    new OOUI\ActionFieldLayout(
                        new OOUI\TextInputWidget( [
                            'name' => 'bypassAddUsername',
                            'required' => true,
                            'value' => $request->getText('username')
                        ] ),
                        new OOUI\ButtonInputWidget([
                            'type' => 'submit',
                            'flags' => ['primary', 'progressive'],
                            'label' => wfMessage('scratch-confirmaccount-requirements-bypasses-add')->parse()
                        ])
                    )
                ],
            ])
        );
    }

    function showBypassesList() {
        $output = $this->pageContext->getOutput();

        $dbr = getReadOnlyDatabase();

        $bypassUsernames = getUsernameBypasses($dbr);

        $table = Html::openElement('table', [ 'class' => 'wikitable' ]);

        $table .= Html::openElement('tr');
        $table .= Html::element('th', [], wfMessage('scratch-confirmaccount-scratchusername'));
        $table .= Html::element('th', [], wfMessage('scratch-confirmaccount-actions'));
        $table .= Html::closeElement('tr');

        foreach ($bypassUsernames as $username) {
            $table .= Html::openElement('tr');

            $table .= Html::element('td', [], $username);

            $table .= Html::openElement('td');
            $table .= Html::openElement('form', ['action' => SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-requirements-bypasses-url')->text())->getFullURL(), 'method' => 'post']);
            $table .= Html::element('input', ['type' => 'hidden', 'name' => 'bypassRemoveUsername', 'value' => $username]);
            $table .= Html::element('input', ['type' => 'submit', 'value' => wfMessage('scratch-confirmaccount-requirements-bypasses-remove')->text()]);
            $table .= Html::closeElement('form');
            $table .= Html::closeElement('td');

            $table .= Html::closeElement('tr');
        }

        $table .= Html::closeElement('table');

        $output->addHTML($table);
    }

    function render() {
        $output = $this->pageContext->getOutput();

        $output->enableOOUI();

        $this->showAddBypassForm();
        $this->showBypassesList();
    }
}