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

        $existingIds = array();
        $userId = (is_user_logged_in()) ? $current_user->ID : '1';

        /**
         * Check to see if there is anything in the $_GET
         */
        $actionType = (isset($_GET['action']) && !empty($_GET['action'])) ? $_GET['action'] : null;
        $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? $_GET['paged'] : 1;

        foreach($posts as $post) {
            $ids[] = $post->ID;

            //$actions[] = new UserActionPost($post);
        }

        $ajQuery = new ActionJacksonQuery();
        $postActions = $ajQuery->getPostAction('posts', $ids, null, null, null, null, null, false);

        $ids = array();

        foreach($postActions as $postAction) {
            $ids[] = $postAction->post_action_id;

            $actions[] = new PostAction($postAction);
        }

        $userActions = $ajQuery->getUserActions($userId, null, $ids, null, $page, 10);

        if(isset($userAction) && !emptY($userAction)) {
            foreach($userActions as $userAction) {
                foreach($actions as $action) {
                    if($action->id == $userAction->object_id) {
                        $action->userAction = $userAction;
                        $action->user = new UserAction($userAction);
                    }
                }
            }
        }

        foreach($posts as $post) {
            if(isset($userAction) && !emptY($userAction)) {
                foreach($actions as $action) {
                    if($action->objectId == $post->ID) {
                        $post->actions[] = $action;
                    }
                }
            }
        }

        return $posts;
    }
    add_filter('posts_results', 'getUserActionsOnPosts');

    function dont_suppress_filters($query){
        $query->query_vars['suppress_filters'] = false;
        return $query;
    }
    add_filter('pre_get_posts', 'dont_suppress_filters');

    function addUserAction() {
        global $current_user;
        get_currentuserinfo();
        $userId = isset($current_user->ID) && $current_user->ID > 0 ? $current_user->ID : 0;

        $ajQuery = new ActionJacksonQuery();
        $result = $ajQuery->addUserAction((int)$_POST['id'], $_POST['type'], $_POST['name'], $_POST['sub_type'], $userId);
        //$result = $ajQuery->addUserAction('332', 'post', 'upvote', 'question', 1);

        echo json_encode($result);
        exit;
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

        if ( is_user_logged_in() ) {
            global $current_user;

            get_currentuserinfo();

            $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? $_GET['paged'] : 1;

            foreach($comments as $comment) {
                $ids[] = $comment->comment_ID;
            }

            $ajQuery = new ActionJacksonQuery();
            $postActions = $ajQuery->getPostAction('comments', $ids, null, null, null, null, null, false);

            $ids = array();

            foreach($postActions as $postAction) {
                $ids[] = $postAction->object_id;

//                $actions[] = new PostAction($postAction);
            }

            $userActions = $ajQuery->getUserActions($userId, null, $ids, null, $page, 10);

            foreach($userActions as $userAction) {
                $actions[] = new PostAction($userAction);
            }

            if(isset($userActions) && !emptY($userActions)) {
                foreach($userActions as $userAction) {
                    foreach($actions as $action) {
                        if($current_user->ID == $action->user && $action->id == $userAction->action_id) {
                            $action->user = new UserAction($userAction);
                        } else {
                            $action->user = null;
                        }
                    }
                }
            }

            foreach($comments as $comment) {
                if(isset($actions) && !empty($actions)) {
                    foreach($actions as $action) {
                        if($action->objectId == $comment->comment_ID) {
                            $comment->actions[] = $action;
                        }
                    }
                }
            }

//            echo '<pre>';
//            var_dump($comments);
//            exit;

            return $comments;
        }

        return $comments;
    }
    add_filter('the_comments', 'getMyActionsOnComments');

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
