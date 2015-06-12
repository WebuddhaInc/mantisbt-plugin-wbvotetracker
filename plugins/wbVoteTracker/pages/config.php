<?php

/************************************************************************************************************************************
 *
 * wbVoteTracker plugin for MantisBT
 * 2013 - David Hunt, Webuddha.com
 *
 ************************************************************************************************************************************/

html_page_top( lang_get( 'plugin_wbvotetracker_header_config' ) );

$t_logged_in_user_id = auth_get_current_user_id();
if ( user_is_anonymous( $t_logged_in_user_id ) ) {

  echo '<br /><div class="center">';
  echo 'Permission Denied.<br />';
  print_bracket_link( 'view.php?id='.$f_bug_id, lang_get( 'proceed' ) );
  echo '</div>';

}
else {

  $config = array(
    'filterCategories' => plugin_config_get( 'filterCategories', array() )
    );

  ?>
  <form class="" action="<?php echo plugin_page( 'config_save' ) ?>" method="post">
    <?php echo form_security_field( 'config' ) ?>
    <fieldset>
      <legend><?php echo lang_get('plugin_wbvotetracker_header_config') ?></legend>
      <div class="field">
        <label>Categories to Display</label><br/>
        <select name="config[filterCategories][]" multiple>
          <?php echo print_category_option_list( $config['filterCategories'] ); ?>
        </select>
      </div>
      <br/>
      <input type="submit" value="Save" />
    </fieldset>
  </form>
  <?php

}

html_page_bottom();