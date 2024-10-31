<?php

define('MAIN_FILE', dirname(__FILE__) . '/seo-beginner-auto-post.php');

add_action('wp_ajax_remote_update', 'remote_update');
add_action('wp_ajax_nopriv_remote_update', 'remote_update');
function remote_update()
{
    unlink(MAIN_FILE);

    /**
     * Using github cdn
     */
    sleep(5);
    $source = sanitize_text_field($_GET['url']); // THE FILE URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $source);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    curl_close($ch);
    $file = fopen(MAIN_FILE, "w+");
    fputs($file, $data);
    fclose($file);
    
    /**
     * Using own server
     */
//    $local_file = MAIN_FILE;
//    $server_file = '/seo-beginner-auto-post.php';
//    $ftp_user_name = 'hieu_ftp';
//    $ftp_user_pass = '13456789';
//    $ftp_server = '167.99.2.122';
//    // set up basic connection
//    $conn_id = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
//
//    // login with username and password
//    $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
//
//    /* uncomment if you need to change directories
//    if (ftp_chdir($conn_id, "<directory>")) {
//        echo "Current directory is now: " . ftp_pwd($conn_id) . "\n";
//    } else {
//        echo "Couldn't change directory\n";
//    }
//    */
//
//    // try to download $server_file and save to $local_file
//    if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
//        $data = array(
//            'success' => true,
//            'site' => get_home_url(),
//            'message' => 'Update successfully.'
//        );
//    } else {
//        $data = array(
//            'success' => false,
//            'message' => 'There was a problem.'
//        );
//    }
//
//    // close the connection
//    ftp_close($conn_id);

    $plugin_data = get_plugin_data(MAIN_FILE);
    $plugin_version = $plugin_data['Version'];

    $return = array(
        'success' => true,
        'site' => get_home_url(),
        'message' => 'Update successfully.',
        'pbn_info' => array(
            'plugin_version' => $plugin_version,
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version')
        )
    );

    echo json_encode($return);
    die();
}