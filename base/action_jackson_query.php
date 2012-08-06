<?php
    class ActionJacksonQuery {
        private $_wpdb;

        public function __construct() {
            global $wpdb;

            $this->_wpdb = $wpdb;

            $this->_wpdb->tables = array_merge($this->_wpdb->tables, array($this->_wpdb->prefix.'post_action', $this->_wpdb->prefix.'user_actions'));
        }

        public function addUserAction(
                                    $objId,
                                    $objType,
                                    $action,
                                    $objSubType=null,
                                    $userId=1) {
            if((int)$objId <= 0 || is_null($objId)) {
                Throw new Exception('You need to pass an object ID!');
            }

            if((string)$objType === '' || is_null($objType)) {
                Throw new Exception('You need to pass an object type!');
            }

            $actions = $this->_getUserAction($userId, $action, $objId, $objType);
            if(isset($actions) && !empty($actions)) {
                return 1;
            }

            //_addPostAction($objId, $objType, $action, $objectSubtype=null) {
            $result = $this->_addPostAction($objId, $objType, $action, $objSubtype);
            if(isset($result) && $result > 0) {
                $args = array(
                    'user_id' => $userId,
                    'object_id' => $result,
                    'action_added' => strtotime('now')
               	);

                $result = $this->_wpdb->insert($this->_wpdb->prefix.'user_actions', $args);

                return $result;
            } else {
                return false;
            }
        }

        public function updateUserAction(
                                        $actionId,
                                        $objId=null,
                                        $objType=null,
                                        $objSubType=null,
                                        $action=null) {
            $args = array(
                'action_type' => $action,
                'object_type' => $objType,
                'object_subtype' => $objSubType,
                'object_id' => $objId
           	);

            //$this->update( $table, $data, $where, $format = null, $where_format = null );
            $result = $this->_wpdb->update($this->_wpdb->prefix.'user_actions', $args, array('user_action_id' => $actionId));
            if($result == true) {
                $this->_updatePostAction($actionId, $objId, $objType, $objSubType, $action);
            }
        }

        public function getUserActions($user_id, $object_id=null, $page=1, $limit=10) {
            if(
                ((isset($objId) && is_int($objId) && $objId > 0 ) && (!isset($objType) || is_null($objType) || $objType == ''))
                ||
                ((!isset($objId) ||!is_int($objId) || $objId <= 0) && (isset($objType) && is_string($objType) && $objType != ''))
            ) {
                Throw new Exception('If you want to get a user\'s action based an object, you need to pass in an object type and object ID!');
            }

            /**
             * This must be here, otherwise other non-argument variables will be included.
             */
            $args = get_defined_vars();

            unset($args['limit']);
            unset($args['object_type_id_key_name']);
            unset($args['page']);
            unset($args['wp_query']);

            $startLimit = ($page * $limit) - $limit;

            $args = $this->_unsetNulls($args);

            $argCount = count($args);

            $query = 'SELECT
                            ua.*
                        FROM
                            '.$this->_wpdb->prefix.'user_actions ua
                        WHERE ';

            foreach($args as $key=>$arg) {
                if(!is_null($arg) && !empty($arg)) {
                    $arg = is_string($arg) ? '"'.$arg.'"' : $arg;

                    if(is_array($arg)) {
                        $query .= ($i < ($argCount - 1)) ? $key.' IN ('.implode(',', $arg).') AND ' : $key.' IN ('.implode(',', $arg).')';
                    } else {
                        $query .= ($i < ($argCount - 1)) ? $key.'='.$arg.' AND ' : $key.'='.$arg;
                    }

                    $i++;
                }
            }

            $query .= ' LIMIT '.$startLimit.','.$limit;

            echo $query;

            return $this->_wpdb->get_results($query);
        }

        /**
         * @param $object_type Object name based on WP table names (posts, terms, users, etc.)
         * @param $object_id Object ID related to the object type (post ID, etc.)
         * @param null $object_sub_type Object subtype (page, category, etc.)
         * @param null $post_action_id (
         * @param null $action_type
         * @param int $limit
         * @param int $page
         * @param bool $limited
         * @return mixed
         */
        public function getPostAction($object_type, $object_id, $object_subtype=null, $post_action_id=null, $action_type=null, $limit=10, $page=1, $limited=true) {
            $args = get_defined_vars();

            if(isset($post_action_id) && (int)$post_action_id > 0) {
                unset($args['obejct_type']);
                unset($args['object_id']);
                unset($args['object_subtype']);
            }

            unset($args['limit']);
            unset($args['limited']);
            unset($args['object_type_id_key_name']);
            unset($args['page']);
            unset($args['wp_query']);

            $startLimit = ($page * $limit) - $limit;

            $args = $this->_unsetNulls($args);

            $argCount = count($args);

            $query = 'SELECT
                            *
                        FROM
                            '.$this->_wpdb->prefix.'post_actions
                        WHERE ';

            foreach($args as $key=>$arg) {
                if(!is_null($arg) && !empty($arg)) {
                    $arg = is_string($arg) ? '"'.$arg.'"' : $arg;

                    if(is_array($arg)) {
                        $query .= ($i < ($argCount - 1)) ? $key.' IN ('.implode(',', $arg).') AND ' : $key.' IN ('.implode(',', $arg).')';
                    } else {
                        $query .= ($i < ($argCount - 1)) ? $key.'='.$arg.' AND ' : $key.'='.$arg;
                    }

                    $i++;
                }
            }

            $query .= $limited === true ? ' LIMIT '.$startLimit.','.$limit : '';

            return $this->_wpdb->get_results($query);
        }

        private function _addPostAction($objId, $objType, $action, $objectSubtype=null) {
            $result = $this->getPostAction($objType, $objId, null, null, $action);
            if(isset($result) && !empty($result)) {
                $updated = $this->_updatePostAction($result[0]->post_action_id, null, null, null, $action, $result[0]->action_total + 1);
                if($updated == 1) {
                    return $result[0]->post_action_id;
                }
            }

            $args = array(
                'action_type' => $action,
                'object_type' => $objType,
                'object_subtype' => $objSubType,
                'object_id' => $objId,
                'action_total' => 1,
                'last_modified' => strtotime('now')
            );

            $result = $this->_wpdb->insert($this->_wpdb->prefix.'post_actions', $args);
            if($result == 1) {
                echo 'the id is '.$this->_wpdb->insert_id.'<br/>';
                return $this->_wpdb->insert_id;
            }
        }

        private function _addUserAction($objId, $objType, $action, $objectSubtype=null) {
            $result = $this->_getUserAction($objType, $objId, null, null, $action);
            if(isset($result) && !empty($result)) {

            }

            $args = array(
                'action_type' => $action,
                'object_type' => $objType,
                'object_subtype' => $objSubType,
                'object_id' => $objId,
                'action_total' => 1,
                'last_modified' => strtotime('now')
            );

            $result = $this->_wpdb->insert($this->_wpdb->prefix.'post_actions', $args);
            if($result == 1) {
                return $this->_wpdb->insert_id;
            }
        }

        private function _updatePostAction($actionId, $objId=null, $objType=null, $objSubType=null, $action=null, $action_total=null) {
            $args = $this->_unsetNulls(array(
                            'action_type' => $action,
                            'object_type' => $objType,
                            'object_subtype' => $objSubType,
                            'object_id' => $objId,
                            'action_total' => $action_total
                       	));

            $formats = $this->_buildFormats($args);

            return $this->_wpdb->update($this->_wpdb->prefix.'post_actions', $args, array('post_action_id' => $actionId), $formats, array('%d'));
        }

        private function _getUserAction($user_id, $action_type, $object_id, $object_type) {
            $args = get_defined_vars();

            $query = 'SELECT
                            ua.*, pa.*
                        FROM
                            '.$this->_wpdb->prefix.'user_actions ua
                        JOIN
                            '.$this->_wpdb->prefix.'post_actions pa
                                ON
                                    ua.object_id=pa.post_action_id
                        WHERE ';

            $args = $this->_unsetNulls($args);
            $argCount = count($args);

            foreach($args as $key=>$arg) {
                if(!is_null($arg) && !empty($arg)) {
                    $arg = is_string($arg) ? '"'.$arg.'"' : $arg;

                    $tableAlias = ($key == 'user_id') ? 'ua' : 'pa';
                    echo $key.' '.$tableAlias.'<br/>';

                    $query .= ($i < ($argCount - 1)) ? $tableAlias.'.'.$key.'='.$arg.' AND ' : $tableAlias.'.'.$key.'='.$arg;

                    $i++;
                }
            }

            return $this->_wpdb->get_results($query);
        }

        private function _unsetNulls($args) {
            foreach($args as $key=>$arg) {
                if(is_null($arg) || empty($arg)) {
                    unset($args[$key]);
                }
            }

            return $args;
        }

        private function _buildFormats($args) {
            $formats = array();

            foreach($args as $key=>$arg) {
                if(is_string($arg)) {
                    $formats[] = '%s';
                } elseif(is_int($arg)) {
                    $formats[] = '%d';
                } elseif(is_float($arg)) {
                    $formats[] = '%f';
                }
            }

            return $formats;
        }
    }