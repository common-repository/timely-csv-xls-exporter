<?php
/**
 * Timely CSV EXL exporter settings page class
 *
 * Displays the settings page.
 * 
 * This file is part of the Timely CSV EXL exporter plugin
 * You can find out more about this plugin at http://www.freeamigos.mx
 * Copyright (c) 2006-2015  Virgial Berveling
 *
 * @package WordPress
 * @author Virgial Berveling
 * @copyright 2006-2015
 *
 * version: 1.1.1
 *
 *  added attachment post_type
 *
 *
 */

if (!defined('ABSPATH')) die();

register_activation_hook( FA_TCXE_PLUGIN_BASENAME, array('FATCXESettingsPage','fatcxe_activate_plugin') );
register_deactivation_hook( FA_TCXE_PLUGIN_BASENAME, array('FATCXESettingsPage','fatcxe_deactivate_plugin') );


class FATCXESettingsPage
{
    private $options;
    private $periods;
    private $export_formats;

    private $errors;
    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'plugins_loaded', array($this, 'update_check') );
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'admin_init', array( $this, 'request' ) );
        add_filter('plugin_action_links_' . FA_TCXE_PLUGIN_BASENAME, array($this,'add_action_links') );

        
        $this->periods = array(
            array('+1 week','Weekly'),
            array('+2 week','Every two weeks'),
            array('+1 month','Monthly'),
            array('+3 month','Quarterly'),
            array('+1 year','Yearly')
        );
        $this->export_formats = array(
            array('csv','CSV'),
            array('xls','MS Excell')
        );
        
        $this->errors = array();
        $this->errors['upload_dir'] = (object) array ("status"=>false,"message"=>"Attachments directory can not be created");
        $this->errors['send_mail'] = (object) array ("status"=>false,"message"=>"An error was detected while trying to send the e-mail");
    }
    
    
   

    static function delete_dir($dir)
    {
        return !empty($dir) && is_file($dir) ?
            @unlink($dir) :
            (array_reduce(glob($dir.'/*'), function ($r, $i) { return $r && FATCXESettingsPage::deleteDir($i); }, TRUE)) && @rmdir($dir);
    }
    
    
    
/**
 * Add a link to the settings page onto the plugin page.
 *
 * @since 1.0.0
 */

    function add_action_links ( $links ) {
         $mylinks = array(
         '<a href="' . admin_url( 'options-general.php?page=fatcxe-admin' ) . '">'.__('Settings').'</a>',
         );
        return array_merge( $links, $mylinks );
    }

    

/**
 * Add options page link.
 *
 * @since 1.0.0
 */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            '&#9733; Timely Export', 
            'manage_options', 
            'fatcxe-admin', 
            array( $this, 'create_admin_page' )
        );
        
    }
    
    
    public function request()
    {
        if (!empty($_GET['json']) && !empty($_GET['page']) && !empty($_GET['pt']))
        {
            if ($_GET['page'] == 'fatcxe-admin' && $_GET['json'] == 'fields')
            {
                $post_type = sanitize_text_field($_GET['pt']);
                $fields = $this->get_post_fields($post_type);
             
                wp_send_json( $fields );
                exit;
            }
        }
    }
    

    
    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'fatcxe_options' );
        
        if (isset($_GET['settings-updated']))
        {
            delete_option('fatcxe_next_export');
        }
?>
        <div class="wrap">
        <?php 
        
        if (isset($_GET['send']))
        {
            if ($_GET['send'] == '1')
            {
                ?>
            <div class="updated is-dismissible"><p>Timely export e-mail is succesfully send to <?=$this->options['emailaddress_to']?></p>
            </div>
            <?php 
            }else {
                $this->errors['send_mail']->status = true;
            }
        }

        if (!empty($this->options['cron_enabled'])):
        
            $next_export_time = fatcxe_next_export_time($this->options);

            if (empty($this->options['fields_to_export']))
            {?>
                <div class="notice" style="border-left-color:#ffdd43"><p><strong>Missing fields. Please select a minimum of 1 field for export</strong></p></div>
            <?php 
            }else if ($next_export_time)
            {
                ?>
            <div class="updated"><p>Timely export is <strong style="color:#46b450">ACTIVE</strong><br/><em style="color:rgba(0,0,0,0.5);font-size:12px">Next export sceduled on <?=date_i18n(get_option( 'date_format' ), $next_export_time).' '.date('H:i',$next_export_time)?>*</em></p></div>
                <?php 
            }else { ?>
                <div class="notice" style="border-left-color:#ffdd43"><p><strong>Timely export finished. Next export not within time range</strong></p></div>
            <?php 
            }
        
        
        ?>
        <?php else : ?> 
            <div class="error"><p>Timely export <strong style="color:#dc3232">INACTIVE</strong></p></div>
        <?php endif;?>
                     
        <?php  
        
        /* 
        RENDER POSSIBLE ERORS OUTPUT
        */
        
        foreach ($this->errors as $error)
        {
            if ($error->status)
            {?>
            <div class="error"><p>Error: <strong style="color:#dc3232"><?=$error->message?></strong></p></div>
            <?php }
        }?>
                      
           <h2>Manage the Timely CSV/XLS export settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'fatcxe_option_group' );   
                do_settings_sections( 'fatcxe-admin' );
                
                submit_button(); 
            if (!empty($this->options['cron_enabled']))
            {
                $link1 = '?page=fatcxe-admin&fatcxe_token='.$this->options['secret_token'].'&output=download';
                $link2 = '?page=fatcxe-admin&fatcxe_token='.$this->options['secret_token'].'&output=send';
                $disabled = '';
            }else {
                $link1 = $link2 = 'javascript:;';
                $disabled = 'disabled="disabled"';
            }
                
            ?>
            </form>
            <div style="clear:both;height:20px"></div>
            <a href="<?=$link2?>" class="button" <?=$disabled?>>Send now</a>&nbsp;&nbsp;<a href="<?=$link1?>" class="button" <?=$disabled?>>Download now</a>&nbsp;
            <?php  
            $last_export = get_option('fatcxe_last_export');
            if ($last_export != 'never'): ?>
<em style="color:rgba(0,0,0,0.5);display:inline-block;padding-top:5px">Last export send on <?=date_i18n(get_option( 'date_format' ), $last_export).' '.date('H:i',$last_export)?></em>
            <?php endif; ?>

            
            </p>
            <div style="clear:both;height:30px"></div>
            
            <div class="description"><p>* Cronscripts in Wordpress only run when a visitor visits the website. So next export while be as soon as a visitor visits the site after sceduled time. <a href="http://holisticnetworking.net/2008/10/18/scheduling-with-wordpress-cron-functions/" target="_blank">More info</a></p></div>
            <div style="background-color:#FFF;margin-top:5px">
            <div style="padding:40px;text-align:center;color:rgba(0,0,0,0.7);">
                <p class="description">If you find this plugin useful, you can show your appreciation here :-)
 </p>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="LTZWTLEDPULFE">
<input type="image" src="https://www.paypalobjects.com/nl_NL/NL/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal, de veilige en complete manier van online betalen.">
<img alt="" border="0" src="https://www.paypalobjects.com/nl_NL/i/scr/pixel.gif" width="1" height="1">
</form>
            </div>
</div>

</div>
        <?php
    }

/**
 * Register and add settings page.
 *
 * @since 1.0.0
 */
    public function page_init()
    {   
            
        
        register_setting(
            'fatcxe_option_group', // Option group
            'fatcxe_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'fatcxe-admin' // Page
        );  

     

        
        add_settings_field(
            'add_header', // ID
            'Column names', // Title 
            array( $this, 'show_header_row_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );  
        
        add_settings_field(
            'post_type_to_export', // ID
            'Select post type to export', // Title 
            array( $this, 'field2_select_post_type' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      

        
        add_settings_field(
            'post_status_to_export', // ID
            'Select which post status to export', // Title 
            array( $this, 'field3_select_post_status' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );     

        add_settings_field(
            'taxonomies_to_export', // ID
            'Select taxonomies to export', // Title 
            array( $this, 'field4_select_taxonomies' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );     

        add_settings_field(
            'fields_to_export', // ID
            'Select fields to export', // Title 
            array( $this, 'field5_select_fields' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );     
        
        
        
        add_settings_field(
            'export_period', // ID
            'Select the period to export', // Title 
            array( $this, 'select_export_period_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        add_settings_field(
            'filter_on_period', // ID
            'Send data between period', // Title 
            array( $this, 'enable_filter_on_period_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        ); 

        add_settings_field(
            'export_period_start', // ID
            'Starting date', // Title 
            array( $this, 'select_export_period_start_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        add_settings_field(
            'export_period_end', // ID
            'Ending date', // Title 
            array( $this, 'select_export_period_end_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        
        
        add_settings_field(
            'delimiter', // ID
            'CSV Delimiter', // Title 
            array( $this, 'delimiter_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );            
        
        add_settings_field(
            'emailaddress_to', // ID
            'Export to E-mailaddress', // Title 
            array( $this, 'emailaddress_to_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );
        
        add_settings_field(
            'emailsubject', // ID
            'Subject of email', // Title 
            array( $this, 'emailsubject_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        
        add_settings_field(
            'emailmessage', // ID
            'Bodytext of email', // Title 
            array( $this, 'emailmessage_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        
        add_settings_field(
            'export_format', // ID
            'Export Format', // Title 
            array( $this, 'select_export_format_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        
        
        
        
        add_settings_field(
            'secret_token', // ID
            'Secrect token for direct export', // Title 
            array( $this, 'secret_token_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );      
        
        
        add_settings_field(
            'cron_enabled', // ID
            '&nbsp;', // Title 
            array( $this, 'enable_timely_callback' ), // Callback
            'fatcxe-admin', // Page
            'setting_section_id' // Section           
        );     
    }

/**
 * Sanitize each setting field as needed
 *
 * @param array $input Contains all settings fields as array keys
 * @since 1.0.0
 */

    public function sanitize( $input )
    {
        if (empty($input)) $input = array();
        $new_input = array();
        
        foreach( $input as $key=>$val )
           
            if ($key == 'export_period_start' && is_array($val) && isset($val['d']) && isset($val['t']) )
            {
                $new_input[$key] = strtotime($val['d']. ' '.$val['t']);
                
            }elseif ($key == 'post_status_to_export' && is_array($val) )
            {
                foreach($val as $v) $new_input[$key][] = sanitize_text_field($v);
            }elseif ($key == 'taxonomies_to_export' && is_array($val) )
            {
                foreach($val as $v) $new_input[$key][] = sanitize_text_field($v);
            }elseif ($key == 'fields_to_export' && is_array($val) )
            {
                foreach($val as $v) $new_input[$key][] = sanitize_text_field($v);
            }else {
                $new_input[$key] = sanitize_text_field($val);
            }
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        echo 'Manage your timely preferences below.';
    }

    /** 
     * Get the settings option array and print one of its values
     */

    public function enable_timely_callback()
    {
        $id = 'cron_enabled';
        $checked = isset( $this->options[$id]) && $this->options[$id] =='1' ?true:false;

        if ($checked) {$add_check = 'checked="checked"';}else {$add_check='';};        
        
        print '<label><input type="checkbox" name="fatcxe_options['.$id.']" value="1" '.$add_check.' />&nbsp;<strong>By checking this checkbox you enable the timed export via e-mail</strong></label>';
    }

    public function show_header_row_callback()
    {
        $id = 'add_header';
        $checked = isset( $this->options[$id]) && $this->options[$id] =='1' ?true:false;

        if ($checked) {$add_check = 'checked="checked"';}else {$add_check='';};        
        
        print '<label><input type="checkbox" name="fatcxe_options['.$id.']" value="1" '.$add_check.' />&nbsp;Show columnnames in first row</label>';
    }

    
    public function enable_filter_on_period_callback()
    {
        $id = 'filter_on_period';
        $checked = isset( $this->options[$id]) && $this->options[$id] =='1' ?true:false;

        if ($checked) {$add_check = 'checked="checked"';}else {$add_check='';};
        
        
        print '<label><input type="checkbox" name="fatcxe_options['.$id.']" value="1" '.$add_check.' />&nbsp;<strong>Only export data between timely period (selected above)</strong></label><br/><span class="description">Keep unchecked to export all data.</span>';
    }
    
    

    public function secret_token_callback()
    {
        $id = 'secret_token';
        $token = empty($this->options[$id])?md5(wp_salt()):$this->options[$id];

        print '<p><input type="secret_token" name="fatcxe_options['.$id.']" value="'.$token.'" style="width:300px"/><br/>
        <span class="description">You can enter any secrect token here. Just keep it difficult enough :-)</span></p>';
    }
    
    
    
    public function delimiter_callback()
    {
        $id = 'delimiter';
        $delimiter = empty($this->options[$id])?';':$this->options[$id];

        print '<p><input type="text" placeholder=";" name="fatcxe_options['.$id.']" value="'.$delimiter.'" style="width:20px"/></p>';
    }
    
    public function emailaddress_to_callback()
    {
        $id = 'emailaddress_to';
        $email = empty($this->options[$id])?get_option('admin_email'):$this->options[$id];

        print '<p><input type="email" placeholder="yourname@yourdomain.com" name="fatcxe_options['.$id.']" value="'.$email.'" style="width:300px"/></p>';
    }
    
    public function emailsubject_callback()
    {
        $id = 'emailsubject';
        $subject = empty($this->options[$id])?'Timely export':$this->options[$id];

        print '<p><input type="text" placeholder="e-mail subject" name="fatcxe_options['.$id.']" value="'.$subject.'" style="width:300px"/></p>';
    }
    
    
    public function emailmessage_callback()
    {
        $id = 'emailmessage';
        $message = empty($this->options[$id])?'This is your sceduled export.':$this->options[$id];

        print '<p><textarea name="fatcxe_options['.$id.']" style="min-width:300px;min-height:200px">'.$message.'</textarea></p>';
    }
    
    
    public function field2_select_post_type()
    {
        $id = 'post_type_to_export';   
        $value = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:'';
        
        //global $items;
        $args = array(
         //   'public'   => true,
         //   '_builtin' => true
        );
        // Get the field name from the $args array
        $output = 'names'; // names or objects, note names is the default
        $operator = 'and'; // 'and' or 'or'

        $post_types = get_post_types( $args, $output, $operator ); 

        
        // Get the value of this setting

        // echo a proper input type="text"
        echo '<div style="height:5px"></div>';
        foreach ($post_types as $key=>$item) {
            if (in_array($item,array('revision','nav_menu_item','acf-field-group','acf-field'))) continue;
            $checked = ($value == $item) ? ' checked="checked" ' : '';
            $custom = in_array($item,array('page','post'))?'wordpress': 'custom';
            // radio buttons, 1 post type per time
            echo '<div style="height:25px"><input type="radio" id="'.$id.$key.'" name="fatcxe_options['.$id.']" value="'.$item.'" '.$checked.'" />';
            echo '<label for="'.$id.$key.'"> '.$item.'</label> <span class="description" style="color:rgba(0,0,0,0.5);">'.$custom.'</span>';
            echo '</div>';
        }
    }

    public function select_export_period_callback()
    {
        $id = 'export_period';   
        $value = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:$this->periods[0][0];
        
        echo '</td></tr></tbody></table><hr/><table class="form-table"><tbody><tr><td colspan=2>';

        echo '<div style="height:5px"></div>';
        foreach ($this->periods as $key=>$item) {

            $checked = ($value == $item[0]) ? ' checked="checked" ' : '';

            echo '<div style="height:25px"><input type="radio" id="'.$id.$key.'" name="fatcxe_options['.$id.']" value="'.$item[0].'" '.$checked.'" />';
            echo '<label for="'.$id.$key.'"> '.$item[1].'</label>';
            echo '</div>';
        }
        echo '</td></tr></tbody></table><hr/><table class="form-table"><tbody><tr><td colspan=2>';
    }
    
    
    public function select_export_period_start_callback()
    {
        $id = 'export_period_start';   
        $value_d = isset( $this->options[$id]) && $this->options[$id]?date('Y-m-d',$this->options[$id]):date('Y-m-d');
        $value_t = isset( $this->options[$id]) && $this->options[$id]?date('H:i',$this->options[$id]):'7:00';
        

        echo '<div style="height:5px"></div>';
        echo '<div style="height:25px">
        <input type="date" name="fatcxe_options['.$id.'][d]" value="'.$value_d.'"/>
        <input type="time" name="fatcxe_options['.$id.'][t]" value="'.$value_t.'"/>';
        echo '<label> Select the day on which the timely export should start</label>';
        echo '</div>';
    }
        
    public function select_export_period_end_callback()
    {
        $id = 'export_period_end';   
        $value = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:date('Y-m-d',strtotime('+1 year'));
        

        echo '<div style="height:5px"></div>';
        echo '<div style="height:25px"><input type="date" name="fatcxe_options['.$id.']" value="'.$value.'"/>';
        echo '<label> Select the day on which the timely export should stop</label>';
        echo '</div>';
    }    
    

    public function field3_select_post_status()
    {
        $id = 'post_status_to_export';   
        $values = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:array('any');

        $stati = get_post_stati( null, 'names', 'or' );

        $total = count($stati)+1;
        echo '<select multiple="multiple" size="'.$total.'" style="min-width:300px;max-height:300px" name="fatcxe_options['.$id.'][]">';

        if($values[0] == 'any') {
            echo '\n\t<option selected="selected" value="any">any</option>';
        } else {
            echo '\n\t\<option value="any">any</option>';
        }

        foreach ($stati as $status) {
            if (in_array($status, $values)) {
                echo '\n\t<option selected="selected" value="'. $status . '">'.$status.'</option>';
            } else {
                echo '\n\t\<option value="'.$status .'">'.$status.'</option>';
            }
        }
    }

    public function field4_select_taxonomies() {

        $id = 'taxonomies_to_export';   
        $values = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:array();

        $selected_post_type = isset( $this->options['post_type_to_export']) && $this->options['post_type_to_export']?$this->options['post_type_to_export']:'';
        
            echo '<div id="no-taxonomies-to-display" style="display:none;height:25px;"><span class="description" style="color:rgba(0,0,0,0.5);padding-top:5px;display:inline-block">No available taxonomies for post type '.$selected_post_type.'</span></div>';

        echo '<select multiple="multiple" size="5" id="'.$id.'" style="min-width:300px;max-height:300px" name="fatcxe_options['.$id.'][]"></select>';
    }

    public function field5_select_fields()   {
        $selected_post_type = isset( $this->options['post_type_to_export']) && $this->options['post_type_to_export']?$this->options['post_type_to_export']:'';

        $id = 'fields_to_export';   
        $values = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:array();
        $selected_taxs = isset( $this->options['taxonomies_to_export']) && $this->options['taxonomies_to_export']?$this->options['taxonomies_to_export']:array();

        //$total = count($meta_keys);
        echo '<select multiple="multiple" size="15" id="'.$id.'" style="min-width:300px;max-height:450px" name="fatcxe_options['.$id.'][]"></select>';

        echo "<script>
jQuery(function($){
    function loadFields()
    {
        var fatcxe_post_type = $('input[name=\'fatcxe_options[post_type_to_export]\']:checked').val();

        $.getJSON( location.protocol + '//' + location.host + location.pathname+'?page=fatcxe-admin&json=fields&pt='+fatcxe_post_type, function( data ){
            $('#fields_to_export,#taxonomies_to_export').html('');
            var fatcxe_active_fields = ['".implode("','",$values)."'];
            var fatcxe_active_taxonomies = ['".implode("','",$selected_taxs)."'];
            $.each( data.fields, function( key, val ) {
                var selected = $.inArray(val,fatcxe_active_fields) > -1?'selected=\"selected\"':'';
                $('#fields_to_export').append('<option value=\"' + val + '\" '+selected+'>' + val + '</option>');
            });
            if (data.taxonomies.length ==0)
            {
                $('#no-taxonomies-to-display').show();            
                $('#taxonomies_to_export').hide();            
            }else {
                $('#taxonomies_to_export').show();            
                $('#no-taxonomies-to-display').hide();
                $.each( data.taxonomies, function( key, val ) {
                    var selected = $.inArray(val,fatcxe_active_taxonomies) > -1?'selected=\"selected\"':'';
                    $('#taxonomies_to_export').append('<option value=\"' + val + '\" '+selected+'>' + val + '</option>');
                });
            }
            
        });
    }
    
    $('input[name=\'fatcxe_options[post_type_to_export]\']').on('change',loadFields);
    
    loadFields();    
                
});
</script>";
    }  
    
    
    public function select_export_format_callback()
    {
        $id = 'export_format';   
        $value = isset( $this->options[$id]) && $this->options[$id]?$this->options[$id]:'xls';
        

        // echo a proper input type="text"
        echo '<div style="height:5px"></div>';
        foreach ($this->export_formats as $key=>$item) {

            $checked = ($value == $item[0]) ? ' checked="checked" ' : '';
            // radio buttons, 1 post type per time
            echo '<div style="height:25px"><input type="radio" id="'.$id.$key.'" name="fatcxe_options['.$id.']" value="'.$item[0].'" '.$checked.'" />';
            echo '<label for="'.$id.$key.'"> '.$item[1].'</label>';
            echo '</div>';
        }
    }
    
    
    
    
    
    
    
    function get_post_fields($post_type)
    {

        global $wpdb;
        $query = "
            SELECT DISTINCT($wpdb->postmeta.meta_key)
            FROM $wpdb->posts
            LEFT JOIN $wpdb->postmeta
            ON $wpdb->posts.ID = $wpdb->postmeta.post_id
            WHERE $wpdb->posts.post_type = '%s'
            AND $wpdb->postmeta.meta_key != ''
            AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'
          ";

        $meta_keys = $wpdb->get_col($wpdb->prepare($query, $post_type));

        
        $default_keys = array(
            'ID',
            'post_author',
            'post_date',
            'post_date_gmt',
            'post_content',
            'post_title',
            'post_excerpt,',
            'post_name',
            'permalink',
            'post_thumbnail'
        );
        
        $keys = array_merge($default_keys,$meta_keys);
        
        
        $taxs = get_object_taxonomies($post_type);

        $fields = (object) array('fields'=>$keys,'taxonomies'=>$taxs);
        
        $fields = apply_filters( 'fa_timely_fields', $fields );
        return $fields;
    }    
    

/**
 * Check for update of plugin.
 *
 * @since 1.0.0
 */
    public function update_check()
    {
        if (get_site_option( 'fatcxe_version' ) != FA_TCXE_VERSION) {
       
            
            $this->options = get_option( 'fatcxe_options' );
            
            
            /* Is this the first install, then set all defaults to active */
            if ($this->options === false)
            {
                $options = array(
                    'cron_enabled'              => '',
                    'emailaddress_to'           => get_option('admin_email'),
                    'emailsubject'              => 'Timely export',
                    'emailmessage'              => 'This is your sceduled export.',
                    'add_header'                => '1',
                    'filter_on_period'          => '1',
                    'delimiter'                 => ';',
                    'post_type_to_export'       => 'post',
                    'post_status_to_export'     => array('any'),
                    'taxonomies_to_export'      => array(),
                    'fields_to_export'          => array('post_title'),
                    'secret_token'              => md5(wp_salt()),
                    'export_period'             => $this->periods[0][0],
                    'export_period_start'       => strtotime('-1 day'),
                    'export_period_end'         => date('Y-m-d',strtotime('+1 year')),
                    'export_format'             => $this->export_formats[0][0],
                );
                
                update_option('fatcxe_options',$options);
                update_option('fatcxe_last_export','never');
                $this->options = $options;
            }
            

            /* UPDATE DONE! */
            update_site_option('fatcxe_version',FA_TCXE_VERSION);
        }
        
    }


/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 */
    static function fatcxe_activate_plugin(){
        if ( ! current_user_can( 'activate_plugins' ) )
        return;

        //create temp uploadfolder for attachment
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'].'/'.FA_TCXE_UPLOAD_DIR;
        if ( ! is_dir( $dir ) ) {
            if (!wp_mkdir_p( $dir )) 
            {
                $this->errors['upload_dir']->status = true;
            }else {
                @chmod ($dir,0760);
            }
        }

    }


/**
 * Plugin deactivation hook
 *
 * @since 1.0.0
 */

    static function fatcxe_deactivate_plugin()
    {
        //var_dump('deactivate');exit;
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        delete_option( 'fatcxe_options' );
        delete_option( 'fatcxe_version' );
        delete_option('fatcxe_next_export');
        delete_option('fatcxe_last_export');
        delete_option('fatcxe_exports_log');
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'].'/'.FA_TCXE_UPLOAD_DIR;
        if ( is_dir( $dir ) ) FATCXESettingsPage::delete_dir($dir);

    }
}
