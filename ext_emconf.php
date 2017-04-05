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

$EM_CONF[$_EXTKEY] = [
    'title' => 'Clean permalinks',
    'description' => 'Enables the access to website pages through an URL in the format /page/<pageId>/, using RealURL',
    'category' => 'fe',
    'author' => 'Julien Henchoz',
    'author_company' => 'Cobweb Development SARL',
    'author_email' => 'support@cobweb.ch',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'internal' => '',
    'version' => '1.0.0',
    'constraints' =>
        array(
            'depends' =>
                array(
                    'realurl' => '2.0.0-0.0.0',
                    'typo3' => '7.6.0-7.99.99',
                ),
            'conflicts' =>
                array(),
            'suggests' =>
                array(),
        ),
];
