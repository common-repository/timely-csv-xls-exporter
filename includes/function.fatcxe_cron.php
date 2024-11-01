<?php

if (!defined('ABSPATH')) die();

function fatcxe_next_export_time($options=false)
{
    if (!$options) return false;

    $last_export = intval(get_option('fatcxe_last_export'));
    $next_export_time = get_option('fatcxe_next_export');

    $startdate = $options['export_period_start'];
    $enddate = strtotime($options['export_period_end']);
    
    if (empty($next_export_time)) $next_export_time = strtotime($options['export_period'],$startdate);
    for($i=0;$i<100;$i++)
    {
        if ($next_export_time < $last_export)
        {
            $next_export_time = strtotime($options['export_period'],$next_export_time);
        }else {
            $i = 9999;
        }
    }
    
    if ($next_export_time < $startdate || $next_export_time > $enddate) $next_export_time = false;
    return  $next_export_time;

}


function fatcxe_cron()
{
    
    $options = get_option('fatcxe_options');
    if (!isset($options) || empty($options['fields_to_export']) || empty($options['cron_enabled']) ) return false;

    
    $valid_token = false;
    $output = false;
    if (is_admin() && substr(basename($_SERVER['REQUEST_URI']),0,15) == 'options-general')
    {
        if (isset($_GET['fatcxe_token']) && $_GET['fatcxe_token'] == $options['secret_token'])
        {
            $valid_token = true;
            $output = true;
            if (isset($_GET['output']) && $_GET['output'] == 'send') $output = false;
            
        }else {
            return false;
        }
    }
    
    $next_export_time = fatcxe_next_export_time($options);
    
    if (!$valid_token && (!$next_export_time || $next_export_time > time())) return false;
    
    $filter = false;
    if (!empty($options['filter_on_period']))
    {
        $before = date('Y-m-d H:i:s',$next_export_time);
        $after = date('Y-m-d H:i:s',strtotime(str_replace('+','-',$options['export_period']),$next_export_time));
        
        $filter = (object) array(
            'after'=> $after,
            'before'=> $before
        );
    }
    
    
    // let's do this
    include_once( FA_TCXE_PLUGIN_DIR . '/includes/function.timely_exporter.php' );
    $result = fa_timely_exporter($options,$filter,$output);

    // this was a direct mail
    if ($valid_token && !$output) 
    {
        header ("location: ".get_site_url()."/wp-admin/options-general.php?page=fatcxe-admin&send=".($result?"1":"0"));
        exit;
    }
    
    update_option('fatcxe_next_export',intval($next_export_time));
    update_option('fatcxe_last_export',time());
    
}