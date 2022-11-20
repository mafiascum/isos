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
	'ACTIVITY_OVERVIEW'    => 'Activity Overview',
    'USERNAME'             => 'Username',
    'FIRST_POST'           => 'First Post',
    'LAST_POST'            => 'Last Post',
    'ELAPSED_TIME'         => 'Elapsed Time',
    'POST_COUNT'           => 'Posts',
    'ISO_SELECTED'         => 'Isolate Users',
    'GO'                   => 'Go',
    'LINK_TO_PARENT_TOPIC' => 'Back to Topic',
	'PM_SELECTED'          => 'PM Selected',
	'ISO'                  => 'ISO',
	'ISOLATION_NR_LABEL'   => 'isolation',
	'POST_NUMBER_LABEL'    => 'Post',
	'DISABLED_SIG_BBCODES' => 'Disabled signature bbcodes',
	'DISABLED_SIG_BBCODES_EXPLAIN' => 'The bbcode tags that are disallowed from being used in user signatures.',
	'UNSPECIFIED' => 'Unspecified',
	'VLA_ENDS' => 'V/LA Ends',
	'SUBSCRIPTIONS' => 'Subscriptions',
	'BOOKMARKS' => 'Bookmarks',
));
