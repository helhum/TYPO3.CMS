<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Frontend\Middleware;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageGenerator;

class TypoScriptFrontendUnCachedRendering implements MiddlewareInterface
{
    /**
     * @var TypoScriptFrontendController
     */
    protected $controller;

    /**
     * Instance of the timetracker
     * @var TimeTracker
     */
    protected $timeTracker;

    public function __construct(TypoScriptFrontendController $controller = null, TimeTracker $timeTracker = null)
    {
        $this->controller = $controller ?: $GLOBALS['TSFE'];
        $this->timeTracker = $timeTracker ?: GeneralUtility::makeInstance(TimeTracker::class);
    }

    public function process(ServerRequestInterface $request, PsrRequestHandlerInterface $handler): ResponseInterface
    {
        // Render non-cached parts, which are taken from cache or added during page generation
        if ($this->controller->isINTincScript()) {
            $this->controller->preparePageContentGeneration();
            $this->timeTracker->push('Non-cached objects', '');
            $this->controller->INTincScript();
            $this->timeTracker->pull();
        }

        return $handler->handle($request);
    }
}
