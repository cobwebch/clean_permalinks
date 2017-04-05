<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Julien Henchoz <typo3@cobweb.ch>, Cobweb
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Cobweb\CleanPermalinks\UserFunc;

use DmitryDulepov\Realurl\Encoder\UrlEncoder;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;


/**
 * Userfunc to render alternative label
 */
class RealUrl
{
    /**
     * @const Regex used to detected if the current URL should be considered as a permalink.
     * Currently "/page/123",
     */
    const PERMALINK_REGEX = '/^\/?page\/(\d+)\/?$/';

    /**
     * If the incoming URL has the permalink format, intercept it and redirect to the page having this ID
     * @param array &$config
     * @return void
     */
    public function pagePermaLink(array &$config)
    {
        if (isset($config['URL'])) {
            // If the page url matches for right format...
            $permalinkPid = $this->getPermalinkPid($config['URL']);
            if ($permalinkPid) {
                $encoderArguments = $this->prepareEncoderArguments($permalinkPid);
                if (is_array($encoderArguments) && !empty($encoderArguments)) {
                    // Make sure the URL to the target page is in realurl cache
                    /** @var UrlEncoder $encoder */
                    $encoder = GeneralUtility::makeInstance(UrlEncoder::class);
                    $encoder->encodeUrl($encoderArguments);

                    $pathData = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
                        'speaking_url',
                        'tx_realurl_urldata',
                        'page_id = ' . (int)$permalinkPid
                    );

                    if (is_array($pathData) && isset($pathData['speaking_url'])) {
                        HttpUtility::redirect(
                            $this->getRedirectUrl($pathData['speaking_url']),
                            HttpUtility::HTTP_STATUS_301
                        );
                    }
                }
            }
        }
    }

    /**
     * Checks if the given URL is a permalink. If true, returns the target pid.
     * Else returns false
     * @param string $url
     * @return bool|int
     */
    public function getPermalinkPid($url)
    {
        $matches = [];
        $pageId = false;
        $isPermalink = preg_match(self::PERMALINK_REGEX, $url, $matches);
        if ($isPermalink) {
            if (isset($matches[1]) && is_numeric($matches[1])) {
                $pageId = (int)$matches[1];
            }
        }
        return $pageId;
    }

    /**
     * Prepares the argument needed by the realurl "encodeUrl" method
     * @param int $pageId
     * @return array
     */
    public function prepareEncoderArguments($pageId)
    {
        $encoderArguments = false;
        // Check if the page exists
        $page = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'pages', 'uid = ' . (int)$pageId);
        if ($page) {
            $this->setupEnvironment($pageId);
            $scheme = $this->getCurrentScheme();
            $currentHost = $this->getCurrentHost();
            $currentScript = $this->getCurrentScriptName();
            $targetUrl = $scheme . $currentHost . '/' . $currentScript . '?id=' . (int)$pageId;

            $encoderArguments = [
                'typeNum' => null,
                'LD' => [
                    'url' => $targetUrl,
                    'totalURL' => $targetUrl
                ],
                'args' => [
                    'page' => $page,
                    'script' => $currentScript
                ]
            ];

        }
        return $encoderArguments;
    }

    /**
     * Generate the final URL we should redirect the user to
     * @param $pagePath
     * @return string
     */
    public function getRedirectUrl($pagePath)
    {
        $pagePath = trim($pagePath, '/');
        $trailingSlash = !empty($pagePath) ? '/' : '';
        return $this->getCurrentScheme() . $this->getCurrentHost() . '/' . $pagePath . $trailingSlash;
    }

    /**
     * Setup the necessary TSFE for url encoding to be possible
     * @param $pageId
     */
    public function setupEnvironment($pageId)
    {
        $GLOBALS['TSFE']->id = (int)$pageId;
        $GLOBALS['TSFE']->config['config']['tx_realurl_enable'] = true;
        $GLOBALS['TSFE']->absRefPrefix = $this->getCurrentScheme() . $this->getCurrentHost() . '/';
    }

    /**
     * Get the current domain
     * @return mixed
     */
    public function getCurrentHost()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : false;
    }

    /**
     * Get the current script name
     * @return string
     */
    public function getCurrentScriptName()
    {
        return isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])
            ? trim($_SERVER['SCRIPT_NAME'], '/')
            : false;
    }

    /**
     * Gets the currently used protocol
     * @return string
     */
    public function getCurrentScheme()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    }

    /**
     * @return DatabaseConnection
     */
    public function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
