<?php

/************************************************************************************************************************************
 *
 * wbVoteTracker plugin for MantisBT
 * 2013 - David Hunt, Webuddha.com
 *
 ************************************************************************************************************************************/

  $t_current_user_id = auth_get_current_user_id();

  # Improve performance by caching category data in one pass
  category_get_all_rows( helper_get_current_project() );

  compress_enable();

  if ( current_user_get_pref( 'refresh_delay' ) > 0 )
    html_meta_redirect( plugin_page( 'browse' ), current_user_get_pref( 'refresh_delay' )*60 );

  $f_page_number = gpc_get_int( 'page_number', 1 );

  $t_per_page   = null;
  $t_bug_count  = null;
  $t_page_count = null;

  $t_project_id = helper_get_current_project();
  $t_filter = current_user_get_bug_filter();
  if( $t_filter === false || isset($_REQUEST['reset']) && $_REQUEST['reset'] == 'true' )
    $t_filter = filter_get_default();
  $t_filter['per_page'] = config_get( 'my_view_bug_count' );
  $t_per_page = $t_filter['per_page'];

  $t_sort = $t_filter['sort'];
  $t_dir  = $t_filter['dir'];

  $t_icon_path                      = config_get( 'icon_path' );
  $t_update_bug_threshold           = config_get( 'update_bug_threshold' );
  $t_bug_resolved_status_threshold  = config_get( 'bug_resolved_status_threshold' );
  $t_hide_status_default            = config_get( 'hide_status_default' );
  $t_default_show_changed           = config_get( 'default_show_changed' );
  $category_key                     = gpc_get_string( 'category', '' );
  $reporter_id                      = gpc_get_string_array( FILTER_SEARCH_REPORTER_ID, META_FILTER_ANY );

  $c_filter = $t_filter;
  if( strlen($category_key) )
    $c_filter[FILTER_PROPERTY_CATEGORY] = array( $category_key );
  else
    $category_key = $c_filter[FILTER_PROPERTY_CATEGORY][0];
  if( is_array($reporter_id) )
    $c_filter[FILTER_PROPERTY_REPORTER_ID] = $reporter_id;
  $c_filter[FILTER_PROPERTY_SORT_FIELD_NAME] = 'last_updated,vote_count,date_submitted';
  $c_filter[FILTER_PROPERTY_SORT_DIRECTION] = 'DESC,DESC,DESC';
  $c_filter['_view_type'] = 'advanced';

  $tc_setting_arr = filter_ensure_valid_filter( $c_filter );
  $t_settings_serialized = serialize( $tc_setting_arr );
  $t_settings_string = config_get( 'cookie_version' ) . '#' . $t_settings_serialized;
  $t_project_id = helper_get_current_project();
  $t_project_id = ( $t_project_id * -1 );
  $t_row_id = filter_db_set_for_current_user( $t_project_id, false, '', $t_settings_string );
  gpc_set_cookie( config_get( 'view_all_cookie' ), $t_row_id, time()+config_get( 'cookie_time_length' ), config_get( 'cookie_path' ) );

  $rows = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $c_filter );

  $t_category_name = preg_replace('/s$/','',$category_key);

  html_page_top( strlen($category_key) ? $category_key.' Browser' : 'Request Browser' );
  ?>
  <div class="searchRequests">
    <?php if(is_array($reporter_id)){ ?>
    <h3>Search Your <?php echo ((string)$category_key == (string)META_FILTER_ANY ? 'Requests' : $t_category_name.' Requests') ?></h3>
    <form action="search.php" method="get">
      <?php /* <input type="hidden" name="category" value="<?php echo $category_key ?>" />
      <input type="hidden" name="reporter_id[]" value="<?php echo $reporter_id[0] ?>" /> */ ?>
      <input type="text" name="search" value="" />
      <input type="submit" value=" Search " />
    </form>
    <?php } else { ?>
    <h3>Search <?php echo ((string)$category_key == (string)META_FILTER_ANY ? 'in All Requests' : 'in all '.$t_category_name.' Requests') ?></h3>
    <form action="search.php" method="get">
      <?php /* <input type="hidden" name="category" value="<?php echo $category_key ?>" />
      <input type="hidden" name="reporter_id[]" value="<?php echo $reporter_id[0] ?>" /> */ ?>
      <input type="text" name="search" value="" />
      <input type="submit" value=" Search " />
    </form>
    <?php } ?>
    <?php echo '<div class="button"><a href="view_all_bug_page.php"> Advanced Filters </a></div>'; ?>
  </div>
  <div class="requestListControl">
    <?php
      $v_start = 0;
      $v_end   = 0;
      if( count( $rows ) > 0 ) {
        $v_start = $t_filter['per_page'] * ($f_page_number - 1) + 1;
        $v_end = $v_start + count( $rows ) - 1;
      }
      echo '<div class="total">'. lang_get( 'viewing_bugs_title' ) . " ($v_start - $v_end / $t_bug_count)</div>";
      $f_filter = gpc_get_int( 'filter', 0);
      echo '<div class="links">';
      print_page_links( plugin_page( 'browse' ), 1, $t_page_count, (int)$f_page_number, $f_filter );
      echo '</div>';
    ?>
  </div>
  <div class="requestList">
    <?php if( !count($rows) ){ ?>
    <div class="empty_msg">There are no records to view</div>
    <?php } ?>
    <?php foreach( $rows AS $t_bug ){ ?>
    <div class="request">
      <?php
        $t_summary = string_display_line_links( $t_bug->summary );
        $t_last_updated = date( config_get( 'normal_date_format' ), $t_bug->last_updated );
        $status_color = get_status_color( $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
        $t_attachment_count = 0;
        if(( file_can_view_bug_attachments( $t_bug->id, null ) ) )
          $t_attachment_count = file_bug_attachment_count( $t_bug->id );
        $project_name = project_get_field( $t_bug->project_id, 'name' );

        // echo '<pre>'. print_r($t_bug,1) .'</pre>';
        echo '<div class="votes">';
          echo '<b>'. $t_bug->vote_count .'</b> <span>vote'. ($t_bug->vote_count != 1 ? 's' : '') .'</span>';
          if( !bug_is_readonly( $t_bug->id ) && !bug_is_resolved( $t_bug->id ) )
            echo wbVoteTrackerPlugin::html_button_vote( $t_bug->id, $t_bug, array('fields'=>array('mode' => 'quick')) );
        echo '</div>';
        echo '<div class="info">';

          echo '<div class="key">'; print_bug_link( $t_bug->id ); echo '</div>';

          echo '<div class="summary">';
            if( ON == config_get( 'show_bug_project_links' ) && helper_get_current_project() != $t_bug->project_id ) {
              echo '[', string_display_line( project_get_name( $t_bug->project_id ) ), '] ';
            }
            echo '<a href="view.php?id='.$t_bug->id.'">'.$t_summary.'</a>';
            // if( !bug_is_readonly( $t_bug->id ) && access_has_bug_level( $t_update_bug_threshold, $t_bug->id ) )
            //   echo '<a href="' . string_get_bug_update_url( $t_bug->id ) . '" class="update"><img border="0" src="' . $t_icon_path . 'update.png' . '" alt="' . lang_get( 'update_bug_button' ) . '" /></a>';
          echo '</div>';

          $cat_name = category_full_name( $t_bug->category_id, true, $t_bug->project_id );
          echo '<div class="category '.strtolower(preg_replace('/[^A-Za-z0-9\-\_]+/','',$cat_name)).'">';
          echo string_display_line( $cat_name );
          echo '</div>';

          $status_color = get_status_color( $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
          echo '<div class="status" style="background-color:'.$status_color.'">';
          printf( '<span class="issue-status" title="%1$s">%2$s</span>',
            get_enum_element( 'resolution', $t_bug->resolution, auth_get_current_user_id(), $t_bug->project_id ),
            get_enum_element( 'status', $t_bug->status, auth_get_current_user_id(), $t_bug->project_id )
          );
          if(( ON == config_get( 'show_assigned_names' ) ) && ( $t_bug->handler_id > 0 ) && ( access_has_project_level( config_get( 'view_handler_threshold' ), $t_bug->project_id ) ) ) {
            printf( ' (%s)', prepare_user_name( $t_bug->handler_id ) );
          }
          echo '</div>';

          echo '<div class="stats">';
            echo (int)$t_bug->bugnotes_count . ' Note' . ((int)$t_bug->bugnotes_count == 1 ? '' : 's');
            echo ' | ';
            echo (int)$t_bug->attachment_count . ' File' . ((int)$t_bug->attachment_count == 1 ? '' : 's');
          echo '</div>';

          echo '<br/>';
          echo '<div class="priority">';
          print_formatted_priority_string( $t_bug );
          echo '</div>';

          echo '<div class="created">Posted by ';
          if( $t_bug->reporter_id > 0 )
            echo prepare_user_name( $t_bug->reporter_id );
          echo '</div>';

          echo '<div class="updated">Updated ';
          if( $t_bug->last_updated > strtotime( '-' . $t_filter[FILTER_PROPERTY_HIGHLIGHT_CHANGED] . ' hours' ) ) {
            echo '<b>' . $t_last_updated . '</b>';
          } else {
            echo $t_last_updated;
          }
          echo '</div>';

        echo '</div>';
      ?>
      <div class="clr"></div>
    </div>
    <?php } ?>
  </div>
  <div class="requestListControl">
    <?php
      $v_start = 0;
      $v_end   = 0;
      if( count( $rows ) > 0 ) {
        $v_start = $t_filter['per_page'] * ($f_page_number - 1) + 1;
        $v_end = $v_start + count( $rows ) - 1;
      }
      echo '<div class="total">'. lang_get( 'viewing_bugs_title' ) . " ($v_start - $v_end / $t_bug_count)</div>";
      echo '<div class="button"><a href="search.php?category='.$category_key.'">Advanced Filter</a></div>';
      $f_filter = gpc_get_int( 'filter', 0);
      echo '<div class="links">';
      print_page_links( plugin_page( 'browse' ), 1, $t_page_count, (int)$f_page_number, $f_filter );
      echo '</div>';
    ?>
  </div>
  <?php

  html_page_bottom();