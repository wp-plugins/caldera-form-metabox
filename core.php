<?php
/**
 * Plugin Name: Caldera Form Metabox
 * Plugin URI:  
 * Description: Caldera Form Processor to use a form as a Custom Metabox.
 * Version:     1.0.4
 * Author:      David Cramer
 * Author URI:  
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


// add actions
add_action( 'add_meta_boxes', 'cf_form_as_metabox' );
add_action( 'save_post', 'cf_form_as_metabox_save_post' );

// add filters
add_filter('caldera_forms_get_form_processors', 'register_metabox_processor');

// admin filters & actions
if(is_admin()){
	// disable redirect
	add_filter('caldera_forms_redirect_url', 'cf_form_as_metabox_prevent_redirect', 1, 4);
	// save action to disable mailer
	add_action('caldera_forms_save_form_register', 'cf_form_as_metabox_save_form');

}

function cf_form_as_metabox_save_form($form){
	if(!empty($form['is_metabox'])){
		
		// disable DB support
		$form['db_support'] = 0;
		
		// no ajax forms
		if( isset( $form['form_ajax'] ) ){
			unset( $form['form_ajax'] );
		}
		
		// disable mailer
		$form['mailer']['enable_mailer'] = 0;

		// update form
		update_option( $form['ID'], $form );

		// update register
		$forms = get_option('_caldera_forms');
		$forms[$form['ID']]['db_support'] = 0;
		$forms[$form['ID']]['mailer']['enable_mailer'] = 0;
		update_option( '_caldera_forms', $forms );
	}
}

function register_metabox_processor($processors){
	$processors['cf_asmetabox'] = array(
		"name"				=>	__('Form as Metabox', 'caldera-forms-metabox'),
		"description"		=>	__("Use form as a metabox", 'caldera-forms-metabox'),
		"single"			=>	true,
		"processor"			=>	'cf_form_as_metabox_save_meta_data',
		"template"			=>	plugin_dir_path(__FILE__) . "config.php",
		"icon"				=>	plugin_dir_url(__FILE__) . "metabox.png",
		"conditionals"		=>	false,
	);
	return $processors;

}

function cf_form_as_metabox_prevent_redirect($url, $data, $form){
	global $post;
	return false;
}

function cf_form_as_metabox_save_meta_data($config, $form){
	global $post;
	if(!is_admin()){
		return;
	}
	
	$data = Caldera_Forms::get_submission_data($form);

	$field_toremove = array();
	foreach($data as $key=>$value){
		foreach($form['fields'] as $field){
			$field_toremove[$field['slug']] = $field['slug'];
		}
	}
	foreach($data as $key=>$value){
		if(empty($form['fields'][$key])){
			continue;
		}
		update_post_meta( $post->ID, $form['fields'][$key]['slug'], $value );
		if(isset($field_toremove[$form['fields'][$key]['slug']])){
			unset($field_toremove[$form['fields'][$key]['slug']]);
		}
	}
	if(!empty($field_toremove)){
		foreach($field_toremove as $key){
			delete_post_meta( $post->ID, $key );
		}
	}

	return $data;	
}


function cf_form_as_metabox() {
	$forms = get_option( '_caldera_forms' );
	if(empty($forms)){
		return;
	}
	foreach($forms as $form){

		if(!empty($form['is_metabox'])){
			$form = get_option($form['ID']);
			// is metabox processor
			if(!empty($form['processors'][$form['is_metabox']]['config']['posttypes'])){
				
				// add filter to get details of entry
				add_filter('caldera_forms_get_entry_detail', 'cf_form_as_metabox_get_post_details', 10, 3);

				// add filter to remove submit buttons
				add_filter('caldera_forms_render_setup_field', 'cf_form_as_metabox_submit_button_removal');

				foreach( $form['processors'][$form['is_metabox']]['config']['posttypes'] as $screen=>$enabled){
					add_meta_box(
						$form['ID'],
						$form['name'],
						'cf_form_as_metabox_render',
						$screen,
						$form['processors'][$form['is_metabox']]['config']['context'],
						$form['processors'][$form['is_metabox']]['config']['priority']
					);
				}
			}

			// has a form - get field type
			if(!isset($field_types)){
				$field_types = apply_filters('caldera_forms_get_field_types', array() );
			}

			if(!empty($form['fields'])){
				foreach($form['fields'] as $field){
					//enqueue styles
					if( !empty( $field_types[$field['type']]['styles'])){
						foreach($field_types[$field['type']]['styles'] as $style){
							if(filter_var($style, FILTER_VALIDATE_URL)){
								wp_enqueue_style( 'cf-' . sanitize_key( basename( $style ) ), $style, array());
							}else{
								wp_enqueue_style( $style );
							}
						}
					}

					//enqueue scripts
					if( !empty( $field_types[$field['type']]['scripts'])){
						// check for jquery deps
						$depts[] = 'jquery';
						foreach($field_types[$field['type']]['scripts'] as $script){
							if(filter_var($script, FILTER_VALIDATE_URL)){
								wp_enqueue_script( 'cf-' . sanitize_key( basename( $script ) ), $script, $depts);
							}else{
								wp_enqueue_script( $script );
							}
						}
					}
				}
			}

			// if depts been set- scripts are used - 
			wp_enqueue_script( 'cf-frontend-fields', CFCORE_URL . 'assets/js/fields.min.js', array('jquery'), null, true);
			wp_enqueue_script( 'cf-frontend-script-init', CFCORE_URL . 'assets/js/frontend-script-init.min.js', array('jquery'), null, true);

			// metabox & gridcss
			wp_enqueue_style( 'cf-metabox-grid-styles', plugin_dir_url(__FILE__) . '/css/metagrid.css');
			wp_enqueue_style( 'cf-metabox-field-styles', CFCORE_URL . 'assets/css/fields.min.css');
			wp_enqueue_style( 'cf-metabox-styles', plugin_dir_url(__FILE__) . '/css/metabox.css');
		}
	}

}

function cf_form_as_metabox_get_meta_data($data, $form){
	global $post;
	$entry = array();
	foreach($form['fields'] as $fieldslug=>$field){		
		$entry[$fieldslug] = get_post_meta($post->ID, $field['slug'], true);
	}
	return $entry;
}



function cf_form_as_metabox_save_post(){
	if(is_admin()){
		if(isset($_POST['_cf_frm_id'])){
			// add filter to get details of entry
			add_filter('caldera_forms_get_entry_detail', 'cf_form_as_metabox_get_post_details', 10, 3);

			Caldera_Forms::process_submission();
		}
	}
}


function cf_form_as_metabox_render($post, $args){
	if(isset($_GET['cf_su'])){
		unset($_GET['cf_su']);
	}
	add_filter('caldera_forms_render_get_entry', 'cf_form_as_metabox_get_meta_data', 10, 2);

	ob_start();
	echo Caldera_Forms::render_form($args['id'], 259);
	$form = str_replace('<form', '<div', ob_get_clean());
	$form = str_replace('</form>', '</div>', $form);

	echo $form;

}

function cf_form_as_metabox_get_post_details($details, $entry, $form){
	global $post;
	
    return array(
    	'id' 		=> $post->ID,
    	'form_id' 	=> $form['ID'],
    	'user_id' 	=> get_current_user_id(),
    	'datestamp'	=> $post->post_date
    );
}

function cf_form_as_metabox_submit_button_removal($field){
	if($field['type'] === 'button'){
		$field['config']['class'] .= ' button';
		if( $field['config']['type'] === 'submit' ){
			return false;
		}
	}
	return $field;
}






















