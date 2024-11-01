<?php

if (!defined('ABSPATH')) die();

// Normal export
function fa_timely_exporter($options,$filter,$output=false){

    $filename = 'timely-export-'.date('Ymd_Hi').'-export';
        
    $filename = apply_filters( 'fatxce_filename', $filename );
    $filename .= '.'.$options['export_format'];
    
    
    // Query the DB for all instances of the custom post type
    $args = array(
        'post_type' => $options['post_type_to_export'],
        'post_status' => $options['post_status_to_export'],
        'posts_per_page' => -1,
        'order' => 'ASC',
    );
        
    
    if ($filter)
    {
        $args['date_query'] = array(
        array(
            'after'     => $filter->after,
            'before'    => $filter->before,
            'inclusive' => true
        ));
    }
    
    
    $fatcxe_data = get_posts($args);

    // Count the number of instances of the custom post type
    $fatcxe_count_posts = count($fatcxe_data);

    // Build an array of the custom field values
    $fatcxe_generate_value_arr = array();
    $i = 0;

    foreach ($fatcxe_data as $post): setup_postdata($post);

        $post->permalink = get_permalink($post->ID);
        $post->post_thumbnail = wp_get_attachment_url( get_post_thumbnail_id($post->ID) );

    
        foreach($post as $key => $value) {
            if(in_array($key, $options['fields_to_export'])) {
                // Prevent SYLK format issue
                if($key == 'ID') {

                    $fatcxe_generate_value_arr[strtolower($key)][$i] = $post->$key;
                } else {
                    $fatcxe_generate_value_arr[$key][$i] = $post->$key;
                }
            }
        }


        // get custom taxonomy information
        
        if(!empty($options['taxonomies_to_export']) ) {
            foreach($options['taxonomies_to_export'] as $tax) {
                $names = array();
                $terms = wp_get_object_terms($post->ID, $tax);

                if (!empty($terms)) {
                    if (!is_wp_error( $terms ) ) {
                        foreach($terms as $t) {
                            //echo $t->name;
                            $names[] = $t->name;
                        }
                    } else {
                        $names[] = '- error -';
                    }
                } else {
                    $names[] = '';
                }

                $fatcxe_generate_value_arr[$tax][$i] = implode(',', $names);
                //echo implode(',', $names);
            }
        }

        // get the custom field values for each instance of the custom post type
        if(count($options['fields_to_export']) > 0) {
            $fatcxe_generate_post_values = get_post_custom($post->ID);
            foreach ($options['fields_to_export'] as $key) {
                // check if each custom field value matches a custom field that is being exported
                if (array_key_exists($key, $fatcxe_generate_post_values)) {
                    // if the the custom fields match, save them to the array of custom field values
                    $fatcxe_generate_value_arr[$key][$i] = $fatcxe_generate_post_values[$key]['0'];
               }
            }
        }

        $i++;

    endforeach;

    /*
    
    APPLY CUSTOM FILTERS
    
    */
    
    $fatcxe_generate_value_arr = apply_filters( 'fatxce_before_format', $fatcxe_generate_value_arr );

    
    // create a new array of values that reorganizes them in a new multidimensional array where each sub-array contains all of the values for one custom post instance
    $fatcxe_generate_value_arr_new = array();

    foreach($fatcxe_generate_value_arr as $value) {
        $i = 0;
        while ($i <= ($fatcxe_count_posts-1)) {
            $fatcxe_generate_value_arr_new[$i][] = $value[$i];
            $i++;
        }
    }
    
    $fatcxe_generate_value_arr_new = apply_filters( 'fatxce_after_format', $fatcxe_generate_value_arr_new );
    
    $add_header = false;
    
    $header_keys = array_keys($fatcxe_generate_value_arr);            
    $header_keys = apply_filters( 'fatxce_header_keys', $header_keys );
    
    if (!$output)
    {
        
        $upload_dir = wp_upload_dir();
        $output_location = $upload_dir['basedir'].'/'.FA_TCXE_UPLOAD_DIR.'/'.$filename;
    }else {
        $output_location = 'php://output';
    }
    
/**
 * CSV output file.
 *
 * @since 1.0.0
 */
    

    if ($options['export_format'] == 'csv') {


        $fh = @fopen( $output_location, 'w' );


        foreach ( $fatcxe_generate_value_arr_new as $data ) {
            
            // Add a header row if it hasn't been added yet -- using custom field keys from first array
            if ( !empty($options['add_header']) && !$add_header ) {

                fputcsv($fh, $header_keys, $options['delimiter']);
                $add_header = true;
            }
            
            // Put the data from the new multi-dimensional array into the stream
            fputcsv($fh, $data, $options['delimiter']);
        }

        // Close the file stream
        fclose($fh);

        if ($output)
        {
        //output the headers for the CSV file
            header('Content-Encoding: UTF-8');
            header("Content-type: text/csv; charset=utf-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header('Content-Description: File Transfer');
            header("Content-Disposition: attachment; filename=".$filename);
            header("Expires: 0");
            header("Pragma: public");
        //echo "\xEF\xBB\xBF"; // UTF-8 BOM
            
            exit;
        }
        
    }

    
/**
 * Excell output file from PHPExcel library
 *
 * @since 1.1.0
 */
 

    if ($options['export_format'] == 'xls')
    {

        /** Include PHPExcel */
        require_once dirname(__FILE__) . '/../Classes/PHPExcel.php';


        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()->setCreator("Timely CSV-XLS export")
                             ->setTitle("Office 2007 XLSX Document");


        $objPHPExcel->setActiveSheetIndex(0);

        $startRow = 'A1';
                // Add header data
        if ( !empty($options['add_header']) && !$add_header ) {
            $objPHPExcel->getActiveSheet()
                ->fromArray(
                    $header_keys,   // The data to set
                    NULL,        // Array values with this value will not be set
                    $startRow         // Top left coordinate of the worksheet range where
            );

            $startRow = 'A2';
        }
        
        $objPHPExcel->getActiveSheet()
            ->fromArray(
                $fatcxe_generate_value_arr_new,   // The data to set
                NULL,        // Array values with this value will not be set
                $startRow         // Top left coordinate of the worksheet range where
        );

        if ($output)
        {
            // Redirect output to a client’s web browser (Excel5)
            header('Content-Type: application/vnd.ms-excel');
            header("Content-Disposition: attachment;filename=".$filename);
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');

            // If you're serving to IE over SSL, then the following may be needed
            header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
            header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header ('Pragma: public'); // HTTP/1.0

            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit;

        }else {
            //open the file stream
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save($output_location);
        }
    }
    

    
/**
 * Setup mail and attachment.
 *
 * @since 1.0.0
 */
    
    
    if (!$output)
    {
        return fatcxe_mail($options['emailaddress_to'],$options['emailsubject'],$options['emailmessage'],$output_location);
    }
}



function fatcxe_mail($email=false,$subject=false,$message,$attachment=false)
{
    if (!$email || !$attachment || !$subject) return false;

    $html  = "<html><body>\n<h3>".$subject."</h3>";
    $html  .= $message;
    $html .= "<br><br/><hr/>Attachment send by Timely CSV/XLS Exporter.\n";
    $html .= '</body></html>';


    $html = apply_filters( 'fatxce_mail_html', $html );
    
    $host = str_replace(array('https://','http://','www.'),'',$_SERVER['HTTP_HOST']);

    // SEND THE ATTACHMENT BY EMAIL
    add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));

    $headers = 'From: Timely CSV-XLS export plugin <no-reply@'.$host.'>' . "\r\n";
    $headers = apply_filters( 'fatxce_mailheaders', $headers );
    
    $send = wp_mail( $email, $subject, $html, $headers,$attachment );

    // Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
    remove_filter( 'wp_mail_content_type', 'set_html_content_type' );
    
    //remove attachment
    @unlink($attachment);
    $log = get_option('fatcxe_exports_log');
    update_option('fatcxe_exports_log',$log.'send mail '.date('Y-m-d H:m:s').' | '.$email.' | filename: '.basename($attachment)."\r\n");
    return $send;
}

