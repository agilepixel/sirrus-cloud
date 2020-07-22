<?php
/**
 * Plugin Name:       Sirrus Cloud
 * Plugin URI:        https://www.sirruscomputers.com/
 * Description:       Sirrus Cloud integration
 * Version:           2.2.4
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

require 'plugin-update-checker/plugin-update-checker.php';
$scUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/agilepixel/sirrus-cloud/',
    __FILE__,
    'sirrus-cloud'
);

//Optional: If you're using a private repository, specify the access token like this:
//$scUpdateChecker->setAuthentication('your-token-here');

//Optional: Set the branch that contains the stable release.
$scUpdateChecker->setBranch('master');

if (!class_exists('Sirrus_Cloud')) {
    class Sirrus_Cloud
    {
        public static $instance = false;
        public static $version = '2.2.4';
        public static $path = '';
        public static $settings = array();

        private static $stock = [];
        private static $group = [];
        private static $artist = [];

        private static $acf_fields = [];
        private static $acf_groups;

        private function __construct()
        {
            self::$path = plugin_dir_path(__FILE__);

            /* register import endpoint */
            add_action('wp_ajax_aimp', array( $this, 'import_endpoint'), 10);
            add_action('wp_ajax_nopriv_aimp', array( $this, 'import_endpoint'), 10);
            add_action('wp_ajax_aimp_test', array( $this, 'test_connection'), 10);

            add_action('wp_ajax_aimp_wp_field', array( $this, 'mapping_wp_endpoint'), 10);
            add_action('wp_ajax_aimp_source_field', array( $this, 'mapping_source_endpoint'), 10);
            add_action('wp_ajax_aimp_wp_posttype', array( $this, 'posttype_wp_endpoint'), 10);

            /* register option page */
            if (is_admin()) {
                add_action('admin_enqueue_scripts', array( $this, 'admin_assets'));
                add_action('admin_menu', array( $this, 'options_pages'));
            }

            /* load settings */
            self::fetch_settings();
        }

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
        }

        public static function settings_fields($is_save = false)
        {
            if ($is_save) {
                return array(
                    'import_endpoint',
                    'import_username',
                    'import_password',
                    'import_stock_link_post_type',
                    'import_artist_link_post_type',
                    'import_group_link_post_type',
                    'additional_groups',
                    'import_group_link_custom',
                    'import_group_link_post_type_custom'
                );
            } else {
                return array(
                    'import_endpoint',
                    'import_username',
                    'import_password',
                    'import_stock_link_post_type',
                    'import_artist_link_post_type',
                    'import_group_link_post_type',
                    'import_group_link_custom',
                    'import_group_link_post_type_custom',
                    'sirrus_cloud_mapping_stock',
                    'sirrus_cloud_mapping_artist',
                    'sirrus_cloud_mapping_group',
                );
            }
        }

        public static function fetch_settings()
        {
            $settings = self::settings_fields();
            foreach ($settings as $_setting) {
                self::$settings[$_setting] = get_option($_setting);
            }

            self::$settings['field_url'] = false;

            /* setting import scheme url */
            if (!empty(self::$settings['import_endpoint'])) {
                self::$settings['field_url'] = 'https://'.self::$settings['import_endpoint'].'/crontabs/export_fields';
            }
        }

        public static function get_fields()
        {
            $upload_dir = self::get_upload_dir();
            $fields = array();
            if (file_exists($upload_dir.'/import-scheme.json')) {
                $fields = file_get_contents($upload_dir.'/import-scheme.json');
                $fields = json_decode($fields, true);
            }
            return $fields;
        }

        public static function get_upload_dir($all = false)
        {
            $upload = wp_upload_dir();
            if ($all) {
                return $upload;
            }
            return $upload['basedir'];
        }

        public static function wp_standard_field()
        {
            $post = new stdClass();
            $post->post_title = '';
            $post->post_content = '';
            $post->post_excerpt = '';
            return $post;
        }

        public function test_connection()
        {
            $output = array();

            self::fetch_settings();
            if (self::fetch_import_scheme()) {
                $output = array(
                    'success' => true,
                    'message' => __('Successfully connected with Wordpress site.', 'sirrus_cloud'),
                );
            } else {
                $output = array(
                    'success' => false,
                    'message' => __('Fail connect to Wordpress site, please review configuration.', 'sirrus_cloud'),
                );
            }

            wp_send_json($output);
            wp_die();
        }

        public function mapping_wp_endpoint()
        {
            global $wpdb;
            $output = array();
            $q = $_GET['q'];

            $standard = array(
                array('id' => 'post_title', 'text' => 'post_title'),
                array('id' => 'post_content', 'text' => 'post_content'),
                array('id' => 'post_excerpt', 'text' => 'post_excerpt'),
                array('id' => 'post_thumbnails', 'text' => 'post_thumbnails'),
            );

            $output[] = array('text' => 'Standard Field','children' => $standard);

            if (!empty($q)) {
                $results = $wpdb->get_results("SELECT meta_key FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE '".$q."%' group by meta_key", ARRAY_A);
            } else {
                $results = $wpdb->get_results("SELECT meta_key FROM {$wpdb->prefix}postmeta group by meta_key", ARRAY_A);
            }
            if (!empty($results)) {
                $result = [];
                $foundKeys = [];
                foreach ($results as $_result) {
                    if (in_array($_result['meta_key'], array('post_title', 'post_content', 'post_excerpt', 'post_thumbnails'))) {
                        continue;
                    }
                    if (substr($_result['meta_key'], 0, 1) === '_') {
                        continue;
                    }
                    $foundKeys[] = $_result['meta_key'];
                    $result[] = array(
                        'id'       => $_result['meta_key'],
                        'text'     => 'Meta -> ' . $_result['meta_key'],
                    );
                }

                $not_in = implode(', ', array_map(function ($value) {
                    return '"'.$value.'"';
                }, $foundKeys));

                $sql = "SELECT post_excerpt FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_excerpt NOT IN (".$not_in.")";
                $acf_contents = $wpdb->get_results($sql);
                
                foreach ($acf_contents as $_result) {
                    $foundKeys[] = $_result->post_excerpt;
                    $result[] = array(
                        'id'       => $_result->post_excerpt,
                        'text'     => 'Meta -> ' . $_result->post_excerpt,
                    );
                }
                
                $group_contents = self::get_acf_groups();
                foreach ($group_contents as $group_content) {
                    $object = get_field_object($group_content->post_name);
                    foreach ($object['sub_fields'] as $sub_field) {
                        $join = $object['name'] . '_' . $sub_field['name'];
                        if (!in_array($join, $foundKeys)) {
                            $foundKeys[] = $join;
                            $result[] = array(
                                'id'       => $join,
                                'text'     => 'Meta -> ' . $join,
                            );
                        }
                    }
                }

                usort($result, function ($a, $b) {
                    return strcmp($a["id"], $b["id"]);
                });
                
                $output[] = array('text' => 'Custom field','children' => $result);
            }

            $taxes = get_taxonomies();
            if (count($taxes) > 0) {
                $result = [];
                foreach ($taxes as $tax) {
                    $result[] = array(
                        'id'       => 'taxonomy~'.$tax,
                        'text'     => 'Taxonomy -> ' . $tax,
                    );
                    if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields')) {
                        $groups = acf_get_field_groups(array('taxonomy' => $tax));

                        if ($tax == 'artists') {
                            foreach ($groups as $group) {
                                $fields = acf_get_fields($group['key']);
                                foreach ($fields as $field) {
                                    $result[] = array(
                                        'id'       => 'taxonomyfield~'.$tax.'~'.$field['name'],
                                        'text'     => 'Taxonomy -> ' . $tax .' -> ' . $field['name'],
                                    );
                                }
                            }
                        }
                    }
                }
                usort($result, function ($a, $b) {
                    return strcmp($a["id"], $b["id"]);
                });
                $output[] = array('text' => 'Taxonomy','children' => $result);
            }

            wp_send_json(array('results' => $output));
        }

        public function posttype_wp_endpoint()
        {
            $result = array();
            $post_types = get_post_types();
            unset($post_types['post']);
            unset($post_types['page']);
            unset($post_types['attachment']);
            unset($post_types['revision']);
            unset($post_types['nav_menu_item']);
            unset($post_types['custom_css']);
            unset($post_types['customize_changeset']);
            unset($post_types['oembed_cache']);
            unset($post_types['user_request']);
            unset($post_types['wp_block']);

            $term = @$_GET['term'];
            if (!empty($post_types)) {
                foreach ($post_types as $_field => $empty) {
                    if (!empty($term) && !preg_match("#".$term."#", $_field)) {
                        continue;
                    }
                    $result[] = array(
                        'id'       => $_field,
                        'text'     => 'Post -> '.$_field,
                    );
                    $taxonomies = get_object_taxonomies($_field);

                    foreach ($taxonomies as $taxonomy) {
                        $result[] = array(
                            'id'       => 'taxonomy~'.$taxonomy,
                            'text'     => 'Post -> '.$_field. '-> Taxonomy -> '.$taxonomy,
                        );
                    }

                    if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields')) {
                        $groups = acf_get_field_groups(array('post_type' => $_field));
                        foreach ($groups as $group) {
                            $fields = acf_get_fields($group['key']);
                            foreach ($fields as $field) {
                                if (!empty($field['name'])) {
                                    $result[] = array(
                                        'id'       => 'taxonomy~'.$field['name'],
                                        'text'     => 'Post -> ' . $_field .' -> ' . $field['name'],
                                    );
                                }
                            }
                        }
                    }
                }
            }

            wp_send_json(array('results' => $result));
        }

        public function mapping_source_endpoint()
        {
            $result = array();
            $fields = self::get_fields();

            $term = @$_GET['term'];
            $selected = @$_GET['selected'];
            $type = $_GET['type'];
            foreach ($fields as $_type => $_fields) {
                if ($type == $_type) {
                    $group = array('text' => $_type, 'children' => array());
                    foreach ($_fields as $_field => $empty) {
                        if (!empty($term) && !preg_match("#".$term."#", $_field)) {
                            continue;
                        }
                        $group['children'][] = array(
                            'id'       => $_field,
                            'text'     => $_field,
                        );
                        if ($_field === 'stock_image') {
                            $group['children'][] = array(
                                'id'       => 'stock_image_additional',
                                'text'     => 'stock_image_additional',
                            );
                        }
                    }
                    $result[] = $group;
                }
            }
            wp_send_json(array('results' => $result));
        }

        public function phpConfigValueInBytes(string $var)
        {
            $value = trim(ini_get($var));
            $unit = strtolower(substr($value, -1, 1));
            $value = (int)$value;
    
            switch ($unit) {
                case 'g':
                    $value *= 1024;
                // no break (cumulative multiplier)
                case 'm':
                    $value *= 1024;
                // no break (cumulative multiplier)
                case 'k':
                    $value *= 1024;
            }
    
            return $value;
        }

        public function maxPowerCaptain()
        {
            // Don't mess with the memory_limit, even at the config's request, if it's already set to -1 or >= 1.5GB
            $memoryLimit = $this->phpConfigValueInBytes('memory_limit');
            if ($memoryLimit !== -1 && $memoryLimit < 1024 * 1024 * 1536) {
                @ini_set('memory_limit', $maxMemoryLimit ?: '1536M');
            }
    
            // Try to disable the max execution time
            @set_time_limit(0);
        }

        public function import_endpoint()
        {
            $this->maxPowerCaptain();
            $is_test = isset($_GET['debug']);
            if (isset($_FILES['payload']) && function_exists('gzuncompress')) {
                $post = gzuncompress(file_get_contents($_FILES['payload']['tmp_name']));
            } elseif ($is_test) {
                $upload_dir = plugin_dir_path(__FILE__).'testimport/';
                $post = file_get_contents($upload_dir.'/import-'.$_GET['debug'].'.json');
            } else {
                $post = file_get_contents("php://input");
            }
            $json = json_encode($post, JSON_PRETTY_PRINT);
            
            $upload_dir = self::get_upload_dir();
            $result = array();
            

            if (!empty($post)) {
                if (!$is_test && isset($json['id'])) {
                    $json = json_decode($post, true);
                    file_put_contents($upload_dir.'/import-'.$json['id'].'.json', $post);
                } else {
                    $json = json_decode($post, true);
                }
                if (!empty($json)) {
                    if (!isset($json['items']) && isset($json['pull'])) {
                        $contents = file_get_contents($json['pull']);
                        $json = json_decode($contents, true);
                        if (empty($json)) {
                            $result = array('result' => 'failed', 'log' => 'empty pull');
                            wp_send_json($result);
                            wp_die();
                        }
                    }

                    $authentication_wp = md5(self::$settings['import_username'].self::$settings['import_password']);
                    $authentication = $json['authentication'];

                    $log    = '';
                    $result = $json;
                    $result['items'] = array();

                    if ($authentication_wp == $authentication || $is_test) {
                        foreach ($json['items'] as $_item) {
                            if ($json['deploy_record'] == 'stock') {
                                if (isset($_item['deploy_action']) && $_item['deploy_action'] == 'delete') {
                                    $import_result = self::delete_stock($_item);
                                } else {
                                    $import_result = self::import_stock($_item);
                                }
                                $item_log = '';
                                if (!$import_result['success']) {
                                    $import_result_status = 'failed';
                                    $log = __('Unabled to create or update stock', 'sirrus_cloud');
                                    if (!empty($import_result['log'])) {
                                        $log = $log."\n".$import_result['log'];
                                        $item_log = $import_result['log'];
                                    }
                                } else {
                                    $import_result_status = 'success';
                                }
                                $result['items'][] = array('record_id' => $_item['record_id'], 'result' => $import_result_status, 'log' => $item_log);
                            }
                            if ($json['deploy_record'] == 'artist') {
                                if (isset($_item['deploy_action']) && $_item['deploy_action'] == 'delete') {
                                    $import_result = self::delete_artist($_item);
                                } else {
                                    $import_result = self::import_artist($_item);
                                }
                                $item_log = '';
                                if (!$import_result['success']) {
                                    $import_result_status = 'failed';
                                    $log = __('Unabled to create or update artist', 'sirrus_cloud');
                                    if (!empty($import_result['log'])) {
                                        $log = $log."\n".$import_result['log'];
                                        $item_log = $import_result['log'];
                                    }
                                } else {
                                    $import_result_status = 'success';
                                }
                                $result['items'][] = array('record_id' => $_item['record_id'], 'result' => $import_result_status, 'log' => $item_log);
                            }
                            if ($json['deploy_record'] == 'group') {
                                if (isset($_item['deploy_action']) && $_item['deploy_action'] == 'delete') {
                                    $import_result = self::delete_group($_item);
                                } else {
                                    $import_result = self::import_group($_item);
                                }
                                $item_log = '';
                                if (!$import_result['success']) {
                                    $import_result_status = 'failed';
                                    $log = __('Unabled to create or update group', 'sirrus_cloud');
                                    if (!empty($import_result['log'])) {
                                        $log = $log."\n".$import_result['log'];
                                        $item_log = $import_result['log'];
                                    }
                                } else {
                                    $import_result_status = 'success';
                                }
                                $result['items'][] = array('record_id' => $_item['record_id'], 'result' => $import_result_status, 'log' => $item_log);
                            }
                        }
                        $result['result'] = 'success';
                    } else {
                        $result['result'] = 'failed';
                    }
                    $result['log'] = $log;
                }
            } else {
                $result = array('result' => 'failed', 'log' => 'empty request');
            }
            if (!$is_test) {
                wp_send_json($result);
            } else {
                wp_send_json($result);
            }
            wp_die();
        }

        public static function import_stock($item = array())
        {
            $import_artist_fail = array();
            if (!empty($item['artist'])) {
                $import_result = self::import_artist($item['artist']);
                if (!$import_result) {
                    $import_artist_fail[] = __('Cannot import artist ID: ', 'sirrus_cloud').$item['artist']['record_id'];
                }
                if (!empty($import_artist_fail)) {
                    return array('success' => false, 'log' => implode("\n", $import_artist_fail));
                }
            }
            if ($post_ID = self::is_post_exists('stock:'.$item['record_id'])) {
                $result = self::update_post('stock', self::$settings['import_stock_link_post_type'], $item, self::$settings['sirrus_cloud_mapping_stock'], $post_ID);
                return array('success' => $result, 'log' => '');
            } else {
                $result = self::update_post('stock', self::$settings['import_stock_link_post_type'], $item, self::$settings['sirrus_cloud_mapping_stock']);
                return array('success' => $result, 'log' => '');
            }
        }
        public static function import_artist($item = array())
        {
            if ($post_ID = self::is_post_exists('artist:'.$item['record_id'])) {
                $result = self::update_post('artist', self::$settings['import_artist_link_post_type'], $item, self::$settings['sirrus_cloud_mapping_artist'], $post_ID);
                return array('success' => $result, 'log' => '');
            } else {
                $result = self::update_post('artist', self::$settings['import_artist_link_post_type'], $item, self::$settings['sirrus_cloud_mapping_artist']);
                return array('success' => $result, 'log' => '');
            }
        }
        public static function import_group($item = array())
        {
            $import_stock_fail = array();
            
            if (!empty($item['stocks'])) {
                foreach ($item['stocks'] as $_stock) {
                    $import_result = self::import_stock($_stock);
                    if (!$import_result) {
                        $import_stock_fail[] = __('Cannot import stock ID: ', 'sirrus_cloud').$_stock['stockID'];
                    }
                }
                if (!empty($import_stock_fail)) {
                    return array('success' => false, 'log' => implode("\n", $import_stock_fail));
                }
            }
            

            $post_type = self::$settings['import_group_link_post_type'];

            if (self::$settings['import_group_link_custom'] && $key = array_search($item['group_type_name'], self::$settings['import_group_link_custom'])) {
                $post_type = self::$settings['import_group_link_post_type_custom'][$key] ?? self::$settings['import_group_link_post_type'];
            }

            if ($post_ID = self::is_post_exists('group:'.$item['id'])) {
                $result = self::update_post('group', $post_type, $item, self::$settings['sirrus_cloud_mapping_group'], $post_ID);
                return array('success' => $result, 'log' => '');
            } else {
                $result = self::update_post('group', $post_type, $item, self::$settings['sirrus_cloud_mapping_group']);
                return array('success' => $result, 'log' => '');
            }
        }
        public static function delete_stock($item = array())
        {
            if ($post_ID = self::is_post_exists('stock:'.$item['record_id'])) {
                self::delete_item($post_ID);
            }
            return array('success' => true, 'log' => '');
        }
        public static function delete_artist($item = array())
        {
            if ($post_ID = self::is_post_exists('artist:'.$item['record_id'])) {
                self::delete_item($post_ID);
            }
            return array('success' => true, 'log' => '');
        }
        public static function delete_group($item = array())
        {
            if ($post_ID = self::is_post_exists('group:'.$item['record_id'])) {
                self::delete_item($post_ID);
            }
            return array('success' => true, 'log' => '');
        }

        public static function delete_item($id)
        {
            if (preg_match('/^term_id_(.*)$/', $id, $termmatch)) {
                $term = get_term($termmatch[1]);
                wp_delete_term($termmatch[1], $term->taxonomy);
            } else {
                wp_delete_post($id, true);
            }
        }

        public static function get_acf_fields($name)
        {
            global $wpdb;
            if (isset(self::$acf_fields[$name])) {
                return self::$acf_fields[$name];
            }
            $sql = "SELECT post_name, post_content FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_excerpt = '".$name."'";
            self::$acf_fields[$name] = $wpdb->get_results($sql);
            return self::$acf_fields[$name];
        }


        public static function get_acf_groups()
        {
            global $wpdb;
            if (!is_null(self::$acf_groups)) {
                return self::$acf_groups;
            }
            $sql = "SELECT ID, post_name, post_content, post_excerpt FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_content LIKE '%\"type\";s:5:\"group\"%'";
            self::$acf_groups = $wpdb->get_results($sql);
            return self::$acf_groups;
        }


        public static function process_meta($data, $field, $post_id = false)
        {
            $return = $data;
           
            $acf_contents = self::get_acf_fields($field);
            $field_id = null;


            if (count($acf_contents) === 0 && $field != 'post_title') {
                $group_contents = self::get_acf_groups();
                foreach ($group_contents as $group_content) {
                    $object = get_field_object($group_content->post_name);
                    foreach ($object['sub_fields'] as $sub_field) {
                        $join = $object['name'] . '_' . $sub_field['name'];
                        if ($field === $join) {
                            $group_obj = new stdClass();
                            $group_obj->post_name = $join;
                            $group_obj->post_content = serialize($sub_field);
                            $acf_contents = [
                                $group_obj
                            ];
                        }
                    }
                }
            }
            foreach ($acf_contents as $acf_content) {
                //acf field
                $acfdata = unserialize($acf_content->post_content);
                if ($acfdata['type'] === 'accordion') {
                    //TODO ignore accordion
                    continue;
                }
                if (!is_null($field_id)) {
                    continue;
                }
                $field_id = $acf_content->post_name;
                switch ($acfdata['type']) {
                    case 'date_picker':
                        $date = new Datetime($data);
                        $return = $date ? $date->format('Ymd') : $data;
                    break;
                    case 'date_time_picker':
                        $date = new Datetime($data);
                        $return = $date ? $date->format('Y-m-d H:i:s') : $data;
                    break;
                    case 'gallery':
                        if (!is_array($data)) {
                            $data = [$data];
                        }
                        
                        $return = [];
                        foreach ($data as $url) {
                            try {
                                $return[] = self::upload_image($url);
                            } catch (\Exception $e) {
                            }
                        }
                    break;
                    case 'flexible_content':
                        //TODO
                        $return = get_field($field, $post_id, true);
                        if ($field == 'content_sections') {
                            $artworks = [];
                            $attachments = [];

                            foreach ($data as $d) {
                                $stock_post_ID = self::is_post_exists('stock:'.$d['stockID']);
                                if ($stock_post_ID) {
                                    $artworks[] = [
                                    'artwork' =>
                                        get_post($stock_post_ID)
                                ];
                                    $attachment = get_post_thumbnail_id($stock_post_ID);
                                    if (!empty($attachment)) {
                                        $attachments[] = acf_get_attachment($attachment);
                                    }
                                    $gallery = get_field('artwork_gallery', $stock_post_ID);
                                }
                            }

                            $found = false;
                            $gfound = false;
                            foreach ($return as &$r) {
                                if (!$found && $r['acf_fc_layout'] == 'featured_work') {
                                    $r['artworks'] = $artworks;
                                    $found = true;
                                }
                                if (!$gfound && $r['acf_fc_layout'] == 'gallery' && count($attachments) > 0) {
                                    $r['gallery'] = $attachments;
                                    $gfound = true;
                                }
                            }

                            if (!$gfound && count($attachments) > 0) {
                                $return[] = [
                                'acf_fc_layout' => 'gallery',
                                'heading' => 'Gallery',
                                'gallery' =>
                                    $attachments
                                
                            ];
                            }

                        
                            if (!$found) {
                                $return[] = [
                                'acf_fc_layout' => 'featured_work',
                                'heading' => 'Stock',
                                'artworks' =>
                                    $artworks
                                
                            ];
                            }
                        }
                        
                    break;
                    case 'taxonomy':
                        $label_term = term_exists($data, $acfdata['taxonomy']);
                        if ($label_term) {
                            $return = [$label_term['term_id']];
                        }
                    break;
                    default:
                    $return = $data;
                    break;

                }

                if ($post_id && !is_null($field_id)) {
                    //if($field == 'content_sections'){
                    //    var_dump($post_id);
                    //    var_dump($acfdata['type']);
                    //exit;
                    //}
                    update_field($field_id, $return, $post_id);
                    return null;
                }
            }

            return $data;
        }

        public static function upload_image($url, $post_id = 0)
        {
            $args = [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key'     => 'aimp_image_url',
                        'value'   => $url,
                        'compare' => '=',
                    ],
                ],
            ];
            $attachments = get_posts($args);
            $existing = false;
            if (is_null($attachments) || count($attachments) === 0) {
                $media = media_sideload_image($url, $post_id, null, 'id');
                if (!is_wp_error($media)) {
                    update_post_meta($media, 'aimp_image_url', $url);
                } else {
                    return false;
                }
            } else {
                $existing = true;
                $media = $attachments[0]->ID;
            }
            return $media;
        }


        public static function update_post($type, $post_type = '', $data = array(), $schema = array(), $updateID = '')
        {
            if (isset(self::$$type[$data['record_id']])) {
                return;
            }
            if (preg_match('/^taxonomy~(.*)/', $post_type, $matches)) {
                $termid = $matches[1];
                $title = (!empty($data['display_name']))?$data['display_name']:$data['record_id'];

                if (preg_match('/^term_id_(.*)$/', $updateID, $termmatch)) {
                    $id = $termmatch[1];
                } elseif (!empty($title)) {
                    $label_term = term_exists($title, $termid);
        
                    if (empty($label_term)) {
                        $label_term = wp_insert_term($title, $termid);
                    }
                    $id = $label_term['term_id'];
                } else {
                    return false;
                }

                if (isset($id) && !empty($title)) {
                    $update = wp_update_term($id, $termid, [
                        'name' => $title,
                        'slug' => sanitize_title($title)
                    ]);
                }
                
                $meta_field = [];
                $meta_field['aimp_import_uid'] = $type.':'.$data['record_id'];

                $schema = json_decode($schema, true);
                foreach ($schema as $source => $wp) {
                    if (preg_match('/^taxonomyfield~'.$termid.'~(.*)/', $wp, $matches)) {
                        update_term_meta($id, $matches[1], $data[$source]);
                    }
                }

                if (!empty($meta_field)) {
                    foreach ($meta_field as $key => $value) {
                        update_term_meta($id, $key, $value);
                    }
                }

                self::update_relationships($id, $type, $post_type, $data);

                return true;
            } else {
                $schema = json_decode($schema, true);
                $standard_field = self::wp_standard_field();
                $standard_field->post_type = $post_type;
                $standard_field_key = get_object_vars($standard_field);
                $is_test = isset($_GET['debug']);
                $meta_field = array();
            
                if (!empty($schema)) {
                    foreach ($schema as $source => $wp) {
                        if (array_key_exists($wp, $standard_field_key) && isset($data[$source])) {
                            $standard_field->{$wp} = $data[$source];
                        }
                    }
                }

                if ($type == 'stock') {
                    if (empty($standard_field->post_title)) {
                        $standard_field->post_title = (!empty($data['stock_title']))?$data['stock_title']:$data['stockID'];
                    }
                    if (empty($standard_field->post_content)) {
                        $standard_field->post_content = (!empty($data['stock_title']))?$data['stock_title']:$data['stockID'];
                    }
                } elseif ($type == 'artist') {
                    if (empty($standard_field->post_title)) {
                        $standard_field->post_title = (!empty($data['display_name']))?$data['display_name']:$data['record_id'];
                    }
                    if (empty($standard_field->post_content)) {
                        $standard_field->post_content = (!empty($data['display_name']))?$data['display_name']:$data['record_id'];
                    }
                } else {
                    $stock_post_IDs = array();
                    if (!empty($data['stocks'])) {
                        foreach ($data['stocks'] as $_stock) {
                            $stock_post_ID = self::is_post_exists('stock:'.$_stock['stockID']);
                            if ($stock_post_ID) {
                                $stock_post_IDs[] = $stock_post_ID;
                            }
                        }
                    }
                    
                    if ($stock_post_IDs) {
                        $meta_field['linked_to_stocks'] = implode(',', $stock_post_IDs);
                    }
                    if (empty($standard_field->post_title)) {
                        $standard_field->post_title = (!empty($data['group_name']))?$data['group_name']:$data['record_id'];
                    }
                    if (empty($standard_field->post_title)) {
                        $standard_field->post_content = (!empty($data['group_name']))?$data['group_name']:$data['record_id'];
                    }
                }

                $standard_field->post_status = 'publish';
                if (empty($updateID)) {
                    $id = wp_insert_post($standard_field);
                } else {
                    $id = $updateID;
                    $standard_field->ID = $updateID;
                    $return = wp_update_post($standard_field, true);
                }

                if (!empty($schema)) {
                    $ignores = [];
                    foreach ($schema as $source => $wp) {
                        if (in_array($source, $ignores)) {
                            continue;
                        }
                        if (in_array($wp, $standard_field_key) && isset($data[$source])) {
                            //$standard_field[$wp] = $data[$source];
                        } elseif (isset($data[$source])) {
                            $meta = $data[$source];
                            $keys = array_keys($schema, $wp);
                            if (count($keys) > 1) {
                                foreach ($keys as $key) {
                                    if ($key == $source) {
                                        continue;
                                    }
                                    $ignores[] = $key;
                                    if (isset($data[$key])) {
                                        if (!is_array($meta)) {
                                            $meta = [$meta];
                                        }
                                        if (!is_array($data[$key])) {
                                            $data[$key] = [$data[$key]];
                                        }
                                        $meta = array_merge($meta, $data[$key]);
                                    }
                                }
                            }
                            if (!array_key_exists($wp, $standard_field_key)) {
                                $meta_field[$wp] = self::process_meta($meta, $wp, $id);
                            }
                        }
                    }
                }

                self::update_relationships($id, $type, $post_type, $data);

                if (!empty($id)) {
                    if (!empty($meta_field['post_thumbnails'])) {
                        self::set_post_image($meta_field['post_thumbnails'], $id, $standard_field->post_title);
                    }

                    $meta_field['aimp_import_uid'] = $type.':'.$data['record_id'];

                    if (!empty($meta_field)) {
                        foreach ($meta_field as $key => $value) {
                            if (preg_match('/^taxonomy~(.*)/', $key, $matches)) {
                                if (empty($value)) {
                                    wp_set_post_terms($id, [], $matches[1], false);
                                    continue;
                                }
                                $label_term = term_exists($value, $matches[1]);
        
                                if (empty($label_term)) {
                                    $label_term = wp_insert_term($value, $matches[1]);
                                }
                                wp_set_post_terms($id, $label_term['term_id'], $matches[1], false);
                            } elseif (!is_null($value)) {
                                update_post_meta($id, $key, $value);
                            }
                        }
                    }
                    self::$$type[$data['record_id']] = $id;
                    return true;
                } else {
                    return false;
                }
            }
        }
        public static function update_relationships($id, $type, $post_type, $data)
        {
            if ($type === 'stock' && isset($data['artist'])) {
                if ($artist = self::is_post_exists('artist:'.$data['artist']['id'])) {
                    $schema = self::$settings['sirrus_cloud_mapping_artist'];
                    $schema = json_decode($schema, true);
                    if (preg_match('/^term_id_(.*)$/', $artist, $termmatch)) {
                        $term = get_term($termmatch[1]);
                        wp_set_post_terms($id, $term->term_id, $term->taxonomy, false);
                        foreach ($schema as $source => $wp) {
                            if (preg_match('/^taxonomyfield~'.$term->taxonomy.'~(.*)/', $wp, $matches) && isset($data['artist'][$source])) {
                                update_term_meta($termmatch[1], $matches[1], $data['artist'][$source]);
                            }
                        }
                    } else {
                        if (function_exists('update_field')) {
                            update_field('work_artist', [(int) $artist], $id);
                        }
                    }
                }
            }

            if ($post_type === 'exhibitions' && isset($data['stocks'])) {
                $works = [];
                $artists = [];
                $artist_is_taxonomy = false;
                foreach ($data['stocks'] as $stock) {
                    if ($work = self::is_post_exists('stock:'.$stock['stockID'])) {
                        $works[] = (int) $work;
                    }
                    if (isset($stock['artist']) && $artist = self::is_post_exists('artist:'.$stock['artist']['id'])) {
                        if (preg_match('/^term_id_(.*)$/', $artist, $termmatch)) {
                            $term = get_term($termmatch[1]);
                            $artists[] = $term->term_id;
                            $artist_is_taxonomy = $term->taxonomy;
                        } else {
                            $artists[] = (int) $artist;
                        }
                    }
                }
                $artists = array_unique($artists);

                if (function_exists('update_field')) {
                    update_field('exhibitions_works', array_unique($works), $id);
                    $flex = get_field('content_sections', $id);
                    if (!$flex) {
                        $flex = [];
                    }
                    $key = count($flex);
                    foreach ($flex as $k => $f) {
                        if ($f['acf_fc_layout'] == 'featured_work') {
                            $key = $k;
                        }
                    }
                    if (!isset($flex[$key])) {
                        $flex[$key] = [
                            'acf_fc_layout' => 'featured_work'
                        ];
                    }
                    $flex[$key]['artworks'] = [];

                    foreach ($works as $work) {
                        $flex[$key]['artworks'][] = ['artwork'=>$work];
                    }
                    update_field('content_sections', $flex, $id);
                    
                    if (!$artist_is_taxonomy) {
                        update_field('exhibition_artist', array_unique($artists), $id);
                    }
                }

                if ($artist_is_taxonomy) {
                    wp_set_post_terms($id, $artists, $artist_is_taxonomy, false);
                    if (function_exists('update_field')) {
                        update_field('artist', $artists, $id);
                    }
                }
            }
        }

        public static function is_post_exists($uid = '')
        {
            global $wpdb;
            if (!empty($uid)) {
                $sql = "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE 'aimp_import_uid' AND meta_value LIKE '".$uid."' LIMIT 0,1";
                $post_id = $wpdb->get_var($sql);
                if (!empty($post_id)) {
                    return $post_id;
                }
            }
            if (!empty($uid)) {
                $sql = "SELECT term_id FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'aimp_import_uid' AND meta_value LIKE '".$uid."' LIMIT 0,1";
                $post_id = $wpdb->get_var($sql);
                if (!empty($post_id)) {
                    return 'term_id_'.$post_id;
                }
            }
            return false;
        }


        public static function set_post_image($url, $post_id, $post_title)
        {
            $args = [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key'     => 'aimp_image_url',
                        'value'   => $url,
                        'compare' => '=',
                    ],
                ],
            ];
            $attachments = get_posts($args);

            if (is_null($attachments) || count($attachments) === 0) {
                $args = array(
                    'posts_per_page' => 1,
                    'post_type' => 'attachment',
                    'name'      => trim($post_title),
                );
                $attachments = get_posts($args);
                if (!is_null($attachments) && count($attachments) > 0) {
                    update_post_meta($attachments[0]->ID, 'aimp_image_url', $url);
                }
            }

            $existing = false;
            if (is_null($attachments) || count($attachments) === 0) {
                $media = media_sideload_image($url, $post_id, $post_title, 'id');
                if (!is_wp_error($media)) {
                    update_post_meta($media, 'aimp_image_url', $url);
                } else {
                    return;
                }
            } else {
                $existing = true;
                $media = $attachments[0]->ID;
            }

            set_post_thumbnail($post_id, $media);
        }
        
        public static function wp_get_attachment_by_post_name($post_name)
        {
            $args = array(
                'posts_per_page' => 1,
                'post_type' => 'attachment',
                'name'      => trim($post_name),
            );
            $get_attachment = new WP_Query($args);
            if (! $get_attachment || ! isset($get_attachment->posts, $get_attachment->posts[0])) {
                return false;
            }
            return $get_attachment->posts[0];
        }

        public function fetch_import_scheme()
        {
            if (!empty(self::$settings['field_url'])) {
                $options = array();
                $auth = md5(self::$settings['import_username'].''.self::$settings['import_password']);

                $response = wp_remote_post(self::$settings['field_url'].'?auth='.$auth, $options);

                if (is_wp_error($response) || empty($response['body'])) {
                } else {
                    $body = @json_decode($response['body'], true);
                    if (!empty($body)) {
                        $upload_dir = self::get_upload_dir();
                        add_settings_error('sirrus_cloud_messages', 'sirrus_cloud_messages', __('Structure file updated', 'sirrus_cloud'), 'updated');
                        file_put_contents($upload_dir.'/import-scheme.json', json_encode($body));

                        return true;
                    }
                }
            }
            return false;
        }

        public function admin_assets()
        {
            $version = '1.1';

            wp_enqueue_script('sirrus_cloud_admin_script', plugins_url('admin/js/app.js', __FILE__), array('jquery'), $version);
            wp_enqueue_script('sirrus_cloud_admin_select2_script', plugins_url('admin/js/select2.min.js', __FILE__), array('jquery'), $version);

            wp_enqueue_style('sirrus_cloud_admin_style', plugins_url('admin/css/app.css', __FILE__));
            wp_enqueue_style('sirrus_cloud_admin_select2_style', plugins_url('admin/css/select2.min.css', __FILE__));
        }

        public function options_pages()
        {
            add_menu_page(
                'Connector',
                'Connector',
                'manage_options',
                'sirrus_cloud',
                array( $this, 'options_page_html')
            );

            add_submenu_page('sirrus_cloud', 'Data Mapping', 'Data Mapping', 'manage_options', 'sirrus_cloud-mapping', array( $this, 'options_mapping_page_html'));
        }

        public function options_page_html()
        {
            if (! current_user_can('manage_options')) {
                return;
            }
            $fields = self::settings_fields(true);
            

            if (isset($_POST['settings-updated']) && $_POST['settings-updated'] = 1) {
                foreach ($fields as $field) {
                    if (isset($_POST[$field])) {
                        if (is_string($_POST[$field])) {
                            $data = strip_tags(
                                stripslashes(
                                    sanitize_text_field(
                                        filter_input(INPUT_POST, $field)
                                    )
                                )
                            );
                            update_option($field, $data);
                        } else {
                            $data = filter_input_array(INPUT_POST, array($field    => array('filter'    => FILTER_SANITIZE_STRING,
                            'flags'     => FILTER_REQUIRE_ARRAY
                           )));
                            update_option($field, $data[$field]);
                        }
                    }
                }

                add_settings_error('sirrus_cloud_messages', 'sirrus_cloud_messages', __('Settings Saved', 'sirrus_cloud'), 'updated');

                /* reload settings */
                self::fetch_settings();
                self::fetch_import_scheme();
            }

            $fields_value = array();

            foreach ($fields as $field) {
                $value = get_option($field);
                $fields_value[$field] = $value;
            }

            if (!empty($fields_value['import_stock_link_post_type'])) {
                $value = $fields_value['import_stock_link_post_type'];
                $fields_value['import_stock_link_post_type_selected'] = '<option value="'.$value.'">'.$value.'</option>';
            }
            if (!empty($fields_value['import_artist_link_post_type'])) {
                $value = $fields_value['import_artist_link_post_type'];
                $fields_value['import_artist_link_post_type_selected'] = '<option value="'.$value.'">'.$value.'</option>';
            }
            if (!empty($fields_value['import_group_link_post_type'])) {
                $value = $fields_value['import_group_link_post_type'];
                $fields_value['import_group_link_post_type_selected'] = '<option value="'.$value.'">'.$value.'</option>';
            }

            if (empty($fields_value['import_group_link_custom'])) {
                $fields_value['import_group_link_custom'] = [];
            }
            if (empty($fields_value['import_group_link_post_type_custom'])) {
                $fields_value['import_group_link_post_type_custom'] = [];
            }
            if (empty($fields_value['additional_groups'])) {
                $fields_value['additional_groups'] = 0;
            } else {
                for ($x = 1; $x <= $fields_value['additional_groups'] ; $x++) {
                    if (!isset($fields_value['import_group_link_custom'][$x])) {
                        $fields_value['import_group_link_custom'][$x] = '';
                    }
                    if (!isset($fields_value['import_group_link_post_type_custom'][$x])) {
                        $fields_value['import_group_link_post_type_custom'][$x] = '';
                    }
                }
            }

            settings_errors('sirrus_cloud_messages');

            require_once(self::$path.'view/form.php');
        }

        public function options_mapping_page_html()
        {
            if (isset($_POST['settings-stock']) && $_POST['settings-stock'] = 1) {
                $sirrus_cloud_field_stock = $_POST['sirrus_cloud_field_stock'];
                $wp_field_stock = $_POST['wp_field_stock'];
                $save_data = array();
                foreach ($sirrus_cloud_field_stock as $_index => $_field) {
                    if (!empty($_field)) {
                        $save_data[$_field] = $wp_field_stock[$_index];
                    }
                }
                update_option('sirrus_cloud_mapping_stock', json_encode($save_data));
            }
            if (isset($_POST['settings-artist']) && $_POST['settings-artist'] = 1) {
                $sirrus_cloud_field_artist = $_POST['sirrus_cloud_field_artist'];
                $wp_field_artist = $_POST['wp_field_artist'];
                $save_data = array();
                foreach ($sirrus_cloud_field_artist as $_index => $_field) {
                    if (!empty($_field)) {
                        $save_data[$_field] = $wp_field_artist[$_index];
                    }
                }
                update_option('sirrus_cloud_mapping_artist', json_encode($save_data));
            }
            if (isset($_POST['settings-group']) && $_POST['settings-group'] = 1) {
                $sirrus_cloud_field_group = $_POST['sirrus_cloud_field_group'];
                $wp_field_group = $_POST['wp_field_group'];
                $save_data = array();
                foreach ($sirrus_cloud_field_group as $_index => $_field) {
                    if (!empty($_field)) {
                        $save_data[$_field] = $wp_field_group[$_index];
                    }
                }
                update_option('sirrus_cloud_mapping_group', json_encode($save_data));
            }
            $sirrus_cloud_mapping_stock = get_option('sirrus_cloud_mapping_stock');
            if (!empty($sirrus_cloud_mapping_stock)) {
                $sirrus_cloud_mapping_stock = json_decode($sirrus_cloud_mapping_stock, true);
            }
            $sirrus_cloud_mapping_artist = get_option('sirrus_cloud_mapping_artist');
            if (!empty($sirrus_cloud_mapping_artist)) {
                $sirrus_cloud_mapping_artist = json_decode($sirrus_cloud_mapping_artist, true);
            }
            $sirrus_cloud_mapping_group = get_option('sirrus_cloud_mapping_group');
            if (!empty($sirrus_cloud_mapping_group)) {
                $sirrus_cloud_mapping_group = json_decode($sirrus_cloud_mapping_group, true);
            }


            require_once(self::$path.'view/mapping.php');
        }

        public function options_log_page_html()
        {
            require_once(self::$path.'view/mapping.php');
        }
    }
    $Sirrus_Cloud = Sirrus_Cloud::getInstance();
}
