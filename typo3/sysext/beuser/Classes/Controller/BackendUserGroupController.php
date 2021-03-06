<?php
namespace TYPO3\CMS\Beuser\Controller;

/*
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend module user group administration controller
 */
class BackendUserGroupController extends BackendUserActionController
{
    /**
     * @var \TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository
     */
    protected $backendUserGroupRepository;

    /**
     * @param \TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository $backendUserGroupRepository
     */
    public function injectBackendUserGroupRepository(\TYPO3\CMS\Beuser\Domain\Repository\BackendUserGroupRepository $backendUserGroupRepository)
    {
        $this->backendUserGroupRepository = $backendUserGroupRepository;
    }

    /**
     * Initialize actions
     *
     * @throws \RuntimeException
     */
    public function initializeAction()
    {
        // @TODO: Extbase backend modules relies on frontend TypoScript for view, persistence
        // and settings. Thus, we need a TypoScript root template, that then loads the
        // ext_typoscript_setup.typoscript file of this module. This is nasty, but can not be
        // circumvented until there is a better solution in extbase.
        // For now we throw an exception if no settings are detected.
        if (empty($this->settings)) {
            throw new \RuntimeException('No settings detected. This module can not work then. This usually happens if there is no frontend TypoScript template with root flag set. ' . 'Please create a frontend page with a TypoScript root template.', 1460976089);
        }
    }

    /**
     * Displays all BackendUserGroups
     */
    public function indexAction()
    {
        /** @var \TYPO3\CMS\Backend\Routing\UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Routing\UriBuilder::class);
        $this->view->assign('backendUserGroups', $this->backendUserGroupRepository->findAll());
        $this->view->assign('returnUrl', (string)$uriBuilder->buildUriFromRoute(
            'system_BeuserTxBeuser',
            [
                'tx_beuser_system_beusertxbeuser' => [
                    'action' => 'index',
                    'controller' => 'BackendUserGroup'
                ]
            ]
        ));
    }
}
