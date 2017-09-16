<?php
/**
*
* @package phpBB Extension - MafiaScum ISOs and Activity Overview
* @copyright (c) 2017 mafiascum.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACTIVITY_OVERVIEW' => 'Activity Overview',
    'USERNAME'          => 'Username',
    'FIRST_POST'        => 'First Post',
    'LAST_POST'         => 'Last Post',
    'ELAPSED_TIME'      => 'Elapsed Time',
    'POST_COUNT'        => 'Posts',
));
