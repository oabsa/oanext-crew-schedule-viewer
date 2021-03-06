<?php

/*
 * Plugin Name: OA NEXT Crew Schedule Viewer
 * Plugin URI: https://github.com/oabsa/oanext-crew-schedule-viewer
 * Description: WordPress plugin for displaying crew schedules for NEXT conference.
 * Version: 1.0
 * Author: Eric Silva (ericjsilva)
 * Author URI: http://oa-bsa.org
 * Author Email: ericjsilva@gmail.com
 * License: GPLv3
*/

/*
 * WordPress plugin for displaying crew schedules for NEXT conference.
 * Copyright (C) 2016 Order of the Arrow
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (!class_exists('OANextCrewScheduleViewer')) {

  //instantiate...
  add_action('plugins_loaded', array('OANextCrewScheduleViewer', 'init'));

  /**
   * Main plugin class.
   */
  class OANextCrewScheduleViewer
  {
    protected static $instance;
    private $page_slug = 'crew';
    private $sat_gsheet_url = 'https://spreadsheets.google.com/feeds/list/1YqBU4TaM8ub4cPCJYePDZGEsyxlprBqB2UCGh8luGdU/od6/public/values?alt=json';
    private $sun_gsheet_url = 'https://spreadsheets.google.com/feeds/list/1YqBU4TaM8ub4cPCJYePDZGEsyxlprBqB2UCGh8luGdU/2/public/values?alt=json';
    private $mon_gsheet_url = 'https://spreadsheets.google.com/feeds/list/1YqBU4TaM8ub4cPCJYePDZGEsyxlprBqB2UCGh8luGdU/3/public/values?alt=json';
    private $tue_gsheet_url = 'https://spreadsheets.google.com/feeds/list/1YqBU4TaM8ub4cPCJYePDZGEsyxlprBqB2UCGh8luGdU/4/public/values?alt=json';

    function __construct()
    {
      add_action('wp_enqueue_scripts', array(&$this, 'enqueueJavascript'));
      add_action('wp_enqueue_scripts', array(&$this, 'enqueueCss'));
      add_filter('query_vars', array(&$this, 'queryVariables'), 10);
      add_action('init', array(&$this, 'initRewrites'), 10, 0);
      add_action('parse_request', array(&$this, 'handleUrl'));
    }

    /**
     * hooked into plugins_loaded action : creates the plugin instance
     */
    public static function init()
    {
      is_null(self::$instance) && self::$instance = new self;
      return self::$instance;
    }

    function enqueueJavascript()
    {
      wp_register_style('oanextcrews-script', plugins_url('oanext.js', __FILE__));
      wp_enqueue_script('oanextcrews-script');
    }

    function enqueueCss()
    {
      wp_register_style('oanextcrews-style', plugins_url('style.css', __FILE__));
      wp_enqueue_style('oanextcrews-style');
    }

    function initRewrites()
    {
      add_rewrite_rule('^crew/([0-9]+)/?$', 'crew/?crew_id=' . $matches[1], 'top');
      flush_rewrite_rules(false);
    }

    function queryVariables($query_vars)
    {
      $query_vars[] = 'crew_id';
      return $query_vars;
    }

    function handleUrl(&$wp)
    {
      if ($wp->request == $this->page_slug) {
        # http://stackoverflow.com/questions/17960649/wordpress-plugin-generating-virtual-pages-and-using-theme-template
        # Note that we don't need to do a template redirect as suggesting in
        # the example because all we do is load the template anyway. We can let
        # the real template code work like it's supposed to and only override
        # the content.
        add_filter('the_posts', array(&$this, 'dummyPost'));
        remove_filter('the_content', 'wpautop');
      }
    }

    function dummyPost($posts)
    {
      // have to create a dummy post as otherwise many templates
      // don't call the_content filter
      global $wp, $wp_query;

      //create a fake post instance
      $p = new stdClass;
      // fill $p with everything a page in the database would have
      $p->ID = -1;
      $p->post_author = 1;
      $p->post_date = current_time('mysql');
      $p->post_date_gmt = current_time('mysql', $gmt = 1);
      $p->post_content = $this->renderPage($wp);
      $p->post_title = 'NEXT Crew Schedule';
      $p->post_excerpt = '';
      $p->post_status = 'publish';
      $p->ping_status = 'closed';
      $p->post_password = '';
      $p->post_name = $this->page_slug;
      $p->to_ping = '';
      $p->pinged = '';
      $p->modified = $p->post_date;
      $p->modified_gmt = $p->post_date_gmt;
      $p->post_content_filtered = '';
      $p->post_parent = 0;
      $p->guid = get_home_url('/' . $p->post_name); // use url instead?
      $p->menu_order = 0;
      $p->post_type = 'page';
      $p->post_mime_type = '';
      $p->comment_status = 'closed';
      $p->comment_count = 0;
      $p->filter = 'raw';
      $p->ancestors = array(); // 3.6

      // reset wp_query properties to simulate a found page
      $wp_query->is_page = TRUE;
      $wp_query->is_singular = TRUE;
      $wp_query->is_home = FALSE;
      $wp_query->is_archive = FALSE;
      $wp_query->is_category = FALSE;
      unset($wp_query->query['error']);
      $wp->query = array();
      $wp_query->query_vars['error'] = '';
      $wp_query->is_404 = FALSE;

      $wp_query->current_post = $p->ID;
      $wp_query->found_posts = 1;
      $wp_query->post_count = 1;
      $wp_query->comment_count = 0;
      // -1 for current_comment displays comment if not logged in!
      $wp_query->current_comment = null;
      $wp_query->is_singular = 1;

      $wp_query->post = $p;
      $wp_query->posts = array($p);
      $wp_query->queried_object = $p;
      $wp_query->queried_object_id = $p->ID;
      $wp_query->current_post = $p->ID;
      $wp_query->post_count = 1;

      return array($p);
    }

    function fetchGsheetJson($url)
    {
      // Get the body of the response
      $response = wp_remote_get($url);

      try {
        // Note that we decode the body's response since it's the actual JSON feed
        $json = json_decode($response['body']);
      } catch (Exception $ex) {
        $json = null;
      }

      return $json;
    }

    function renderPage(&$wp)
    {
      ob_start();
      $current_schedule_url = $this->mon_gsheet_url;
      $crew_id = get_query_var('crew_id');
      if (!empty($crew_id)) {
        $crew_id = trim(get_query_var('crew_id'));
      } elseif (!empty($_POST['crew_id'])) {
        $crew_id = trim($_POST['crew_id']);
      }
      $results_shown = false;
      if (strlen($crew_id) > 0) {
        // Remove hyphen from string, if exists.
        $crew_id = trim($crew_id, '-');
        // Force single numbers to zero-padded numbers
        if (preg_match('/^[0-9]{1}+$/', $crew_id)) {
          $crew_id = sprintf("%02d", $crew_id);
        }
        if (preg_match('/^[0-9]{2}+$/', $crew_id)) {
          $results = $this->fetchGsheetJson($current_schedule_url);

          if (empty($results)) {
            ?>
            <div class="oanext_crew_num_bad">
              <p>Error loading schedule.</p>
            </div>
            <?php
          } else {
            ?>
            <?php
            $day = $results->feed->title->{'$t'};
            $entries = $results->feed->entry;
            $crew_found = false;
            foreach ((array)$entries as $entry) {
              $crew_number = $entry->{'gsx$crew'}->{'$t'};
              if ($crew_number === $crew_id) {
                $crew_found = true;
                ?>
                <h4>Crew <?php echo htmlspecialchars($crew_id) ?> Schedule (<?php echo $day ?>)</h4>
                <div class="table-responsive">
                <table style="width:100%; " class="easy-table easy-table-default schedule" border="1">
                <thead>
                <tr>
                  <th style="width:25%;text-align:center">Time</th>
                  <th style="width:25%;text-align:center">Activity</th>
                  <th style="width:25%;text-align:center">Location</th>
                </tr>
                </thead>
                <tbody>
                <?php
                for ($i = 1; $i <= 23; $i++) {
                  $time = $entry->{'gsx$duration' . $i}->{'$t'};
                  $activity = $entry->{'gsx$activity' . $i}->{'$t'};
                  $location = $entry->{'gsx$location' . $i}->{'$t'};
                  echo "<tr>";
                  echo "<td style=\"text-align:center\">" . $time . "</td>";
                  echo "<td style=\"text-align:center\">" . $activity . "</td>";
                  echo "<td style=\"text-align:center\">" . $location . "</td>";
                  echo "</tr>";
                }
                $results_shown = true;
              }
            }
            if (!$crew_found) {
              ?>
              <div class="oanext_crew_num_bad">
                <p>Could not find a schedule for Crew number <?php echo htmlspecialchars($crew_id) ?>.</p>
              </div>
              <?php
            }
            ?>
            </tbody>
            </table>
            </div>
            <?php
          }
          ?>
          <div class="oanext_crew_num_entry">
            <p class="oanext_entry_inst">Check another Crew schedule:</p>
            <form method="POST" action="">
              <div class="oanext_input_group">
                <label for="crew_id">Crew #:</label> <input id="crew_id" name="crew_id" type="number" size="9"><input
                  type="submit" value="Go">
              </div>
            </form>
            <p class="oanext_help_inst">You can find your Crew number on the receipt you received at check-in. All crew
              numbers are two digits (e.g. 01, 02, 10, 23, ...)</p>
          </div>
          <?php
        } else {
          ?>
          <div class="oanext_crew_num_bad">
            <p>Could not find a schedule for Crew number <?php echo htmlspecialchars($crew_id) ?>.<br>Remember all crew
              numbers are two digits (e.g. 01, 02, 10, 23, ...)</p>
          </div>
          <?php
        }
      }
      $crew_id = get_query_var('crew_id');
      if ((!isset($crew_id) || !isset($_POST['crew_id'])) && !$results_shown) {
        ?>
        <div class="oanext_crew_num_entry">
          <p class="oanext_entry_inst">Enter your Crew number to check your schedule.</p>
          <form method="POST" action="">
            <div class="oanext_input_group">
              <label for="crew_id">Crew #:</label> <input id="crew_id" name="crew_id" type="number" size="9"><input
                type="submit" value="Go">
            </div>
          </form>
          <p class="oanext_help_inst">You can find your Crew number on the receipt you received at check-in. All crew
            numbers are two digits (e.g. 01, 02, 10, 23, ...)</p>
        </div>
        <?php
      }

      return ob_get_clean();
    }
  }
}