<?php
/**
 *
 * @package phpBB Extension - Mafiascum ISOS and Activity Monitor
 * @copyright (c) 2013 phpBB Group
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace mafiascum\isos\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
/**
 * Event listener
 */
class main_listener implements EventSubscriberInterface
{
    
    /* @var \phpbb\controller\helper */
    protected $helper;

    /* @var \phpbb\template\template */
    protected $template;

    /* @var \phpbb\request\request */
    protected $request;

    /* @var \phpbb\db\driver\driver */
    protected $db;
    
    /* @var \phpbb\user */
	protected $user;

	/* phpbb\language\language */
	protected $language;

	protected $post_id_to_post_number_map;

    static public function getSubscribedEvents()
    {
        return array(
            'core.user_setup'  => 'load_language_on_setup',
            'core.viewtopic_assign_template_vars_before' => 'inject_template_vars',
            'core.viewtopic_modify_page_title' => 'viewtopic_modify_page_title',
            'core.viewtopic_modify_post_row' => 'viewtopic_modify_post_row',
            'core.submit_post_end' => 'submit_post_end',
			'core.ucp_profile_modify_signature_sql_ary' => 'ucp_profile_modify_signature_sql_ary',
			'core.acp_board_config_edit_add' => 'acp_board_config_edit_add',
			'core.viewtopic_get_post_data' => 'viewtopic_get_post_data',
			'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
			'core.viewtopic_before_f_read_check' => 'viewtopic_before_f_read_check',
			'core.viewtopic_highlight_modify' => 'viewtopic_highlight_modify',
			'core.page_header' => 'page_header_after',
			'core.viewtopic_post_rowset_data' => 'viewtopic_post_rowset_data'
        );
    }

    /**
     * Constructor
     *
     * @param \phpbb\controller\helper    $helper        Controller helper object
     * @param \phpbb\template\template    $template    Template object
     * @param \phpbb\request\request    $request    Request object
     * @param \phpbb\db\driver\driver_interface    $db    DB object
     * @param \phpbb\user    $user    User object
	 * @param \phpbb\language $language Language Object
     */
    public function __construct(\phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\request\request $request, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\language\language $language)
    {
        $this->helper = $helper;
        $this->template = $template;
        $this->request = $request;
        $this->db = $db;
		$this->user = $user;
		$this->language = $language;
	}
	private function is_mafia_forum($config, $forum_id)
	{
		$sql = 'SELECT forum_parents FROM ' . FORUMS_TABLE . ' WHERE forum_id=' . $forum_id;
		$result = $this->db->sql_query($sql);
		$is_mafia_forum = false;

		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_parents = unserialize($row['forum_parents']);
			foreach($forum_parents as $parent_forum_id => $parent_forum)
			{
				if($parent_forum_id == $config['mafia_forums_id'])
				{
					$is_mafia_forum = true;
					break;
				}
			}
		}

		$this->db->sql_freeresult($result);
		return $is_mafia_forum;
	}
	public function viewtopic_post_rowset_data($event)
	{
		global $config;
		$row = $event['row'];
		$rowset_data = $event['rowset_data'];
		$is_mafia_forum = $this->is_mafia_forum($config, $rowset_data['forum_id']);

		if($is_mafia_forum)
		{
			if($rowset_data['hide_post'] == true && $rowset_data['post_visibility'] != ITEM_DELETED)
			{
				$rowset_data['hide_post'] = false;
			}
			$rowset_data['foe'] = false;
		}

		$event['rowset_data'] = $rowset_data;
	}
	public function page_header_after($event)
	{
		global $forum_id, $topic_id, $phpEx, $phpbb_root_path;
		if(isset($forum_id) || isset($topic_id))
		{
			$event['display_online_list'] = false;
		}
		$this->template->assign_vars(array(
			'U_BOOKMARKS' => append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=ucp_main&amp;mode=bookmarks'),
			'U_SUBSCRIPTIONS' => append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=ucp_main&amp;mode=subscribed')
		));
	}
    public function inject_users_for_topic($topic_id)
    {
		global $phpbb_root_path, $phpEx;
		$sql = 'SELECT DISTINCT poster_id
				FROM ' . POSTS_TABLE . '
				WHERE topic_id=' . $topic_id;

		$distinct_posters = Array();
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$distinct_posters[] = $row['poster_id'];
		}
		$this->db->sql_freeresult($result);

		//TODO if we ever have multiple localizations, this needs a little rework but I don't care atm
		$sql = "SELECT pu.username, pu.user_id, ppfd.pf_user_pronoun_text as pronoun"
				. " FROM " . USERS_TABLE . " pu"
				. " LEFT JOIN " . PROFILE_FIELDS_DATA_TABLE . " ppfd ON pu.user_id = ppfd.user_id"
				. " WHERE " . $this->db->sql_in_set('pu.user_id', $distinct_posters) 
				. " ORDER BY LOWER(pu.username)";

        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $this->template->assign_block_vars('TOPIC_USERS', array(
                'ID'       => $row['user_id'],
				'USERNAME' => $row['username'] . ($row['pronoun'] == '' ? '' : (" (" . $row['pronoun'] . ")"))
            ));
        }
        $this->db->sql_freeresult($result);
        
        $this->template->assign_vars(array(
            'U_ISO_BASE_URL'    => append_sid("{$phpbb_root_path}viewtopic.{$phpEx}"),
        ));
    }
    
    public function viewtopic_modify_page_title($event) {

        $topic_id = $event['topic_data']['topic_id'];
        $forum_id = $event['forum_id'];
        $start = $event['start'];
        $seo = $this->create_viewtopic_seo($topic_id, $forum_id, $start);

        $this->template->assign_vars(array(
            'U_CANONICAL' => $seo['canonical'],
            'ROBOTS' => $seo['robots'],
        ));
    }

    public function inject_template_vars($event)
    {
		$topic_id = $event['topic_id'];
		
		//Modify the base URL to fix pagination.
		$isolation_author_ids = $this->get_isolation_author_ids();
		if(count($isolation_author_ids) > 0) {
			$event['base_url'] = $event['base_url'] . '&user_select%5B%5D=' . implode('&user_select%5B%5D=', $isolation_author_ids);
		}
		if($this->has_parameterized_posts_per_page()) {
			$event['base_url'] = $event['base_url'] . '&ppp=' . $this->get_parameterized_posts_per_page();
		}

        $this->template->assign_vars(array(
            'U_ACTIVITY_OVERVIEW' => $this->helper->route('activity_overview_route', array('topic_id' => $topic_id))
        ));
        
        $this->inject_users_for_topic($topic_id);
    }

    function create_viewtopic_seo($topic_id, $forum_id, $start)
    {
        global $config, $phpEx, $phpbb_root_path;

        if($start % $config['posts_per_page'] != 0)
            return $this->create_viewtopic_default_seo();
        if(request_var('activity_overview', '') || request_var('vote_id', '') || request_var('st', '') || request_var('sk', '') || request_var('sd', '') || request_var('ppp', '') || request_var('p', '') || !empty(request_var('user_select', array('' => 0))))
            return $this->create_viewtopic_default_seo();
        
        return array(
            'canonical'    => $config['server_protocol'] . $config['server_name'] . $config['script_path'] . "/viewtopic.$phpEx?" . (($forum_id) ? "f=$forum_id&" : "") . "t=$topic_id" . ($start == 0 ? "" : "&start=$start"),
            'robots'    => 'INDEX, FOLLOW'
        );
    }

    function create_viewtopic_default_seo() {
        return array(
            'canonical'    => '',
            'robots'    => 'NOINDEX, FOLLOW'
        );
    }

    /**
     * Load the language file
     *     mafiascum/isos/language/en/demo.php
     *
     * @param \phpbb\event\data $event The event object
     */
    public function load_language_on_setup($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = array(
            'ext_name' => 'mafiascum/isos',
            'lang_set' => 'common',
        );
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function viewtopic_modify_post_row($event) {

        global $phpbb_root_path, $phpEx;
		$topic_id = $event['topic_data']['topic_id'];
		$forum_id = $event['topic_data']['forum_id'];
        $poster_id = $event['poster_id'];
		$post_row = $event['post_row'];
		$row = $event['row'];
		$start = $event['start'];
		$post_id = $row['post_id'];
		$user_cache = $event['user_cache'];

		$is_isolation = count($this->get_isolation_author_ids()) > 0;
		$localized_post_number = $post_row['POST_NUMBER'] - 1;

		$actual_post_number = $is_isolation ? $row['actual_post_number'] : $localized_post_number;
		$iso_post_number = $is_isolation ? $localized_post_number : '';

		//pronoun additions
		$pronoun = array_key_exists('PROFILE_USER_PRONOUN_TEXT_VALUE', $event['cp_row']['row']) ? $event['cp_row']['row']['PROFILE_USER_PRONOUN_TEXT_VALUE'] : '';

		$post_row['PRONOUN'] = $pronoun;
        $post_row['ISO_URL'] = append_sid("{$phpbb_root_path}viewtopic.{$phpEx}", "p=$post_id&f=$forum_id&t={$topic_id}&user_select%5B%5D={$poster_id}#p$post_id");
		$post_row['POST_NUMBER'] = $actual_post_number;
		$post_row['ISO_POST_NUMBER'] = $iso_post_number;
		$post_row['S_IS_ISOLATION'] = $is_isolation;

        $event['post_row'] = $post_row;
    }

    function submit_post_end($event) {

        if($event['mode'] == 'edit') {
            global $phpbb_log;
            $subject = $event['subject'];
            $data_ary = $event['data'];
            $username = $event['username'];
            $user = $this->user;
            $poster_id = $data_ary['poster_id'];
            
            if ($user->data['user_id'] == $poster_id) {
                $log_subject = ($subject) ? $subject : $data_ary['topic_title'];
                $phpbb_log->add('mod', $user->data['user_id'], $user->ip, 'LOG_POST_EDITED', false, array(
                    'forum_id' => $data_ary['forum_id'],
                    'topic_id' => $data_ary['topic_id'],
                    'post_id'  => $data_ary['post_id'],
                    $log_subject,
                    (!empty($username)) ? $username : $user->lang['GUEST'],
                    $data_ary['post_edit_reason']
                ));
            }
        }
	}

	function ucp_profile_modify_signature_sql_ary($event) {
		global $phpbb_container, $config;
		$utils = $phpbb_container->get('text_formatter.utils');
		$parser = $phpbb_container->get('text_formatter.parser');
		$sql_ary = $event['sql_ary'];

//		$min_lines_to_hide = 4;
		$disabled_tags = explode("|", $config['disabled_sig_bbcodes']);
		$text = $utils->unparse($sql_ary['user_sig']);

		foreach($disabled_tags as $disabled_tag) {
			$parser->disable_bbcode($disabled_tag);
		}

//		$signature_line_length = $this->calculate_signature_line_length($text);

		$xml = $parser->parse($text);

		$sql_ary['user_sig'] = $xml;
		$event['sql_ary'] = $sql_ary;
	}

	function calculate_signature_line_length($text) {
		return substr_count($text, "\n") + 1;
	}

	function acp_board_config_edit_add($event) {
		$mode = $event['mode'];

		switch($mode) {
			case 'signature':
				$display_vars = $event['display_vars'];
				$vars = $display_vars['vars'];
				$keys = array_keys($vars);
		
				$disabled_sig_bbcodes = array(
					'lang' => 'DISABLED_SIG_BBCODES',
					'validate' => 'string',
					'type' => 'text:40:150',
					'explain' => true
				);
				
				$arr_length = count($vars);
		
				$vars = array_merge(
					array_slice($vars, 0, $arr_length - 1),
					array('disabled_sig_bbcodes' => $disabled_sig_bbcodes),
					array_slice($vars, $arr_length - 1)
				);
		
				$display_vars['vars'] = $vars;
				$event['display_vars'] = $display_vars;
				break;
		}
	}

	function get_isolation_author_ids() {
		return $this->request->variable('user_select', array(0));
	}

	function viewtopic_get_post_data($event) {
		
		global $phpbb_container, $config;
		$phpbb_content_visibility = $phpbb_container->get('content.visibility');

		$sql_ary = $event['sql_ary'];
		$where = $sql_ary['WHERE'];

		$author_ids = $this->get_isolation_author_ids();
		
		if(count($author_ids) <= 0) {
			return;
		}

		$sort_key = $event['sort_key'];
		$topic_id = $event['topic_id'];
		$forum_id = $event['forum_id'];
		$sql_sort_order = 'p.post_time ASC';
		$sql_limit = $config['posts_per_page'];
		$sql_start = $event['start'];

		$sql = 'SELECT p.post_id
				FROM ' . POSTS_TABLE . " p
				WHERE p.topic_id = $topic_id
				AND " . $this->db->sql_in_set('p.poster_id', $author_ids) . "
				AND " . $phpbb_content_visibility->get_visibility_sql('post', $forum_id, 'p.') . "
				ORDER BY $sql_sort_order";

		$result = $this->db->sql_query_limit($sql, $sql_limit, $sql_start);
		$post_list = array();
		while ($row = $this->db->sql_fetchrow($result)) {
			$post_list[] = $row['post_id'];
		}

		$this->db->sql_freeresult($result);

		$sql_replace = $this->db->sql_in_set('p.post_id', $post_list);

		$where = preg_replace('/p\.post_id\s+IN\s*\(.*?\)/', $sql_replace, $where);
		$where = preg_replace('/p\.post_id\s*=\s*\d+/', $sql_replace, $where);

		$sql_ary['WHERE'] = $where;
		$event['sql_ary'] = $sql_ary;
		$event['post_list'] = $post_list;
	}

	public function viewtopic_modify_post_data($event) {

		global $phpbb_container;
		$phpbb_content_visibility = $phpbb_container->get('content.visibility');
		
		$rowset = $event['rowset'];
		$post_list = $event['post_list'];
		$topic_id = $event['topic_id'];

		if(!is_null($this->post_id_to_post_number_map)) {
			//Record the actual post number on the post rows
			foreach($rowset as $i => $row) {

				$actual_post_number = $this->post_id_to_post_number_map[$row['post_id']];

				$row['actual_post_number'] = $actual_post_number;

				$rowset[$i] = $row;
			}
		}

		$event['rowset'] = $rowset;
	}

	function viewtopic_before_f_read_check($event) {
		global $config;

		if(!empty($this->get_isolation_author_ids())) {
			$config['posts_per_page'] = 200;
		}

		if($this->has_parameterized_posts_per_page()) {
			$config['posts_per_page'] = $this->get_parameterized_posts_per_page();
		}
	}

	function has_parameterized_posts_per_page() {
		$posts_per_page = $this->request->variable('ppp', '');
		return !empty($posts_per_page) && is_numeric($posts_per_page);
	}

	function get_parameterized_posts_per_page() {
		return max(1, min(intval($this->request->variable('ppp', '')), 200));
	}

	function viewtopic_highlight_modify($event) {
		if(!empty($this->get_isolation_author_ids())) {

			global $phpbb_container, $config;
			$phpbb_content_visibility = $phpbb_container->get('content.visibility');
			$topic_id = $event['topic_data']['topic_id'];
			$forum_id = $event['topic_data']['forum_id'];
			$start_post_id = $this->request->variable('p', '');

			//Let's get the real post numbers. Kison, 2011-06-19
			$this->db->sql_query('SET @post_count := -1;');

			$sql = 'SELECT tmp.post_id, tmp.post_number FROM
			(
			SELECT
				post_id,
				poster_id,
				@post_count := @post_count + 1 AS post_number
			FROM ' . POSTS_TABLE . " p
			WHERE p.topic_id=$topic_id
			AND " . $phpbb_content_visibility->get_visibility_sql('post', $forum_id, 'p.') . "
			ORDER BY p.post_time ASC
			) AS tmp
			WHERE " . $this->db->sql_in_set("tmp.poster_id", $this->get_isolation_author_ids(), false, false);
			
			$result = $this->db->sql_query($sql);

			$this->post_id_to_post_number_map = array();
			$total_posts = 0;
			$find_start_post = !empty($start_post_id) && empty($this->request->variable('start', ''));
			$found_start_post = $find_start_post;
			$start = 0;

			while($row = $this->db->sql_fetchrow()) {

				$this->post_id_to_post_number_map[(int)$row['post_id']] = $row['post_number'];

				if($found_start_post && $start_post_id == $row['post_id']) {
					$start = $total_posts;
					$found_start_post = false;
				}

				$total_posts++;
			}

			$this->db->sql_freeresult($result);

			$event['total_posts'] = $total_posts;
			if($find_start_post) {
				$event['start'] = $start - ($start % $config['posts_per_page']);
			}
		}
	}
}
