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
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Send headers.
 * @internal
 */
class TypoScriptFrontendHeaders implements MiddlewareInterface
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

    /**
     * Adds headers as defined in typoscript frontend controller
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isOutputting = $this->controller->isOutputting();
        $request = $request->withAttribute('tsfeIsOutputting', true);

        // Getting status whether we can send cache control headers for proxy caching:
        $doCache = $this->controller->isStaticCacheble();
        // This variable will be TRUE unless cache headers are configured to be sent ONLY if a branch does not allow logins and logins turns out to be allowed anyway...
        $loginsDeniedCfg = empty($this->controller->config['config']['sendCacheHeaders_onlyWhenLoginDeniedInBranch']) || empty($this->controller->loginAllowedInBranch);
        // Finally, when backend users are logged in, do not send cache headers at all (Admin Panel might be displayed for instance).
        $this->controller->isClientCachable = $doCache && !$this->controller->beUserLogin && !$this->controller->doWorkspacePreview() && $loginsDeniedCfg;

        $response = $handler->handle($request);
        if ($isOutputting) {
            // Set header for charset-encoding unless disabled
            if (empty($this->controller->config['config']['disableCharsetHeader'])) {
                $response = $response->withHeader('Content-Type', $this->controller->contentType . '; charset=' . trim($this->controller->metaCharset));
            }
            // Set header for content language unless disabled
            if (empty($this->controller->config['config']['disableLanguageHeader']) && !empty($this->controller->sys_language_isocode)) {
                $response = $response->withHeader('Content-Language', trim($this->controller->sys_language_isocode));
            }
            // Set cache related headers to client (used to enable proxy / client caching!)
            $response = $this->setCacheHeaders($response);
            $response = $this->setAdditionalHeaders($response);

            // Send appropriate status code in case of temporary content
            if ($this->controller->tempContent) {
                $response = $response->withStatus('503', 'Service unavailable');
                $response = $response->withHeader('Retry-after', '3600');
                $response = $response->withHeader('Pragma', 'no-cache');
                $response = $response->withHeader('Cache-control', 'no-cache');
                $response = $response->withHeader('Expire', '0');
            }
        }

        return $response;
    }

    /**
     * Set cache headers good for client/reverse proxy caching
     * This function should not be called if the page content is
     * temporary (like for "Page is being generated..." message,
     * but in that case it is ok because the config-variables
     * are not yet available and so will not allow to send
     * cache headers)
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function setCacheHeaders(ResponseInterface $response): ResponseInterface
    {
        if ($this->controller->isClientCachable) {
            // Build headers:
            $response = $response->withHeader('Expires', gmdate('D, d M Y H:i:s T', $this->controller->cacheExpires));
            $response = $response->withHeader('ETag', '"' . md5($this->controller->content) . '"');
            $response = $response->withHeader('Cache-Control', 'max-age=' . ($this->controller->cacheExpires - $GLOBALS['EXEC_TIME']));
            $response = $response->withHeader('Pragma', 'public');
        } else {
            // Build headers
            // "no-store" is used to ensure that the client HAS to ask the server every time, and is not allowed to store anything at all
            $response = $response->withHeader('Cache-Control', 'private, no-store');
            // Now, if a backend user is logged in, tell him in the Admin Panel log what the caching status would have been:
            if ($this->controller->beUserLogin) {
                if ($this->controller->isStaticCacheble()) {
                    $this->timeTracker->setTSlogMessage('Cache-headers with max-age "' . ($this->controller->cacheExpires - $GLOBALS['EXEC_TIME']) . '" would have been sent');
                } else {
                    $reasonMsg = '';
                    $reasonMsg .= !$this->controller->no_cache ? '' : 'Caching disabled (no_cache). ';
                    $reasonMsg .= !$this->controller->isINTincScript() ? '' : '*_INT object(s) on page. ';
                    $reasonMsg .= !is_array($this->controller->fe_user->user) ? '' : 'Frontend user logged in. ';
                    $this->timeTracker->setTSlogMessage('Cache-headers would disable proxy caching! Reason(s): "' . $reasonMsg . '"', 1);
                }
            }
        }

        return $response;
    }

    /**
     * Set additional headers from config.additionalHeaders
     *
     * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::processOutput()
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function setAdditionalHeaders(ResponseInterface $response): ResponseInterface
    {
        if (!isset($this->controller->config['config']['additionalHeaders.'])) {
            return $response;
        }
        ksort($this->controller->config['config']['additionalHeaders.']);
        foreach ($this->controller->config['config']['additionalHeaders.'] as $options) {
            if (!is_array($options)) {
                continue;
            }
            $header = $options['header'] ?? '';
            $header = isset($options['header.'])
                ? $this->controller->cObj->stdWrap(trim($header), $options['header.'])
                : trim($header);
            if ($header === '') {
                continue;
            }
            $replace = $options['replace'] ?? '';
            $replace = isset($options['replace.'])
                ? $this->controller->cObj->stdWrap($replace, $options['replace.'])
                : $replace;
            $httpResponseCode = $options['httpResponseCode'] ?? '';
            $httpResponseCode = isset($options['httpResponseCode.'])
                ? $this->controller->cObj->stdWrap($httpResponseCode, $options['httpResponseCode.'])
                : $httpResponseCode;
            $httpResponseCode = (int)$httpResponseCode;
            list($headerName, $headerValue) = GeneralUtility::trimExplode(':', $header);

            if ($replace || !$response->hasHeader($headerName)) {
                $response->withHeader($headerName, $headerValue);
            }
            if ($httpResponseCode) {
                $response = $response->withStatus($httpResponseCode);
            }
        }

        return $response;
    }
}
