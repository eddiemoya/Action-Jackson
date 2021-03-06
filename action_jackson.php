<?php
	/*
	Plugin Name: UserActions
	Plugin URL: http://wordpress.org/
	Description: Tracking of all User Actions
	Author: Sebastian Frohm
	Version: 0.1
	*/

	//include base definitions
	//include('globals/USERACTIONS.constants.php');
	@define('ACTIONJACSON_BASE_DIR', 'action_jackson/');

	@define('ACTIONJACSON_ACTIONS_DIR', 'base/');
	@define('ACTIONJACSON_INSTANCES_DIR', 'controllers/instances/');
	@define('ACTIONJACSON_VIEWS_DIR', 'views/');

	//include(USERACTIONS_ACTIONS_DIR.'UserActionsViews.actions.php');
	//include(USERACTIONS_ACTIONS_DIR.'UserActionsAdmin.actions.php');
	include('base/action_jackson_query.php');
    include('models/post_action.php');
    include('models/user_action.php');

    function getUserActionsOnPosts($posts) {
        global $current_user;

        $existingIds = $actions = $ids = array();
        $userId = (is_user_logged_in()) ? $current_user->ID : '0';

        /**
         * Check to see if there is anything in the $_GET
         */
        $actionType = (isset($_GET['action']) && !empty($_GET['action'])) ? $_GET['action'] : null;
        $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? $_GET['paged'] : 1;

        foreach($posts as $post) {
            $ids[] = $post->ID;
        }

        $ajQuery = new ActionJacksonQuery();
        $postActions = $ajQuery->getPostAction('posts', $ids, null, null, null, null, null, false);

        $ids = array();

        foreach($postActions as $postAction) {
            $actions[] = new PostAction($postAction);

            $ids[] = $postAction->object_id;
        }

        if(is_user_logged_in()) {
            $userActions = $ajQuery->getUserActions($userId, null, $ids, null, $page, 10);

            // foreach($userActions as $userAction) {
            //     $actions[] = new PostAction($userAction);
            // }

            // if(isset($userActions) && !emptY($userActions)) {
            //     foreach($userActions as $userAction) {
            //         foreach($actions as $action) {
            //             $userId = (isset($action->user) && is_object($action->user) && get_class($action->user) === 'UserAction') ? $action->user->userId : $action->user;

            //             if($current_user->ID == $userId && $action->id == $userAction->action_id) {
            //                 $action->user = new UserAction($userAction);
            //             }
            //         }
            //     }
            // }

            foreach ($actions as $action)
            {
                foreach ($userActions as $userAction)
                {
                    $userId = (isset($action->user) && is_object($action->user) && get_class($action->user) === 'UserAction') ? $action->user->userId : $action->user;

                    if ($current_user->ID == $userId && $action->id == $userAction->action_id)
                    {
                        $action->user = new UserAction($userAction);
                    }
                }
            }

            foreach ($userActions as $userAction)
            {
                $action = new PostAction($userAction);
                $userId = (isset($action->user) && is_object($action->user) && get_class($action->user) === 'UserAction') ? $action->user->userId : $action->user;

                if ($current_user->ID == $userId && $action->id == $userAction->action_id)
                {
                    $action->user = new UserAction($userAction);
                }

                $actions[] = $action;
            }
        } else {
            $allActions = array();

            $myActions = json_decode(urldecode(stripslashes($_COOKIE['actions'])), true);

            if(isset($myActions) && !empty($actions)) {
                $myActions = $myActions['actions'];

                foreach($myActions as $action) {
                    $ids[] = $action['id'];
                }

                $actions = $ajQuery->getPostAction('posts', $ids);

                foreach($actions as $action) {
                    foreach($myActions as $myAction) {
                        if($myAction['id'] == $action->object_id && $myAction['name'] == $action->action_type) {
                            $allActions[] = new PostAction($action);
                        }
                    }
                }

                $actions = $allActions;
            }
        }

        foreach($posts as $post) {
            if(isset($actions) && !empty($actions)) {
                foreach($actions as $action) {
                    if($action->objectId == $post->ID) {
                        $post->actions[] = $action;
                    }
                }
            }
        }

        return $posts;
    }
    add_filter('the_posts', 'getUserActionsOnPosts');

    function dont_suppress_filters($query){
        $query->query_vars['suppress_filters'] = false;
        return $query;
    }
    add_filter('pre_get_posts', 'dont_suppress_filters');

    function addUserAction() 
    {
		global $current_user;
		
        $ret = array();
        get_currentuserinfo();
        
        if(is_user_logged_in())
        {
			$ajQuery = new ActionJacksonQuery();
			
			$postId = (ctype_digit($_POST['id'])) ? $_POST['id'] : false;
			$postType = (!empty($_POST['type'])) ? $_POST['type'] : false;
			$postSubType = (!empty($_POST['sub_type'])) ? $_POST['sub_type'] : false;
			$postName = (!empty($_POST['name'])) ? $_POST['name'] : false;
			
			if(!$postId || !$postType || !$postSubType || !$postName)
			{
				
				throw new Exception("Invalid inputs");
			}
	file_put_contents("/tmp/blah3.txt", "Valid Inputs");		
			$ret = $ajQuery->addUserAction((int)$postId, $postType, $postName, $postSubType, $current_user->ID);
		}
		
        echo json_encode($ret);
        exit();
    }
    add_action('wp_ajax_add_user_action', 'addUserAction');
    add_action('wp_ajax_nopriv_add_user_action', 'addUserAction');

    /**
     * Get all actions a user has performed on a set of comments
     *
     * @param $comments array of comments
     * @return $comments array
     */
    function getMyActionsOnComments($comments) {
        if(!isset($comments) || empty($comments)) {
            return;
        }

        global $current_user;

        get_currentuserinfo();

        $actions = array();
        $ids = array();

        foreach($comments as $comment) {
            $ids[] = $comment->comment_ID;
        }

        $ajQuery = new ActionJacksonQuery();
        $postActions = $ajQuery->getPostAction('comments', $ids, null, null, null, null, null, false);

        foreach($postActions as $action) {
            $actions[] = new PostAction($action);
        }

        foreach($actions as $action) {
            $ids[] = $action->id;
        }

        if(is_user_logged_in()) {
            $userActions = $ajQuery->getUserActions($current_user->ID, null, $ids);

            if(isset($userActions) && !emptY($userActions)) {
                foreach($userActions as $userAction) {
                    foreach($actions as $action) {
                        if($action->id == $userAction->action_id) {
                            $action->user = new UserAction($userAction);
                        }
                    }
                }
            }
        } else {
            $allActions = array();

            $myActions = json_decode(urldecode(stripslashes($_COOKIE['actions'])), true);

            if(isset($myActions) && !empty($myActions)) {
                $myActions = $myActions['actions'];

                foreach($actions as $action) {
                    foreach($myActions as $myAction) {
                        if($myAction['id'] == $action->objectId && $myAction['name'] == $action->action) {
                            $action->user = (object)$myAction;
                        }
                    }
                }
            }
        }

        foreach($comments as $comment) {
            if(isset($actions) && !empty($actions)) {
                foreach($actions as $action) {
                    if($action->objectId == $comment->comment_ID) 
                    {
						$afu = (is_user_logged_in() && (!empty($action->user))) ? true : false;
						$action->active_for_user = $afu;
						$comment->actions[$action->action] = $action;
                    }
                }
            }
        }

        return $comments;
    }
    add_filter('the_comments', 'getMyActionsOnComments', 9);

    function action_jackson_install() {
        global $wpdb, $action_jackson_db_version;

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.'post_actions` (
                 `post_action_id` int(11) NOT NULL AUTO_INCREMENT,
                 `object_type` varchar(20) NOT NULL,
                 `object_subtype` varchar(30) DEFAULT NULL,
                 `object_id` int(11) NOT NULL,
                 `action_type` varchar(20) NOT NULL,
                 `action_total` int(11) NOT NULL,
                 `last_modified` int(11) NOT NULL,
                 PRIMARY KEY (`post_action_id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10;

               CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.'user_actions` (
                  `user_action_id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` bigint(20) NOT NULL,
                  `object_id` int(11) NOT NULL,
                  `action_added` int(11) NOT NULL,
                  PRIMARY KEY (`user_action_id`),
                  KEY `user_id` (`user_id`),
                  KEY `object_id` (`object_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=18 ;';

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option("action_jackson_db_version", $action_jackson_db_version);
    }

    register_activation_hook(__FILE__, 'action_jackson_install');
    
    function spitOut($txt, $n = false)
    {
		$flags = ($n) ? FILE_APPEND : 0;
		file_put_contents("/tmp/out.txt", "$txt\n", $flags); 
    }
