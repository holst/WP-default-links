<?php
/*
Plugin Name: WP Default Links
Plugin URI: http://superninja.dk/projects/wp-default-link/
Description: Markdown (and, presumably, other tools like it) offers to opportunity to make links in a slightly less obtrusive way than the standard HTML `a` element. Links can also be made with a list at the end of the post, by using [Link title][], the link being defined with [Link title]: http://example.com/. This plugin makes the last bit optional, by allowing the creation of a link database for often used URIs.
Version: 0.1
Author: Jonathan Holst
Author URI: http://holst.biz/
License: Public domain
*/

/*  Made by Jonathan Holst, 2010  (email : jonathan@holst.biz)
    
    This program is in the public domain, and you are permitted to with it 
    whatever you desire.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/*  TODO:
    * Allow for editing and deletion of links
    * Redirect after POSTs
*/

$_DB_VERSION = 0.1;

function default_links_install() {
    global $wpdb, $_DB_VERSION;
    
    $table = $wpdb->prefix.'default_links';
    
    if($wpdb->get_var('SHOW TABLES LIKE "'.$table.'"') != $table) {
        $sql = array('CREATE TABLE ' . $table . ' (
        	id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        	name VARCHAR(250) NOT NULL UNIQUE KEY,
        	uri VARCHAR(250) NOT NULL,
        	tooltip VARCHAR (250) NOT NULL
        )');
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        add_option('default_link_db_version', $_DB_VERSION);
    }
}

function default_links($text) {
    // The following weird stuff is borrowed from Michel Fortin's Markdown 
    // Extra.
    $nested_brackets_depth = 6;
    
    $nested_brackets_re = str_repeat('(?>[^\[\]]+|\[', $nested_brackets_depth).
		str_repeat('\])*', $nested_brackets_depth);
    
    $text = preg_replace_callback('/(\[('.$nested_brackets_re.')\]\ ?(?:\n\ *)?\[(.*?)\])/s', '_doAnchors_reference_callback', $text);
    
    return $text;
}

function get_link_by_name($name) {
    global $wpdb;
    
    $sql = sprintf('SELECT
            uri,
            tooltip
        FROM
            %sdefault_links
        WHERE
            name = "%s"
        LIMIT 1',
        $wpdb->prefix,
        $name
    );
    
    $result = $wpdb->get_row($sql);
    
    return $result ? $result : false;
}

function _doAnchors_reference_callback($matches) {
	$whole_match =  $matches[1];
	$link_text   =  $matches[2];
	$link_name   =  $matches[3] ? $matches[3] : $link_text;
	
	# lower-case and turn embedded newlines into spaces
	$link_name = strtolower($link_name);
	$link_name = preg_replace('/\ ?\n/', ' ', $link_name);

	if($link = get_link_by_name($link_name)) {
		$result = '<a href="'.$link->uri.'"';
		
		if (isset($link->tooltip)) {
			$result .=  ' title="'.$link->tooltip.'"';
		}
	    
		$result .= '>'.$link_text.'</a>';
	}
	else {
		$result = $whole_match;
	}
	
	return $result;
}


register_activation_hook(__FILE__, 'default_links_install');

/* ADMINISTRATION */
function wp_default_links_menu() {
    add_links_page('Default Links', 'Default Links', 'manage_options', 'wp-default-link', 'wp_default_links_options');
}
add_action('admin_menu', 'wp_default_links_menu');

function wp_default_links_options() {
    global $wpdb;
    
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    }
    
    if(isset($_POST['name'], $_POST['uri']) && $_POST['name'] && $_POST['uri']
    ) {
        $data = array('name' => $_POST['name'], 'uri' => $_POST['uri'], 
            'tooltip' => isset($_POST['tooltip']) ? $_POST['tooltip'] : '');
        
        if(!isset($_POST['id'])) {
            $wpdb->insert($wpdb->prefix.'default_links', $data);
        }
        else {
            $wpdb->update($wpdb->prefix.'default_links', $data,
                array('id' => $_POST['id']));
        }
    }
    
    if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $sql = $wpdb->prepare(
            'DELETE FROM '.$wpdb->prefix.'default_links WHERE id = %d', 
            $_GET['delete']);
        
        $wpdb->query($sql);
    }
    
    if(isset($_GET['edit'])) {
        $sql = $wpdb->prepare('SELECT
            name,
            uri,
            tooltip
        FROM
            '.$wpdb->prefix.'default_links
        WHERE
            id = %d',
        $_GET['edit']);
        
        $row = $wpdb->get_row($sql);
    }
    
    echo '<div class="wrap">';
    echo '<h2>Manage default links</h2>';
    
    if(isset($row)) {
        echo '<p><a href="'.admin_url('link-manager.php?page=wp-default-link').'">Add new link</a></p>';
        echo '<h3>Edit link</h3>';
    }
    else {
        echo '<h3>Add new link</h3>';
    }
    
    echo '<form method="post" action="">';
    echo '<div><label for="name">Name: <input type="text" name="name" value="'.(isset($row) ? $row->name : '').'" id="name"></label></div>';
    echo '<div><label for="uri">URI: <input type="text" name="uri" value="'.(isset($row) ? $row->uri : 'http://').'" id="uri"></label></div>';
    echo '<div><label for="tooltip">Tooltip (optional): <input type="text" name="tooltip" value="'.(isset($row) ? $row->tooltip : '').'" id="tooltip"></label></div>';
    echo '<div><input type="submit" value="'.(isset($row) ? 'Edit' : 'Add link').'">'.(isset($row) ? '<input type="hidden" name="id" value="'.$_GET['edit'].'" id="id">' : '').'</div>';
    echo '</form>';
    echo '<hr />';
    
    $sql = 'SELECT
        id,
        name,
        uri,
        tooltip
    FROM
        '.$wpdb->prefix.'default_links';
    
    $objects = $wpdb->get_results($sql);
    
    if(count($objects) > 0) {
        echo '<h3>List of current links</h3>';
    
        echo '<table>';
        echo '<thead><tr><th>Name</th><th>URI</th><th>Tooltip</th></tr></thead>';
        
        echo '<tbody>';
        
        foreach($objects as $row) {
            echo '<tr>';
            echo '<td>'.$row->name.'</td>';
            echo '<td>'.$row->uri.'</td>';
            echo '<td>'.$row->tooltip.'</td>';
            echo '<td><a href="'.admin_url('link-manager.php?page=wp-default-link&amp;edit='.$row->id).'">Edit</a>. <a href="'.admin_url('link-manager.php?page=wp-default-link&amp;delete='.$row->id).'">Delete</a>.</td>';
            echo '</tr>';
        }
    
        echo '</tbody>';
    
        echo '</table>';
    }
    
    echo '</div>';
}

function wp_default_links_manage( $links, $file ) {
 	if( $file == 'wp-default-link/wp-default-link.php' && function_exists( "admin_url" ) ) {
		$settings_link = '<a href="'.admin_url('link-manager.php?page=wp-default-link').'">'.__('Manage').'</a>';
		array_unshift( $links, $settings_link );
	}
	return $links;
}
add_filter( 'plugin_action_links', 'wp_default_links_manage', 10, 2);

// The reason for the relatively high priority number (thus, a little 
// confusingly, giving it low priority) is to not step in before other scripts 
// have had time to examine the text and seen if it needs non-default 
// replacements.
add_filter('the_content', 'default_links', 9999);
add_filter('the_excerpt', 'default_links', 9999);