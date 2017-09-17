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
function console_log( $data ){
    echo '<script>';
    echo 'console.log('. json_encode( $data ) .')';
    echo '</script>';
}
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

    static public function getSubscribedEvents()
    {
        return array(
            'core.search_modify_param_before' => 'process_multi_author_search',
            'core.search_modify_url_parameters' => 'propagate_multi_author_url_params',
            'core.user_setup'  => 'load_language_on_setup',
            'core.viewtopic_assign_template_vars_before' => 'inject_template_vars',
        );
    }

    /**
     * Constructor
     *
     * @param \phpbb\controller\helper	$helper		Controller helper object
     * @param \phpbb\template\template	$template	Template object
     * @param \phpbb\request\request	$request	Request object
     */
    public function __construct(\phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\request\request $request, \phpbb\db\driver\driver_interface $db)
    {
        $this->helper = $helper;
        $this->template = $template;
        $this->request = $request;
        $this->db = $db;
    }

    public function inject_users_for_topic($topic_id)
    {
        $sql = 'SELECT DISTINCT p.poster_id, u.username
                FROM ' . POSTS_TABLE . ' p
                JOIN ' . USERS_TABLE . ' u
                ON p.poster_id = u.user_id
                WHERE p.topic_id = ' . $topic_id . '
                ORDER BY lower(u.username)';

        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $this->template->assign_block_vars('TOPIC_USERS', array(
                'ID'       => $row['poster_id'],
                'USERNAME' => $row['username'],
            ));
        }
        $this->db->sql_freeresult($result);
    }

    public function inject_template_vars($event)
    {
        $topic_id = $event['topic_id'];

        $this->template->assign_vars(array(
            'U_ACTIVITY_OVERVIEW' => $this->helper->route('activity_overview_route', array('topic_id' => $topic_id))
        ));
        $this->inject_users_for_topic($topic_id);
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

    public function process_multi_author_search($event)
    {
        $author_ids = $this->request->variable('author_ids', array(0));
        if (!empty($author_ids)) {
            $event['author_id_ary'] = $author_ids;
        }       
    }

    public function propagate_multi_author_url_params($event)
    {
        $author_ids = $this->request->variable('author_ids', array(0));
        if (!empty($author_ids)) {
            foreach ($author_ids as $author_id) {
                $event['u_search'] .= '&amp;author_ids[]=' . $author_id;
            }
        }
    }
}