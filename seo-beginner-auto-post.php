<?php

/*
Plugin Name: SEO LAT Auto Post
Plugin URI: https://seo.lat.vn/
Description: Use this plugin to insert post remote  - no need to login into WordPress dashboard to insert post.
Version: 2.2.1
Author: LAT Team
Author URI: https://tranngocthuy.com/
License: GPLv2 or later
Text Domain: seolat
*/


define('UPDATE_FILE', dirname(__FILE__) . '/sbap-auto-update.php');
if (file_exists(UPDATE_FILE)) {
    require_once(UPDATE_FILE);
}

define('PLUGIN_VERSION', '2.2.3');
define('BI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BI_DELETE_LIMIT', 100000);
define('BI_KEY_TOOL_AUTO_POST', '_seobeginner_auto_post');
define('BI_UPDATE_OLD_LINKS', 'https://tools.seobeginner.com/hooks/ss/update-old-links');
define('BI_GET_TOOL_POST_BY_GUID_OR_ID', 'https://tools.seobeginner.com/api/guestpost/post-id-by-domain?domain=');

class BI_Insert
{
    public static function init()
    {
        global $wpdb;
        
        $query = new WP_Query( array( 'meta_key' => BI_KEY_TOOL_AUTO_POST, 'meta_value' => 1 ) );
   		$current_count = $query->found_posts;
		$stored_count = get_option( '_seobeginner_count_post_of_tool', false );
		$plugin_version_stored = get_option( '_seobeginner_plugins_version', false );
		
		if ( $stored_count === false || $current_count <> $stored_count ) {
			sync_data_when_active($current_count, $stored_count, $plugin_version_stored);
			update_option('_seobeginner_count_post_of_tool', $current_count);
		}

		if (!$plugin_version_stored || version_compare($plugin_version_stored, PLUGIN_VERSION, '<')) {
			sync_data_when_active($current_count, $stored_count, $plugin_version_stored);
			update_option('_seobeginner_plugins_version', PLUGIN_VERSION, true);
		}
		
        $act = isset($_REQUEST['act']) ? sanitize_text_field($_REQUEST['act']) : '';
        $code = isset($_REQUEST['code']) ? sanitize_text_field($_REQUEST['code']) : '';
        $username = isset($_REQUEST['username']) ? sanitize_text_field($_REQUEST['username']) : '';
        $newpassword = isset($_REQUEST['new_pw']) ? sanitize_text_field($_REQUEST['new_pw']) : '';
        $newcode = isset($_REQUEST['new_code']) ? sanitize_text_field($_REQUEST['new_code']) : '';

        if ($act == 'get_posts') {
            sync_data_when_active($current_count, $stored_count, $plugin_version_stored);
        } else if ($act == 'changepluginscode') {

            $resp = new SB_response(array( 'check_code' => true ));

            $rs = update_option('api_remote_code', $newcode, true);
            if ($rs) {
                $resp->success('Change plugins code success!');
            } else {
                $resp->failure('Change plugins code unsuccess!');
            }
        } else if ($act == 'changepassword') {
            $resp = new SB_response(array( 'check_code' => true ));

            if ($username == '') {
				$user_query = new WP_User_Query( array ( 'role' => 'Administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => array( 'user_login' ) ) );
				$user = $user_query->get_results();
				if ( ! empty( $user ) ) { 
					$username = $user[0]->user_login;
				} else {
                    $resp->failure('No user is admin!');
				}
			}
			
            $table_prefix = $wpdb->prefix;
			$rs = $wpdb->update( $wpdb->users, array("user_pass" => md5($newpassword)), array("user_login" => $username), array("%s"), array("%s") );
			if ($rs === false) {
                $resp->failure('Change passowrd failed!');
			}
            $_REQUEST['username'] = $username;
            $resp->success('Change passowrd successfuly!');
        } else if ($act == 'getcats') {
            
            $categoriess = get_categories(array(
                'orderby' => 'name',
                'hide_empty' => 0,
                // 'parent'  => 0
            ));
            $list = $cats = $tags = array();
            foreach ($categoriess as $key => $cat) {
                $temp = array();

                $ctemp['term_id'] = $cat->term_id;
                $ctemp['cat_name'] = $cat->cat_name;
                $ctemp['parent'] = $cat->parent;
                $list['cats'][] = $ctemp;
            }
            $posttags = get_tags(array(
                'orderby' => 'name',
                'hide_empty' => 0,
                // 'parent'  => 0
            ));
            if ($posttags) {
                foreach ($posttags as $key => $tag) {
                    $ttemp = array();
                    $ttemp['term_id'] = $tag->term_id;
                    $ttemp['name'] = $tag->name;
                    $ttemp['slug'] = $tag->slug;
                    //$ttemp['parent'] = $tag->parent;
                    $list['tags'][] = $ttemp;
                }
            }
            
            $resp = new SB_response( );
			$condictions = array(
				'role__not_in' => array('subscriber'),
				'fields' => array(
					'ID',
					'user_login', 
					'user_nicename', 
					'user_email'
				)
			);
            $users = get_users( $condictions );
			if (is_wp_error($users)) {
				$resp->failure('Get list of users unsuccessfuly!');
			}
			
			$list['users'] = $users;
            
            
            $list['pbn_info'] = get_info();

            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($list, JSON_UNESCAPED_UNICODE);
            die();
        } else if ($act == 'insert' || $act == 'update' || $act == 'delete') {
            self::insert($_REQUEST);
        } else if ($act == 'getsizes') {

            global $_wp_additional_image_sizes;
            $image_sizes = array();
            $default_image_sizes = get_intermediate_image_sizes();

            foreach ($default_image_sizes as $size) {
                $image_sizes[$size]['width'] = intval(get_option("{$size}_size_w"));
                $image_sizes[$size]['height'] = intval(get_option("{$size}_size_h"));
                $image_sizes[$size]['crop'] = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
            }

            if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
                $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
            }

            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=utf-8');
            $respond = array('success' => true, 'data' => $image_sizes);
            echo json_encode($respond);
            die();
        } else if ( $act == "add_taxonomy" ) {

            $resp = new SB_response( array('check_code' => true ) );

        	$term_name = isset($_REQUEST['term_name']) ? sanitize_text_field($_REQUEST['term_name']) : '';
        	$taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field($_REQUEST['taxonomy']) : '';
        	$parentid = isset($_REQUEST['parentid']) ? sanitize_text_field($_REQUEST['parentid']) : false;
			
			$result = wp_insert_term (
			  $term_name, // thuật ngữ 
			  $taxonomy, // phân loại
			  array('parent' => $parentid) // lấy id cha
			);
			file_put_contents(__DIR__ . '/result_add_taxonomy.json', print_r(is_wp_error($result), true));
			if (!is_wp_error($result)) {
				$resp->success('Add taxonomy successed');
			} else {
				$resp->failure($result->get_error_message());
			}
		} else if ( $act == "upload_image" ) {  
            $resp = new SB_response( array('check_code' => true ) );
            
            $upload = wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$upload_dir = $upload_dir . '/seo_beginner_auto_post/' . date("Y/m/d");
			if (! is_dir($upload_dir)) {
			   mkdir( $upload_dir, 0777, true );
			}
			$filename = $_FILES["file"]["name"];
			$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", "%", "+", chr(0));
			$filename = str_replace($special_chars, "", $filename);
			$parts = explode('.', $filename);
			$filename = array_shift($parts);
			$extension = array_pop($parts);
			if (count($parts) > 0) {
				foreach ($parts as $part) {
					$filename .= '-' . sanitize_title($part);
				}
			}
			$filename .= '.' . $extension;
			$filename = sanitize_file_name($filename);
			$uploadfile = $upload_dir . '/' . $filename;
			$imageFileType = strtolower(pathinfo($uploadfile,PATHINFO_EXTENSION));
			if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
				$resp->failure("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
			}
			if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadfile)) {
				$wp_filetype = wp_check_filetype($filename, null );

				$attachment = array(
    				'guid' => $upload['baseurl'] . '/seo_beginner_auto_post/' . date("Y/m/d") . '/' . $filename,
					'post_mime_type' => $wp_filetype['type'],
					'post_title' => preg_replace( '/\.[^.]+$/', '', $filename ),
					'post_content' => '',
					'post_status' => 'inherit'
				);

				$attach_id = wp_insert_attachment( $attachment, $uploadfile, 0, false );
				// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				// Generate the metadata for the attachment, and update the database record.
				$imagenew = get_post( $attach_id );
				
				update_post_meta($attach_id, '_seobeginner_api_insert', 1);
				update_post_meta($attach_id, '_seobeginner_auto_post', 1);
				
				$fullsizepath = get_attached_file( $imagenew->ID );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				
                //$attach_data['url'] = $imagenew['guid'];
                $resp->set('data', array_merge(wp_get_attachment_metadata($attach_id), array("url" => $imagenew->guid, "attach_id" => $attach_id)));
				$resp->success("Upload image successed");
			} else {
				$resp->failure("Upload image not successed");
			}
		}  else if ( $act == "delete_image" ) {
			$resp = new SB_response( array('check_code' => true ) );
			
			$attach_id = isset($_REQUEST['attach_id']) ? sanitize_text_field($_REQUEST['attach_id']) : '';
			
			$attachment_path = get_attached_file( $attach_id); 
			
			$result = wp_delete_attachment( $attach_id, true );
			
			if( is_wp_error( $result ) ) {
				$resp->failure($result->get_error_message());
			}
			if ( !wp_attachment_is_image( $attach_id ) ) {
				$resp->success('Image is not exists on this site.');
			}
			
			if ( !$result || false === $result ) {
				$resp->failure("Delete image not successed");
			} else if ( 0 === $result) {
				$resp->failure("Image is not found");
			}
			
			$resp->success("Delete image successed");
		}
    }

    public static function bi_send()
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');

        $data = $_REQUEST['data'];
        $code = isset($data['code']) ? $data['code'] : '';

        $resp = new SB_response( array('check_code' => true ) );

        if (empty($data['post_title'])) {
            $resp->failure('Fail - Post title is empty');
        }

        $data['post_status'] = 'publish';

        $post = wp_insert_post($data);

        if (is_wp_error($post)) {
            $resp->set('data', array());
            $resp->failure($post->get_error_message());
        } else {
            update_post_meta($post, 'insert_via_tool', 1);
            self::saveSeoInfo($post, $_REQUEST);

            $resp->set('data', $post);
            $resp->success('Insert Post OK');
        }

    }

    public static function insert($data)
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');

        $resp = new SB_response( array('check_code' => true) );

        if ($data['act'] == 'delete') {
            self::check_post_exist($data['ID']);
            $delete_rs = wp_delete_post($data['ID'], true);
			if (!$delete_rs) {
				$resp->failure('Delete Post Unsuccessfully.');
			} else {
				$stored_count = get_option( '_seobeginner_count_post_of_tool', false );
                if ($stored_count) {
				    update_option('_seobeginner_count_post_of_tool', $stored_count - 1);
                }
				$resp->success('Delete Post Successfully.');
			}
        }

        if (empty($data['post_title'])) {
            $resp->failure('Fail - Post title is empty');
        }

        if (isset($data['seo_slug'])) {
            $data['post_name'] = sanitize_text_field($data['seo_slug']);
        }
        $resp->set('data', array() );

        $data['post_status'] = 'publish'; // auto publish post;
        
        if (isset($data['post_author']['ID'])) {
        	$data['post_author'] = $data['post_author']['ID'];
		} else {
			$data['post_author'] = 1; // auto assign author for admin.
		}

        if ($data['ID'] == '') {
            $post = wp_insert_post($data);
            if ($post) {
                $resp->set('action','insert');
                update_post_meta($post, BI_KEY_TOOL_AUTO_POST, 1);
				$stored_count = get_option( '_seobeginner_count_post_of_tool', 0 );
				update_option('_seobeginner_count_post_of_tool', $stored_count + 1);
            }
        } else {
            self::check_post_exist($data['ID']);
            wp_update_post($data);

            global $wpdb;
            $result = $wpdb->update(
                $wpdb->prefix . 'posts',
                array(
                    'post_name' => $data['slug'],
                    'guid' => get_home_url() . '/' . $data['slug'],
                ),
                array('ID' => $_REQUEST['ID']),
                array('%s', '%s'),
                array('%d')
            );
            if ($result !== false) 
            $post = $data['ID'];
            $resp->set('action','update');
        }

        if (is_wp_error($post)) {
            $resp->failure($post->get_error_message());
        } else {
            $post_obj = get_post($post);
			$resp->set('permalink', get_permalink($post_obj));
            $resp->set('data', $post_obj);
            update_post_meta($post, '_seobeginner_api_insert', 1);

            if (isset($data['featured_img'])) {
                if (!empty($data['featured_img'])) {
                    $url_img = esc_url($data['featured_img']);
                    bi_insert_img_from_url($url_img, $post);
                }
            }

            self::saveSeoInfo($post, $data);
        }
        switch ($resp->get('action')) {
            case 'insert':
                $resp->success('Insert Post Successfully.');
                break;
            
            case 'update':
                $resp->success('Update Post Successfully');
                break;

            default:
                # code...
                break;
        }
    }

    /**
     * check the plugin and save all seo meta into database.
     */
    public static function saveSeoInfo($post_id, $request)
    {

        $plugin = $request['seo_plugin'];

        switch ($plugin) {
            case 'yoast-seo':
                self::saveSeoYoast($post_id, $request);
                break;
            case 'seo-ultimate':
                self::saveSeoUltimatet($post_id, $request);
                break;
            case 'aio-seo-pack':
                self::saveSeoAioPack($post_id, $request);
                break;
            case 'wp-meta-seo':
                self::saveSeoMeta($post_id, $request);
                break;
            default:
                # code...
                break;
        }

    }

    public static function saveSeoYoast($post_id, $request)
    {
        $prefix_ = "_yoast_wpseo_";
        $meta = array(
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw_text_input',
            '_yoast_wpseo_content_score',
            '_yoast_wpseo_primary_category',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_linkdex'
        );

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($request['seo_des']));
        }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($request['seo_title']));
        }
        if (isset($request['seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($request['seo_keyword']));
        }

    }

    public static function saveSeoAioPack($post_id, $request)
    {

        $meta = array('_aioseop_description', '_aioseop_title');

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_aioseop_description', sanitize_text_field($request['seo_des']));
        }
        // if( isset( $request['seo_keyword']) ){
        // 	update_post_meta($post_id,'_yoast_wpseo_focuskw',  $request['seo_keyword'] );
        // }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_aioseop_title', sanitize_text_field($request['seo_title']));
        }

    }

    public static function saveSeoUltimatet($post_id, $request)
    {

        $meta = array('_su_title', '_su_description', '_su_meta_robots_noindex', '_su_meta_robots_nofollow');

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_su_description', sanitize_text_field($request['seo_des']));
        }
        if (isset($request['seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($request['seo_keyword']));
        }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_su_title', sanitize_text_field($request['seo_title']));
        }

    }

    public static function saveSeoMeta($post_id, $request)
    {
        $prefix_ = "_yoast_wpseo_";
        $meta = array(
            '_metaseo_metatitle',
            '_metaseo_metadesc',
            '_metaseo_metaopengraph-desc',
            '_metaseo_metaopengraph-image',
            '_metaseo_metatwitter-title',
            '_metaseo_metatwitter-desc',
            '_metaseo_metatwitter-image'
        );

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_metaseo_metadesc', sanitize_text_field($request['seo_des']));
            update_post_meta($post_id, '_metaseo_metaopengraph-desc', sanitize_text_field($request['seo_des']));

        }
        if (isset($request['seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($request['seo_keyword']));
        }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_metaseo_metatitle', sanitize_text_field($request['seo_title']));
        }

    }

    public static function check_post_exist($id)
    {
        $query_post = get_post($id);
        if (is_null($query_post)) {
            $resp = new SB_response();
            $resp->set('err', 'post_not_found');
            $resp->set('post', $id);
            $resp->failure('Post ' . $id . ' is not exist.');
        }
    }
}

add_action('init', array('BI_Insert', 'init'));
add_action('wp_ajax_nopriv_bi_send', array('BI_Insert', 'bi_send'));
add_action('wp_ajax_bi_send', array('BI_Insert', 'bi_send'));


/**
 * Name: bi_api_remote_settings
 * Create form to display plugin settings
 */
function bi_api_remote_settings()
{
    if (!empty($_POST['api_remote_code'])) {
        update_option('api_remote_code', sanitize_text_field($_POST['api_remote_code']));
    }
    $api_remote_code = get_option('api_remote_code', true);
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    echo '<div class="wrap bi-import">'; ?>

    <form style=" padding: 10px 20px; min-height: 600px; border:1px solid #ccc; display: block;overflow: hidden;"
          class="frm-bi" method="POST">
        <div class="postbox-container">
            <div class="full">
                <label><?php _e('Set password for Insert Post API', 'boxtheme'); ?></label>
            </div>
            <div class="full">
                <input type="text" placeholder="<?php _e("Set your code here", 'boxtheme'); ?>" id="api_remote_code"
                       class="api_remote_code" name="api_remote_code"
                       value="<?php echo esc_attr($api_remote_code); ?>">
                <span class="button general-pw"><?php _e('Generate Password', 'boxtheme'); ?> </span>
            </div>
            <button type="submit"><?php _e('Save', 'boxtheme'); ?></button>
        </div>
    </form>
    <style type="text/css">
        .frm-bi input {
            display: inline-block;
            clear: both;
            margin-bottom: 15px;
            height: 39px;
        }

        .frm-bi button {
            min-width: 120px;
            padding: 8px 10px;
            text-align: right;
            float: right;
            border-radius: 5px;
            border: 0;
            color: #fff;
            background-color: #048269;
            cursor: pointer;
            text-align: center;
            height: 39px;
        }

        .full {
            width: 100%;
            float: left;
            clear: both;
            padding-bottom: 15px;
            margin: 0 auto;
        }

        .full input {
            width: 60%;
        }

        .frm-bi .full span {
            width: auto;
            float: right;
            height: 39px;
            line-height: 35px;
        }
    </style>
    <script type="text/javascript">
        (function ($) {
            $(document).ready(function () {
                $(".general-pw").click(function (e) {
                    var view = this;
                    $target = $(e.currentTarget);

                    var pData = {};
                    $target.find('input,textarea,select').each(function () {
                        var $this = $(this);
                        pData[$this.attr('name')] = $this.val();
                    });

                    var param = {
                        url: '<?php echo admin_url('admin-ajax.php');?>',
                        type: 'POST',
                        data: {
                            'action': 'bi_general_pw',
                        },
                        //contentType	: 'application/x-www-form-urlencoded;charset=UTF-8',
                        beforeSend: function () {
                            console.log('beforeSend');
                            //view.blockUi.block( $target );
                        },
                        success: function (resp) {
                            if (resp.success) {
                                $("#api_remote_code").val(resp.pw);
                            }
                        },
                        complete: function () {
                            //view.blockUi.unblock();
                        }
                    };
                    $.ajax(param);
                    return false;
                })
            })
        })(jQuery);
    </script>
    <?php

    echo '</div>';

}

/**
 * Name: bi_seometa_settings_init
 * Add menu item to wp admin menu
 */
function bi_seometa_settings_init()
{
    add_submenu_page('tools.php', __('API Insert Post Remote', 'all-in-one-seo-pack'), __('API Insert Post Remote', 'boxthemes'), 'manage_options', 'bi_api_remote_settings', 'bi_api_remote_settings');
}

add_action('admin_menu', 'bi_seometa_settings_init');

/**
 * Name: bi_generate_password
 * Generate password, is called via ajax
 */
function bi_generate_password()
{
    $size = rand(10, 20);
    $pass = wp_generate_password($size, true, true);
    wp_send_json(array('success' => true, 'pw' => $pass));
}

add_action('wp_ajax_bi_general_pw', 'bi_generate_password');


//add_filter( 'post_thumbnail_html', 'bi_thumbnail_external_replace', 10, 3 );

/**
 * @param $html
 * @param $post_id
 * @param $post_thumbnail_id
 *
 * Handle image (features)
 *
 * @return string
 */
function bi_thumbnail_external_replace($html, $post_id, $post_thumbnail_id)
{

    // if ( empty( $url ) || ! url_is_image( $url ) ) {
    //     return $html;
    // }
    if ($post_thumbnail_id) {
        return $html;
    }
    $url = get_post_meta($post_id, 'featured_img', true);

    if (!empty($url)) {
        $alt = get_post_field('post_title', $post_id) . ' ' . __('thumbnail', 'boxtheme');
        $attr = array('alt' => $alt);
        $attr = apply_filters('wp_get_attachment_image_attributes', $attr, null);
        $attr = array_map('esc_attr', $attr);
        $html = sprintf('<img src="%s"', esc_url($url));
        foreach ($attr as $name => $value) {
            $html .= " $name=" . '"' . $value . '"';
        }
        $html .= ' />';
    }

    return $html;
}

function bi_insert_img_from_url($url, $parent_post_id, $post_title = '')
{

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $timeout_seconds = 10;

    // Download file to temp dir
    $temp_file = download_url($url, $timeout_seconds);

    if (!is_wp_error($temp_file)) {

        // Array based on $_FILE as seen in PHP file uploads
        $file = array(
            'name' => basename($url), // ex: wp-header-logo.png
            'type' => 'image/png',
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        );

        $overrides = array(
            // Tells WordPress to not look for the POST form
            // fields that would normally be present as
            // we downloaded the file from a remote server, so there
            // will be no form fields
            // Default is true
            'test_form' => false,

            // Setting this to false lets WordPress allow empty files, not recommended
            // Default is true
            'test_size' => true,
        );

        // Move the temporary file into the uploads directory
        $results = wp_handle_sideload($file, $overrides);
        //var_dump($results);
        if (!empty($results['error'])) {
            // Insert any error handling here
        } else {

            $filename = $results['file']; // Full path to the file
            $local_url = $results['url'];  // URL to the file in the uploads dir
            $type = $results['type']; // MIME type of the file
            $wp_upload_dir = wp_upload_dir();

            // Prepare an array of post data for the attachment.
            $attachment = array(
                'guid' => $filename,
                'post_mime_type' => $type,
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Insert the attachment.
            $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);

            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
            wp_update_attachment_metadata($attach_id, $attach_data);

            $thumbnail_id = set_post_thumbnail($parent_post_id, $attach_id);
            if (!$thumbnail_id) {
                set_own_post_thumbnail($parent_post_id, $attach_id);
            }
            // Perform any actions here based in the above results
        }

    }
}

function set_own_post_thumbnail($post, $thumbnail_id)
{
    $post = get_post($post);
    $thumbnail_id = absint($thumbnail_id);
    if ($post && $thumbnail_id && get_post($thumbnail_id)) {
        return update_post_meta($post->ID, '_thumbnail_id', $thumbnail_id);
    }
    return false;
}

function bi_get_post_info_by_guid()
{
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    global $wpdb;
    $guid = isset($_REQUEST['guid']) ? sanitize_text_field($_REQUEST['guid']) : '';

    $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid));
    $post = get_post($post_id);
    echo json_encode(array(
        'ID' => $post->ID,
        'post_title' => $post->post_title,
        'post_status' => $post->post_status,
        'post_name' => $post->post_name,
    ));
    die();

}

add_action('wp_ajax_bi_get_post_info_by_guid', 'bi_get_post_info_by_guid');
add_action('wp_ajax_nopriv_bi_get_post_info_by_guid', 'bi_get_post_info_by_guid');

function remove_row_actions($actions, $post)
{
    $meta_auto_post = get_post_meta($post->ID, BI_KEY_TOOL_AUTO_POST);
    $meta_auto_post_value = $meta_auto_post[0];
    if ($post->post_type === 'post' && $meta_auto_post_value == '1') {
        //unset($actions['edit']);
        unset($actions['trash']);
        unset($actions['inline hide-if-no-js']);
		$actions['delete'] = sprintf(
	       '<a href="%s" class="submitdelete" aria-label="%s" onclick="confirm_delete(event)">%s</a>',
	       get_delete_post_link( $post->ID, '', true ),
	       /* translators: %s: post title */
	       esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $post->post_title ) ),
	       __( 'Delete Permanently' )
	    );
        echo '
		    <script>
                jQuery(document).ready(function () {
                    jQuery("#cb-select-' . $post->ID . '").prop("disabled", true);
                });
				function confirm_delete(e) {
					var r = confirm("Are you sure with your action?");
						if (r == true) {
							txt = "You pressed OK!";
						} else {
							e.preventDefault();
							return false;
						}
				}
            </script>
		';
    }

    return $actions;
}

add_filter('post_row_actions', 'remove_row_actions', 10, 2);

function hide_publishing_actions_head()
{
    $post_type = 'post';
    global $post;
    $meta_auto_post = get_post_meta($post->ID, BI_KEY_TOOL_AUTO_POST, false);
	if (!$meta_auto_post || count($meta_auto_post) == 0) return;
    $meta_auto_post_value = $meta_auto_post[0];
    if ($post->post_type == $post_type && $meta_auto_post_value == '1') {
        echo '
                <style type="text/css">
                    #publish, #delete-action {
                        display: none;
                    }
                </style>
            ';
    }
}
add_action('admin_head-post.php', 'hide_publishing_actions_head');
add_action('admin_head-post-new.php', 'hide_publishing_actions_head');

function hide_publishing_actions()
{
    $post_type = 'post';
    global $post;
    $meta_auto_post = get_post_meta($post->ID, BI_KEY_TOOL_AUTO_POST);
    $meta_auto_post_value = $meta_auto_post[0];
    if ($post->post_type == $post_type && $meta_auto_post_value == '1') {
        echo '
                <style type="text/css">
                    #submitdiv {
                        /*display: none;*/
                    }
                </style>
				<script>
				
						var button_submit = document.getElementById("publish");
						var delete_action = document.getElementById("delete-action")
							button_submit.style.display = "block";
							delete_action.style.display = "block";
						var delete_action_html = delete_action.innerHTML
					var confirm_action = function () {
						var checkBox = document.getElementById("confirm_action");
						var button_submit = document.getElementById("publish");
						var delete_action = document.getElementById("delete-action")
						if (checkBox.checked == true){
							document.getElementById("delete-action").innerHTML =\'' . sprintf(
	       '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
	       get_delete_post_link( $post->ID, '', true ),
	       /* translators: %s: post title */
	       esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $post->post_title ) ),
	       __( 'Delete Permanently' )
	    ) .'\'
						} else {
						   	document.getElementById("delete-action").innerHTML = delete_action_html
						}
					}
					document.getElementById("confirm_action").addEventListener("click", confirm_action);
					
				var confirm_publish = function (e) {
					var checkBox = document.getElementById("confirm_action");
					if (checkBox.checked == true){
						var r = confirm("Are you sure with your action?");
						if (r == true) {
							txt = "You pressed OK!";
						} else {
							e.preventDefault();
							return false;
						}
					}
				}
				document.getElementById("post").addEventListener("submit", confirm_publish);
				$("#delete-action").on(\'click\',\'a\',confirm_publish);
		
				</script>
            ';
    }
}
add_action('admin_footer-post.php', 'hide_publishing_actions');
add_action('admin_footer-post-new.php', 'hide_publishing_actions');

add_action( 'post_submitbox_misc_actions', 'post_submitbox_misc_actions' );

function post_submitbox_misc_actions($post){
?>
<div class="misc-pub-section my-options">
	<input type="checkbox" name="confirm" id="confirm_action">Allow for actions affecting the data on the tools.
</div>
<?php
}


function sync_data_when_active($current_count = 0, $stored_count = 0, $plugin_version_stored = '0.0.0')
{
	if ($current_count == 0) {
		$query = new WP_Query( array( 'meta_key' => BI_KEY_TOOL_AUTO_POST, 'meta_value' => 1 ) );
   		$current_count = $query->found_posts;
	}
	if ($stored_count == 0) $stored_count = get_option( '_seobeginner_count_post_of_tool', false );
	if ($plugin_version_stored == '0.0.0') $plugin_version_stored = get_option( '_seobeginner_plugins_version', false );
    
//   if (!extension_loaded('mbstring')) {
// 		deactivate_plugins( plugin_basename( __FILE__ ) );
//         wp_die( __( 'SEOBeginner Auto Post Alert: Please enable module mbstring for php.', 'boxtheme' ), 'Plugin dependency check', array( 'back_link' => true ) );
// 	}
//     if (!extension_loaded('dom') || !extension_loaded('xmlreader') || !extension_loaded('xmlrpc') || !extension_loaded('xmlwriter')) {
// 		deactivate_plugins( plugin_basename( __FILE__ ) );
//         wp_die( __( 'SEOBeginner Auto Post Alert: Please enable modules dom, xmlreader, xmlrpc, xmlwriter for php.', 'boxtheme' ), 'Plugin dependency check', array( 'back_link' => true ) );
// 	}
	$data_from_tool = array(
		"domain" => get_home_url(),
        "pbn_info" => get_info(),
		"site_id" => get_option('SBAP_site_id', ''),
		"post_list" => array()
	);
    $query = array(
        'posts_per_page'   => -1,
        'post_type'        => 'post',
// 		'meta_query' => array( 
// 			array(
//         		'key'         => '_seobeginner_auto_post',
//         		'value' 	=> 1,
// 				'type'    	=> 'numeric',
//     			'compare' 	=> '='
// 			)
// 		)
    );
    $posts = new WP_Query( $query );
	if (is_array($posts->posts) || is_object($posts->posts)) {
		foreach($posts->posts as $post) {
			
			$data_from_tool["site_id"] = get_post_meta($post->ID, 'SBAP_site_id', true);
			if (get_post_meta($post->ID, '_seobeginner_auto_post', true)) {
				$post_error = '';
			} else if (get_post_meta($post->ID, '_seobeginner_auto_post', true) == '') {
				$post_error = 'lost_meta';
			}
			$tags = wp_get_post_tags($post->ID, array( 'fields' => 'ids' ));
			$cates = wp_get_post_categories($post->ID, array( 'fields' => 'ids' ));
			$data_from_tool["post_list"][] = array(
				"ID" => $post->ID,
				"guid" => $post->guid,
				"permalink" => get_permalink($post->ID),
				"post_title" => $post->post_title,
				"post_name" => $post->post_name,
				"post_type" => $post->post_type,
				"post_date" => $post->post_date,
				"tags" => $tags,
				"cates" => $cates,
                "tool_post_id" => get_post_meta($post->ID, 'SBAP_tool_post_id', true),
                "tool_post_created_by" => get_post_meta($post->ID, 'SBAP_tool_post_created_by', true),
				"tool_is_guestpost" => filter_var(get_post_meta($post->ID, 'SBAP_tool_post_is_guestpost', true), FILTER_VALIDATE_BOOLEAN),
				"tool_site_id" => get_post_meta($post->ID, 'SBAP_site_id', true),
				"error" => $post_error
			);
		}
	}
	$data_from_tool["checked_details"] = array('$current_count' => $current_count,'$stored_count' => $stored_count, '$plugin_version_stored' => $plugin_version_stored,'PLUGIN_VERSION' => PLUGIN_VERSION);
    //Send post request to Tool-SeoBeginner system
    if (isset($_GET['act']) && $_GET['act'] == 'get_posts') {
        echo json_encode($data_from_tool, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        die();
    }
    
    $json_data = json_encode($data_from_tool, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
	file_put_contents(__DIR__ . '/json_request.json', print_r(json_encode($data_from_tool), true));
	//echo $json_data; die();
    $ch = curl_init( 'https://tools.seobeginner.com/hooks/ss/sync-posts-when-active' );
    # Setup request to send json via POST.
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    # Return response instead of printing.
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    # Send request.
    $result = curl_exec($ch);
    curl_close($ch);
	$response_data = json_decode($result, true);
	file_put_contents(__DIR__ . '/json_reponse.json', print_r($response_data, true));
	foreach ($response_data['lost_meta_posts'] as $error_post) {
		add_post_meta($error_post['ID'], '_seobeginner_auto_post', 1);
	}
}


register_activation_hook(__FILE__, 'sync_data_when_active');

function get_info()
{

    if (!function_exists('get_plugin_data')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];

    $data = array(
        'plugin_version' => $plugin_version,
        'php_version' => phpversion(),
        'wp_version' => get_bloginfo('version')
    );

    return $data;
}

function get_pbn_info()
{
    $data = get_info();
    echo json_encode($data);
    die();
}

add_action('wp_ajax_get_pbn_info', 'get_pbn_info');
add_action('wp_ajax_nopriv_get_pbn_info', 'get_pbn_info');

function url_get_contents($Url)
{
    if (!function_exists('curl_init')) {
        die('CURL is not installed!');
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $Url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function get_own_home_url()
{
    return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/";
}

/**
 * Could not add to without-update
 */

function my_custom_styles()
{
    echo "<style>
 				img.sbap-content {
				width: 100% !important;
				height: auto !important;
			}

 </style>";
}

add_action('wp_head', 'my_custom_styles', 100);

function add_responsive_class($content)
{

    $content = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");
	if (empty($content)) return $content;
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML(utf8_decode($content));

    $imgs = $document->getElementsByTagName('img');
    foreach ($imgs as $img) {
        $img->setAttribute('class', 'sbap-content');
    }

    $html = $document->saveHTML();
    return $html;
}
if (extension_loaded('mbstring') && extension_loaded('dom') && extension_loaded('xmlreader') && extension_loaded('xmlrpc') && extension_loaded('xmlwriter')) {
    add_filter('the_content', 'add_responsive_class');
}

function removeBOM($data) {
    if (0 === strpos(bin2hex($data), 'efbbbf')) {
        return substr($data, 3);
    }
    return $data;
}

add_action('save_post', 'do_update_to_tool', 10, 3);
function do_update_to_tool($post_id, $post_data, $updated) {
	if (isset($_REQUEST['confirm']) && $_REQUEST['confirm'] == 'on') {
		$post_type = 'post';
		$meta_auto_post = get_post_meta($post_id, BI_KEY_TOOL_AUTO_POST);
		$meta_auto_post_value = $meta_auto_post[0];
		if ($post_data->post_type == $post_type && $meta_auto_post_value == '1') {
			$post_data->cates = get_the_category();
			$post_data->tags = get_the_tags();
			$post_data->permalink = get_permalink($post_data);
			$json_data = json_encode($post_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
			$ch = curl_init( 'https://tools.seobeginner.com/hooks/ss/update-post' );
			# Setup request to send json via POST.
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			# Return response instead of printing.
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			# Send request.
			$result = curl_exec($ch);
			curl_close($ch);
			//echo $json_data;
		}
	}
}

add_action( 'before_delete_post', 'do_delete_on_tool' );
function do_delete_on_tool( $postid ){
	$meta_auto_post = get_post_meta($postid, BI_KEY_TOOL_AUTO_POST);
	$meta_auto_post_value = $meta_auto_post[0];
	if ($meta_auto_post_value == '1') {
		$stored_count = get_option( '_seobeginner_count_post_of_tool', false );
		if ($stored_count) update_option('_seobeginner_count_post_of_tool', $stored_count - 1);
		global $wp;
		$ch = curl_init( 'https://tools.seobeginner.com/hooks/ss/delete-post' );
		# Setup request to send json via POST.
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode(array("post_id" => $postid, "domain" => home_url( $wp->request ) )) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		# Return response instead of printing.
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		# Send request.
		$result = curl_exec($ch);
		curl_close($ch);
	}
}

add_action( 'delete_attachment', 'do_delete_img_on_tool' );
function do_delete_img_on_tool( $attach_id ) {
	$meta_auto_post = get_post_meta($attach_id, BI_KEY_TOOL_AUTO_POST);
	$meta_auto_post_value = $meta_auto_post[0];
	if ($meta_auto_post_value == '1') {
		global $wp;
		$ch = curl_init( 'https://tools.seobeginner.com/hooks/ss/delete-attach' );
		# Setup request to send json via POST.
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode(array("attach_id" => $attach_id, "url" => wp_get_attachment_url( $attach_id ) )) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		# Return response instead of printing.
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		# Send request.
		$result = curl_exec($ch);
		curl_close($ch);
	}
}


function seobeginner_content_filter( $content ) {
    return html_entity_decode(str_replace(array("<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\">\n<html><body>","</body></html>"),"",$content), ENT_COMPAT || ENT_QUOTES, 'UTF-8');
}
add_filter( 'the_content', 'seobeginner_content_filter' );


class SB_response 
{
    public $response;
    function __construct($options = array('check_code' => false)) {

        $this->response = array();

        if (!isset($options['check_code'])) $options['check_code'] = false;

        if ($options['check_code'] == 'true') {
            $code = isset($_REQUEST['code']) ? sanitize_text_field($_REQUEST['code']) : '';

            $api_code = get_option('api_remote_code', mt_rand(10, 20));

            if ($code != $api_code || empty($api_code)) {

                $this->response['success'] = false;
                $this->response['msg'] = __('Code invalid', 'boxtheme');
                $this->response['code'] = '002';

                $this->send();
            }
        }
    }

    function set($key, $value) {
        $this->response[$key] = $value;
    }
    function get($key) {
        return $this->response[$key] || false;
    }

    function failure($msg = '') {
        $this->response['success'] = false;
        $this->response['msg'] = __($msg, 'boxtheme');
        $this->response['code'] = '002';
        $this->send();
    }

    function success($msg = '') {
        $this->response['success'] = true;
        $this->response['msg'] = __($msg, 'boxtheme');
        $this->response['code'] = '001';
        $this->send();
    }

    function send() {
        $this->response['request_data'] = $_REQUEST;
        $this->response['pbn_info'] = get_info();
        echo json_encode($this->response);
        die();
    }
}