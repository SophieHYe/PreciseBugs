<?php
/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

call_user_func(function() {
    $value = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('value');
    $scope = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('scope');

    if (!is_string($value) || empty($value)) {
        \TYPO3\CMS\Core\Utility\HttpUtility::setResponseCodeAndExit(
            \TYPO3\CMS\Core\Utility\HttpUtility::HTTP_STATUS_400
        );
    }

    $content = \TYPO3\CMS\Core\Utility\GeneralUtility::hmac($value, 'flashvars');

    if ($scope === 'flashvars') {
        header('Content-type: application/x-www-form-urlencoded');
        $content = 'hash=' . $content;
    } else {
        header('Content-type: text/plain');
    }

    header('Pragma: no-cache');
    header('Cache-control: no-cache');

    echo $content;
});
