<?php

namespace Cobweb\CleanPermalinks\Tests\Unit\UserFunc;

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

use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use Cobweb\CleanPermalinks\UserFunc\RealUrl;

/**
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 * @author Julien Henchoz <typo3@cobweb.ch>
 */
class RealUrlTest extends UnitTestCase
{
    protected $backupGlobalsBlacklist = array('TYPO3_DB', 'TYPO3_CONF_VARS');

    /**
     * @var RealUrl
     */
    protected $subject = null;

    public function setUp()
    {
        $this->subject = new RealUrl();
    }

    public function tearDown()
    {
        unset($this->subject);
    }

    /**
     * @return array
     */
    public function getCurrentScriptNameProvider() {
        return [
            'Script name with trailing slash' => [
                'hello/',
                'hello'
            ],
            'Script name with leading slash' => [
                '/hello',
                'hello'
            ],
            'Script name without slash' => [
                'hello',
                'hello'
            ],
            'Invalid script name' => [
                null,
                false
            ]
        ];
    }

    /**
     * @test
     * @dataProvider getCurrentScriptNameProvider
     */
    public function getCurrentScriptName($scriptName, $expected) {
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $result = $this->subject->getCurrentScriptName();
        $this->assertSame($expected, $result);
    }


    /**
     * @test
     */
    public function getCurrentHost() {
        $domain = 'www.test.com';
        $_SERVER['HTTP_HOST'] = $domain;
        $result = $this->subject->getCurrentHost();
        $this->assertSame($domain, $result);
    }

    /**
     * @test
     */
    public function getCurrentHostWithUnsetHttpHost() {
        unset($_SERVER['HTTP_HOST']);
        $result = $this->subject->getCurrentHost();
        $this->assertFalse($result);
    }

    /**
     * @return array
     */
    public function getCurrentSchemeProvider() {
        return [
            'HTTPS enabled' => [
                'on',
                'https://'
            ],
            'HTTPS disabled' => [
                'off',
                'http://'
            ],
            'Odd value in $_SERVER[HTTPS]' => [
                'something',
                'http://'
            ],
        ];
    }

    /**
     * @param $httpsValue
     * @param $expected
     * @test
     * @dataProvider getCurrentSchemeProvider
     */
    public function getCurrentScheme($httpsValue, $expected) {
        $_SERVER['HTTPS'] = $httpsValue;
        $result = $this->subject->getCurrentScheme();
        $this->assertSame($expected, $result);
    }

    /**
     * If $_SERVER['https'] is not set, we should use http
     * @test
     */
    public function getCurrentSchemeWithUnsetServerHttps() {
        $expected = 'http://';
        $result = $this->subject->getCurrentScheme();
        $this->assertSame($expected, $result);
        unset($_SERVER['HTTPS']);

    }

    /**
     * @test
     */
    public function setupEnvironment() {
        $GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, [], [], '', false);
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.test.com';
        $pageId = 123;
        $expectedAbsRefPrefix = 'https://www.test.com/';

        $this->subject->setupEnvironment($pageId);

        $this->assertSame($pageId, $GLOBALS['TSFE']->id);
        $this->assertNotEmpty($GLOBALS['TSFE']->config['config']);
        $this->assertArrayHasKey('tx_realurl_enable', $GLOBALS['TSFE']->config['config']);
        $this->assertTrue($GLOBALS['TSFE']->config['config']['tx_realurl_enable']);
        $this->assertSame($GLOBALS['TSFE']->absRefPrefix, $expectedAbsRefPrefix);
    }

    public function redirectUrlProvider() {
        return [
            'Page path with trailing slash, HTTPS ON' => [
                'hello/',
                true,
                'https://www.test.com/hello/'
            ],
            'Page path with trailing slash, HTTPS off' => [
                'hello/',
                false,
                'http://www.test.com/hello/'
            ],
            'Page path with leading slash, HTTPS ON' => [
                '/hello',
                true,
                'https://www.test.com/hello/'
            ],
            'Page path with leading slash, HTTPS off' => [
                '/hello',
                false,
                'http://www.test.com/hello/'
            ],
            'Page path with surrounding slashes, HTTPS ON' => [
                '/hello/',
                true,
                'https://www.test.com/hello/'
            ],
            'Page path with surrounding slashes, HTTPS off' => [
                '/hello/',
                false,
                'http://www.test.com/hello/'
            ],
            'Page path with empty page path, HTTPS ON' => [
                '',
                true,
                'https://www.test.com/'
            ],
            'Page path with empty page path, HTTPS off' => [
                '',
                false,
                'http://www.test.com/'
            ],
            'Page path with invalid pagepath, HTTPS ON' => [
                null,
                true,
                'https://www.test.com/'
            ],
            'Page path with invalid page path, HTTPS off' => [
                null,
                false,
                'http://www.test.com/'
            ],
        ];
    }

    /**
     * @test
     * @dataProvider redirectUrlProvider
     */
    public function redirectUrl($pagePath, $https, $expected) {
        if ($https) {
            $_SERVER['HTTPS'] = 'on';
        }
        else {
            unset($_SERVER['HTTPS']);
        }
        $_SERVER['HTTP_HOST'] = 'www.test.com';

        $result = $this->subject->getRedirectUrl($pagePath);
        $this->assertSame($expected, $result);
    }



    /**
     * @test
     */
    public function prepareEncoderArguments() {
        $GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, [], [], '', false);

        /** @var RealUrl $subject */
        $subject = $this->getMock(RealUrl::class, ['getDatabaseConnection']);

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.test.com';
        $_SERVER['SCRIPT_NAME'] = 'test.php';

        $pageId = 123;
        $pageRecord = [
            'uid' => $pageId,
            'pid' => 1,
            'title' => 'Some page'
        ];

        $expectedUrl = $subject->getCurrentScheme() . $subject->getCurrentHost() . '/' . $subject->getCurrentScriptName() . '?id=' . (int)$pageId;

        $expected = [
            'typeNum' => null,
            'LD' => [
                'url' => $expectedUrl,
                'totalURL' => $expectedUrl
            ],
            'args' => [
                'page' => $pageRecord,
                'script' => $subject->getCurrentScriptName()
            ]
        ];

        $mockDatabaseConnection = $this->getMock(DatabaseConnection::class, ['exec_SELECTgetSingleRow'], [], '', false);
        $mockDatabaseConnection->method('exec_SELECTgetSingleRow')->will(self::returnValue($pageRecord));

        $subject->method('getDatabaseConnection')->will(self::returnValue($mockDatabaseConnection));

        $result = $subject->prepareEncoderArguments($pageId);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function prepareEncoderArgumentsWithUnexistingPage() {
        $GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, [], [], '', false);

        /** @var RealUrl $subject */
        $subject = $this->getMock(RealUrl::class, ['getDatabaseConnection']);

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.test.com';
        $_SERVER['SCRIPT_NAME'] = 'test.php';

        $pageId = 123;

        $mockDatabaseConnection = $this->getMock(DatabaseConnection::class, ['exec_SELECTgetSingleRow'], [], '', false);
        $mockDatabaseConnection->method('exec_SELECTgetSingleRow')->will(self::returnValue(false));

        $subject->method('getDatabaseConnection')->will(self::returnValue($mockDatabaseConnection));

        $result = $subject->prepareEncoderArguments($pageId);

        $this->assertFalse($result);
    }

    public function getPermalinkPidProvider() {
        return [
            'Relative URL with surrounding slashes' => [
                '/page/123/',
                123
            ],
            'Relative URL with trailing slash' => [
                'page/123/',
                123
            ],
            'Relative URL with leading slash' => [
                '/page/123',
                123
            ],
            'Absolute URL with trailing slash' => [
                'http://www.test.com/page/123/',
                false
            ],
            'Absolute URL without trailing slash' => [
                'http://www.test.com/page/123',
                false
            ],
            'Absolute URL with unmatching format' => [
                'http://www.test.com/somepageurl',
                false
            ],
            'Relative URL with unmatching format' => [
                '/somepageurl',
                false
            ],
            'Absolute URL with no page path' => [
                'http://www.test.com/',
                false
            ],
            'Absolute URL with no page path, no trailing slash' => [
                'http://www.test.com',
                false
            ],
            'Relative URL with no page path' => [
                '/',
                false
            ],
            'Relative URL with no page path, no trailing slash' => [
                '',
                false
            ],
            'Absolute URL with page segment, but no page Id' => [
                'http://www.test.com/page/',
                false
            ],
            'Relative URL with page segment, but no page Id' => [
                '/page/',
                false
            ],
            'Absolute URL with page segment, but no page Id, no trailing slash' => [
                'http://www.test.com/page',
                false
            ],
            'Relative URL with page segment, but no page Id, no trailing slash' => [
                '/page',
                false
            ],
            'Absolute URL with some page path before the /page/ segment, with trailing slash' => [
                'http://www.test.com/somepage/page/123/',
                false
            ],
            'Absolute URL with some page path before the /page/ segment, no trailing slash' => [
                'http://www.test.com/somepage/page/123',
                false
            ],
            'Relative URL with some page path before the /page/ segment, with trailing slash' => [
                '/somepage/page/123/',
                false
            ],
            'Relative URL with some page path before the /page/ segment, no trailing slash' => [
                '/somepage/page/123',
                false
            ]
        ];
    }

    /**
     * @param $url
     * @param $expected
     * @test
     * @dataProvider getPermalinkPidProvider
     */
    public function getPermalinkPid($url, $expected) {
        $result = $this->subject->getPermalinkPid($url);
        $this->assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function pagePermaLink() {
        $GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, [], [], '', false);


        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.test.com';
        $_SERVER['SCRIPT_NAME'] = 'test.php';

        /** @var RealUrl $subject */
        $subject = $this->getMock(RealUrl::class, ['getDatabaseConnection', 'getPermalinkPid', 'prepareEncoderArguments']);
        $mockDatabaseConnection = $this->getMock(DatabaseConnection::class, ['exec_SELECTgetSingleRow'], [], '', false);
        $mockDatabaseConnection->method('exec_SELECTgetSingleRow')->will(self::returnValue(false));

        $subject->method('getDatabaseConnection')->will(self::returnValue($mockDatabaseConnection));
        $subject->method('getPermalinkPid')->will(self::returnValue(123));
        $subject->method('prepareEncoderArguments')->will(self::returnValue([
            'typeNum' => null,
            'LD' => [
                'url' => 'https://www.test.com/index.php?id=123',
                'totalURL' => 'https://www.test.com/index.php?id=123'
            ],
            'args' => [
                'page' => ['uid' => 123],
                'script' => $subject->getCurrentScriptName()
            ]
        ]));


        $config = [
            'URL' => 'http://www.test.com/page/123/'
        ];

        $mockDatabaseConnection->expects(self::once())->method('exec_SELECTgetSingleRow');
        $subject->expects(self::once())->method('getPermalinkPid');
        $subject->expects(self::once())->method('prepareEncoderArguments');

        $subject->pagePermaLink($config);
    }

    /**
     * @test
     */
    public function pagePermaLinkWithNonMatchingUrl() {
        $GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, [], [], '', false);


        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.test.com';
        $_SERVER['SCRIPT_NAME'] = 'test.php';

        /** @var RealUrl $subject */
        $subject = $this->getMock(RealUrl::class, ['getDatabaseConnection', 'getPermalinkPid', 'prepareEncoderArguments']);
        $mockDatabaseConnection = $this->getMock(DatabaseConnection::class, ['exec_SELECTgetSingleRow'], [], '', false);
        $mockDatabaseConnection->method('exec_SELECTgetSingleRow')->will(self::returnValue(false));

        $subject->method('getDatabaseConnection')->will(self::returnValue($mockDatabaseConnection));
        $subject->method('getPermalinkPid')->will(self::returnValue(false));


        $config = [
            'URL' => 'http://www.test.com/somepage/'
        ];

        $mockDatabaseConnection->expects(self::never())->method('exec_SELECTgetSingleRow');
        $subject->expects(self::never())->method('prepareEncoderArguments');
        $subject->expects(self::once())->method('getPermalinkPid');

        $subject->pagePermaLink($config);
    }

    /**
     * @test
     */
    public function pagePermaLinkWithInvalidConfig() {
        $GLOBALS['TSFE'] = $this->getMock(TypoScriptFrontendController::class, [], [], '', false);

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.test.com';
        $_SERVER['SCRIPT_NAME'] = 'test.php';

        /** @var RealUrl $subject */
        $subject = $this->getMock(RealUrl::class);
        $mockDatabaseConnection = $this->getMock(DatabaseConnection::class, ['exec_SELECTgetSingleRow'], [], '', false);
        $mockDatabaseConnection->method('exec_SELECTgetSingleRow')->will(self::returnValue(false));

        $subject->method('getDatabaseConnection')->will(self::returnValue($mockDatabaseConnection));
        $subject->method('getPermalinkPid')->will(self::returnValue(false));

        $config = [];

        $mockDatabaseConnection->expects(self::never())->method('exec_SELECTgetSingleRow');
        $subject->expects(self::never())->method('prepareEncoderArguments');
        $subject->expects(self::never())->method('getPermalinkPid');

        $subject->pagePermaLink($config);
    }
}
