<?php
/*
Plugin Name: To-Do List
Description: Simple to-do list plugin for WordPress.
Version: 1.1
Author: Sabin Thapa Magar
*/

// Enqueue styles for the plugin


// Add a menu in the admin panel to edit the to-do list
add_action('admin_menu', 'todo_list_admin_menu');
add_action('wp_enqueue_scripts', 'todo_list_enqueue_styles');
add_action('wp_head', 'todo_list_custom_styles');
add_action('wp_ajax_get_users_by_user_type', 'get_users_by_user_type');
add_action('wp_ajax_grant_admin_access', 'grant_admin_access');
add_action('wp_ajax_remove_admin_access', 'remove_admin_access');

add_action('init', function () {
    $role = get_role('administrator'); // Get the administrator role object
    $role->add_cap('edit_to_do_list'); // Add the 'edit_to_do_list' capability to the administrator role
});
add_action('wp_ajax_mark_task_complete', 'mark_task_complete');
add_action('wp_ajax_opt_out_task', 'opt_out_task');
add_action('admin_init', 'todo_list_admin_handle_form');

add_shortcode('todo_list', 'todo_list_shortcode');


//Change For USER END Side Shortcode
function todo_list_shortcode_handler() {
    // Start output buffering to capture the content of the admin page
    ob_start();
    todo_list_admin_page();
    return ob_get_clean();
}
function register_todo_list_shortcode() {
    add_shortcode('todo_list_user', 'todo_list_shortcode_handler');
}
add_action('init', 'register_todo_list_shortcode');
//Change For USER END Side Shortcode

function todo_list_custom_styles()
{
    ?>
<style>
.todo-list {
    max-width: 600px;
    margin-top: 20px;
}

label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.todo-list button {
    margin-left: 10px;
}

.todo-list .remove-button {
    margin-left: 20px;
}

.todo-list .row-spacing {
    margin-bottom: 20px;
}

.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.category-list li {
    margin-bottom: 10px;
}

.filter-options {
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>
<?php
}

function todo_list_admin_menu()
{
    add_menu_page('To-Do List', 'To-Do List', 'edit_posts', 'todo-list-admin', 'todo_list_admin_page');
}

function todo_list_enqueue_styles()
{
    wp_enqueue_style('todo-list-style', plugin_dir_url(__FILE__) . 'style.css');
}

// AJAX handler to get users by user type
function grant_admin_access()
{
    check_ajax_referer('todo-nonce', 'nonce');

    if (!current_user_can('administrator')) {
        wp_die('Unauthorized access');
    }

    // Get the selected user IDs from the request data
    $selected_user_ids = explode(',', sanitize_text_field($_POST['selected_users']));

    // Loop through each user ID and grant them the administrator role
    foreach ($selected_user_ids as $user_id) {
        $user = new WP_User($user_id);
        $user->add_role('administrator'); // Replace 'administrator' with the desired role
    }

    wp_die('Admin access granted successfully'); // You can return a JSON object with more information
}

function remove_admin_access()
{
    // Check nonce for security
    check_ajax_referer('todo-nonce', 'nonce');

    if (!current_user_can('administrator')) {
        wp_die('Unauthorized access');
    }

    // Get the selected user IDs from the request data
    $selected_user_ids = explode(',', sanitize_text_field($_POST['selected_users']));

    // Loop through each user ID and remove the administrator role
    foreach ($selected_user_ids as $user_id) {
        $user = new WP_User($user_id);
        $user->remove_role('administrator'); // Remove the 'administrator' role from the user
    }

    wp_die('Admin access removed successfully');
}


function get_users_by_user_type()
{
    check_ajax_referer('todo-nonce', 'nonce');

    if (isset($_POST['user_type'])) {
        $selected_user_type = sanitize_text_field($_POST['user_type']);
        $users = get_users(array('role' => $selected_user_type));

        $html = '';
        foreach ($users as $user) {
            $html .= '<tr>';
            $html .= '<td><input type="checkbox" class="user-checkbox" name="selected_users[]" value="' . esc_attr($user->ID) . '"></td>';
            $html .= '<td>' . esc_html($user->ID) . '</td>';
            $html .= '<td>' . esc_html($user->display_name) . '</td>';
            $html .= '<td>' . esc_html($user->user_email) . '</td>';
            $html .= '</tr>';
        }

        echo $html;
    }
    // AJAX handler for marking a task as complete
    function mark_task_complete()
    {
        $task_id = $_POST['task_id'];
        update_post_meta($task_id, 'todo_completed', true);
        wp_die();
    }

    // AJAX handler for opting out of a task
    function opt_out_task()
    {
        $task_id = $_POST['task_id'];

        // Assume $task_id is the ID of the task being opted out
        update_post_meta($task_id, 'todo_opt_out', true);
        update_post_meta($task_id, 'todo_completed', false); // Make sure to set completed to false

        wp_die();
    }

    die();
}

function arrow_indicator($order)
{
    return ($order === 'ASC') ? ' &#9660;' : ' &#9650;';
}

// Handle form submission to update the to-do list
function todo_list_admin_handle_form()
{
    $is_shortcode_admin = isset($_POST['todo_shortcode_admin']) && $_POST['todo_shortcode_admin'] == 1;

    if (isset($_POST['todo-add-task']) && !$is_shortcode_admin) {
        if (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
            foreach ($_POST['selected_users'] as $user_id) {
                $user_id = absint($user_id); // Sanitize the user ID

                // Process the form submission for adding or updating a task
                $task_name = sanitize_text_field($_POST['todo-task-name']);
                $task_link = isset($_POST['todo_task_link']) ? $_POST['todo_task_link'] : '';
                $task_categories = isset($_POST['todo-category-option']) ? $_POST['todo-category-option'] : array();
                $task_user = $user_id;

                // Check if a task ID is provided for updating
                if (isset($_POST['todo_task_id']) && $_POST['todo_task_id'] !== '') {
                    $task_id = absint($_POST['todo_task_id']);
                    // Update the existing task
                    save_todo_task(
                        array(
                            'name' => $task_name,
                            'todo_task_link' => $task_link,
                            'categories' => $task_categories,
                            'user' => $task_user,
                        ),
                        $task_id
                    );
                } else {
                    // Save the task data
                    $task_data = array(
                        'name' => $task_name,
                        'todo_task_link' => $task_link,
                        'categories' => $task_categories,
                        'user' => $task_user,
                        'completed' => false, // Initialize as not completed
                    );

                    // Save the task as a custom post type
                    save_todo_task($task_data);
                }
            }
        }
    }
    // Handle task removal only if the user is an administrator or if the shortcode admin context is set
    if ((isset($_POST['todo_remove_task']) && current_user_can('administrator')) || $is_shortcode_admin) {
        $task_id = absint($_POST['todo_task_id']);
        delete_todo_task($task_id);
    }

    if (isset($_POST['todo_mark_complete_task']) && !$is_shortcode_admin) {
        $task_id_to_complete = absint($_POST['todo_mark_complete_task']);
        update_post_meta($task_id_to_complete, 'todo_completed', true);
    }

    if (isset($_POST['todo_opt_out_task']) && !$is_shortcode_admin) {
        $task_id_to_opt_out = absint($_POST['todo_opt_out_task']);
        update_post_meta($task_id_to_opt_out, 'todo_opt_out', true);
        update_post_meta($task_id, 'todo_completed', false);
    }
}

function save_todo_task($task_data, $task_id = null)
{
 
    if ($task_id) {
        // Update the existing post with new data
        $task_args = array(
            'ID' => $task_id,
            'post_title' => $task_data['name'],
        );
        wp_update_post($task_args);
    } else {
        // If $task_id is not provided, it means we are saving a new task
        $task_args = array(
            'post_title' => $task_data['name'],
            'post_type' => 'todo_task', // Replace with your custom post type name
            'post_status' => 'publish',
        );
        $task_id = wp_insert_post($task_args);
    }

    if ($task_id) {
        // Save or update additional task data as post meta
        update_post_meta($task_id, 'todo_categories', $task_data['categories']);
        update_post_meta($task_id, 'todo_assigned_user', $task_data['user']);
        update_post_meta($task_id, 'todo_due_date', sanitize_text_field($_POST['todo-task-due-date']));
        update_post_meta($task_id, 'todo_due_time', sanitize_text_field($_POST['todo-task-due-time']));
        update_post_meta($task_id, 'todo_category', sanitize_text_field($_POST['todo-task-category']));
        update_post_meta($task_id, 'todo_urgency', sanitize_text_field($_POST['todo-task-urgency']));
        update_post_meta($task_id, 'todo_time_frame', sanitize_text_field($_POST['task_time_frame']));
        update_post_meta($task_id, 'todo_task_link', $task_data['todo_task_link']);
        update_post_meta($task_id, 'todo_completed', $task_data['Completed']);
        update_post_meta($task_id, 'todo_opt_out', $task_data['Opt_out']);
        update_post_meta($task_id, 'todo_incomplete', $task_data['Incomplete']);
        update_post_meta($task_id, 'todo_task_description', sanitize_text_field($_POST['todo-task-description']));
        update_post_meta($task_id, 'todo_assigned_user_type', sanitize_text_field($_POST['todo-task-user-type']));

        // Send email notification to the assigned user
        send_task_assignment_email($task_id, $task_data['user']);
    }
}
// Function to send task assignment email
function send_task_assignment_email($task_id, $assigned_user_id)
{
    $task_title = get_the_title($task_id);
    $assigned_user = get_userdata($assigned_user_id);
    $assigned_user_email = $assigned_user->user_email;
    $subject = 'Task Assignment: ' . $task_title;
    $message = 'You have been assigned a new task: ' . $task_title;

    // Replace with your email sender detailsFupda
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Send the email
    wp_mail($assigned_user_email, $subject, $message, $headers);
}


// Delete a task
function delete_todo_task($task_id)
{
    wp_delete_post($task_id, true); // Setting the second parameter to true to permanently delete the post
}

// Fetch and display the to-do list tasks based on selected options and filters
// Modify the get_todo_list_tasks function


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['todo-filter-tasks'])) {

    $selected_categories = $_POST['todo-category-option'];
    $selected_user = '';
    $current_user_id = get_current_user_id();
    $user_role = 'administrator';
    $selected_status = $_POST['todo-status-option'];
    $selected_urgency = $_POST['todo-urgency-option'];
    $selected_due_date = $_POST['todo-due-date-option'];
    $selected_deadline = $_POST['todo-deadline-option'];
    wp_reset_postdata();
    return $tasks;
}

function get_todo_list_tasks($selected_categories, $selected_user, $current_user_id, $user_role, $selected_status, $selected_urgency,$selected_due_date, $selected_deadline)
{
    $tasks = array();
    // Fetch tasks from a custom post type
    $args = array(
        'post_type' => 'todo_task', // Replace with your custom post type name
        'posts_per_page' => -1, // Retrieve all tasks
        'meta_query' => array(),
        'orderby' => 'ID', // Default sorting by Task ID
        'order' => 'DESC', // Default order is descending
    );

    if ($selected_user != 'all') {
        $args['meta_query'][] = array(
            'key' => 'todo_assigned_user',
            'value' => $selected_user,
            'compare' => '='
        );
    } elseif ($user_role != 'administrator') {
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key' => 'todo_assigned_user',
                'value' => $current_user_id,
                'compare' => '='
            ),
            array(
                'key' => 'todo_assigned_user',
                'compare' => 'NOT EXISTS'
            ),
        );
    }

    if (!empty($selected_due_date)) {
        $args['meta_query'][] = array(
            'key' => 'todo_due_date',
            'value' => $selected_due_date,
            'compare' => '=',
            'type' => 'DATE',
        );
    }

    if ($selected_deadline === 'ascending') {
        $args['meta_key'] = 'todo_due_date';
        $args['orderby'] = 'meta_value';
        $args['order'] = 'ASC';
    } elseif ($selected_deadline === 'descending') {
        $args['meta_key'] = 'todo_due_date';
        $args['orderby'] = 'meta_value';
        $args['order'] = 'DESC';
    }
    
    // print_r($selected_urgency);
    if (!empty($selected_urgency) && $selected_urgency != 'all') {
        $args['meta_query'][] = array(
            'key' => 'todo_urgency',
            'value' => $selected_urgency,
            'compare' => '='
        );
    }
    // print_r($tasks);
    $query = new WP_Query($args);



    // Loop through the tasks and build the array
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();


            $assigned_user = get_post_meta(get_the_ID(), 'todo_assigned_user', true);

            // Check if the current user has the capability to view the task
            if ($user_role === 'administrator' || $assigned_user == $current_user_id) {
                // Check if the assigned user matches the current user's ID or if the user is an administrator
                if ($assigned_user == $current_user_id || $user_role === 'administrator') {
                    $tasks[] = array(
                        'id' => get_the_ID(),
                        'name' => get_the_title(),
                        'description' => get_post_meta(get_the_ID(), 'todo_task_description', true),
                        'user_name' => get_userdata($assigned_user)->display_name,
                        'todo_task_link' => get_post_meta(get_the_ID(), 'todo_task_link', true),
                        'user_type' => get_post_meta(get_the_ID(), 'todo_assigned_user_type', true), // Fetch the user type
                        'due_date' => get_post_meta(get_the_ID(), 'todo_due_date', true),
                        'due_time' => get_post_meta(get_the_ID(), 'todo_due_time', true),
                        'timeframe' => get_post_meta(get_the_ID(), 'todo_time_frame', true),
                        'category' => get_post_meta(get_the_ID(), 'todo_category', true),
                        'urgency' => get_post_meta(get_the_ID(), 'todo_urgency', true),

                        'status' => get_post_meta(get_the_ID(), 'todo_opt_out', true) ?
                            'Opted Out' : (get_post_meta(get_the_ID(), 'todo_completed', true) ? 'Completed' : 'Incomplete'),
                        'deadline' => get_post_meta(get_the_ID(), 'todo_due_date', true),
                    );
                }
            }
        }
    }
   
    //  echo '<pre>';
    // print_r($tasks);
    //  echo '<pre>';
        // print_r($selected_urgency);
            //   echo '<pre>';
            // print_r($tasks);

    $filtered_array = array();
    foreach ($tasks as $item) {
        if ($selected_status === 'incomplete' && ($item['status'] === 'Incomplete' || $item['status'] === null)) {
            $filtered_array[] = $item;
            $tasks = $filtered_array;
        } else if ($selected_status === 'completed' && ($item['status'] === 'Completed' || $item['status'] === null)) {
            $filtered_array[] = $item;
            $tasks = $filtered_array;
        } else if ($selected_status === 'opt_out' && ($item['status'] === 'Opted Out' || $item['status'] === null)) {
            $filtered_array[] = $item;
            $tasks = $filtered_array;
        }
    }
    // $filtered_array_2 = array();
    // //    echo '<pre>';
    //         print_r($selected_urgency);
    // foreach ($tasks as $item) {
    //     if ($selected_urgency === 'high' && ($item['urgency'] === 'high' || $item['urgency'] === null)) {
    //         $filtered_array[] = $item;
    //         $tasks = $filtered_array;
    //     } else if ($selected_urgency === 'low' && ($item['urgency'] === 'low' || $item['urgency'] === null)) {
    //         $filtered_array[] = $item;
    //         $tasks = $filtered_array;
    //     } else if ($selected_urgency === 'medium' && ($item['urgency'] === 'medium' || $item['urgency'] === null)) {
    //         $filtered_array[] = $item;
    //         $tasks = $filtered_array;
    //     }
    // }
    $filtered_array_3 = array();

            //   echo '<pre>';
            // print_r($tasks);
    foreach ($tasks as $item) {
        if ($selected_categories === 'human-resources' && ($item['category'] === 'human-resources' || $item['category'] === null)) {
            $filtered_array[] = $item;
            $tasks = $filtered_array;
        } else if ($selected_categories === 'financial' && ($item['category'] === 'financial' || $item['category'] === null)) {
            $filtered_array[] = $item;
            $tasks = $filtered_array;
        } else if ($selected_categories === 'bill' && ($item['category'] === 'bill' || $item['category'] === null)) {
            $filtered_array[] = $item;
            $tasks = $filtered_array;
        }
    }

    wp_reset_postdata();
    return $tasks;
}



/*   ADMIN SIDE START   */
function todo_list_admin_page()
{
    global $post;

    global $edit_data;

    $is_admin = current_user_can('administrator');
    if ( ! function_exists( 'get_editable_roles' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    $roles = get_editable_roles();
    foreach ($roles as $role => $data) {
        if ($role !== 'administrator') {
        }
    }
    $selected_category = isset($_POST['todo-category-option']) ? sanitize_text_field($_POST['todo-category-option']) : '';
    $selected_user = isset($_POST['todo-user-option']) ? $_POST['todo-user-option'] : 'all';
    $selected_status = isset($_POST['todo-status-option']) ? sanitize_text_field($_POST['todo-status-option']) : 'all';
    $selected_urgency = isset($_POST['todo-urgency-option']) ? sanitize_text_field($_POST['todo-urgency-option']) : 'all';
    $selected_due_date = isset($_POST['todo-due-date-option']) ? sanitize_text_field($_POST['todo-due-date-option']) : '';
    $selected_deadline = isset($_POST['todo-deadline-option']) ? sanitize_text_field($_POST['todo-deadline-option']) : '';
    $selected_user_type = isset($_POST['todo-user-type-option']) ? $_POST['todo-user-type-option'] : 'all';
    $sorting_order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
    $time_frame = get_post_meta($post->ID, 'task_time_frame', true);

    ?>

<?php

    // Check if 'edit' action is triggered
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['task'])) {
        $encoded_task = $_GET['task'];
        $task_array = unserialize(base64_decode(urldecode($encoded_task)));
        $edit_data = $task_array;

   }
    ?>

<div class="wrap">

    <h1>To-Do List - Admin</h1>

    <form method="post" action="">
        <h2>Add Task</h2>

        <label for="todo-task-name">Task Name:</label>
        <input type="text" id="todo-task-name" name="todo-task-name"
            value="<?php echo isset($edit_data[0]['name']) ? esc_attr($edit_data[0]['name']) : ''; ?>" />
        <br><br>
        <label for="task-link">Task Link:</label>
        <input type="text" id="todo_task_link" name="todo_task_link"
            value="<?php echo isset($edit_data[0]['todo_task_link']) ? esc_attr($edit_data[0]['todo_task_link']) : ''; ?>" />
        <br> <br>
        <label for="todo-task-description">Task Description:</label>
        <input type="text" id="todo-task-description" name="todo-task-description"
            value="<?php echo isset($edit_data[0]['description']) ? esc_attr($edit_data[0]['description']) : ''; ?>" />
        <br><br>
        <label for="todo-task-due-date">Due Date:</label>
        <input type="date" id="todo-task-due-date" name="todo-task-due-date"
            value="<?php echo isset($edit_data[0]['due_date']) ? esc_attr($edit_data[0]['due_date']) : ''; ?>" /><br><br>

        <label for="todo-task-due-time">Due Time:</label>
        <input type="time" id="todo-task-due-time" name="todo-task-due-time"
            value="<?php echo isset($edit_data[0]['due_time']) ? esc_attr($edit_data[0]['due_time']) : ''; ?>" />
        <br><br>


        <label for="todo-task-urgency">Urgency:</label>
        <select id="todo-task-urgency" name="todo-task-urgency">
        <option value="select">Select</option>
            <option value="low"
                <?php echo isset($edit_data[0]['urgency']) && $edit_data[0]['urgency'] == 'low' ? 'selected' : ''; ?>>
                Low</option>
            <option value="medium"
                <?php echo isset($edit_data[0]['urgency']) && $edit_data[0]['urgency'] == 'medium' ? 'selected' : ''; ?>>
                Medium</option>
            <option value="high"
                <?php echo isset($edit_data[0]['urgency']) && $edit_data[0]['urgency'] == 'high' ? 'selected' : ''; ?>>
                High</option>
        </select>
        <br><br>
        <label for="todo-task-user-type">Assign to User Type:</label>
        <select id="todo-task-user-type" name="todo-task-user-type">
        <option value="select">Select</option>
            <?php
                $roles = get_editable_roles();
                foreach ($roles as $role => $data) {
                    if ($role !== 'administrator') {
                        $selected = isset($edit_data[0]['user_type']) && $edit_data[0]['user_type'] === $role ? 'selected' : '';
                        echo '<option value="' . esc_attr($role) . '" ' . $selected . '>' . ucfirst($role) . '</option>';
                    }
                }
                ?>
        </select>
        <button type="button" class="button" id="select-users">Select</button>
        <br><br>
        <label for="task_time_frame">Time Frame (in hours):</label>
        <input type="text" id="task_time_frame" name="task_time_frame"
            value="<?php echo isset($edit_data[0]['timeframe']) ? esc_attr($edit_data[0]['timeframe']) : ''; ?>">
        <br><br>


        <label for="todo-task-category">Task Category:</label>
        <select id="todo-task-category" name="todo-task-category">
            <option value="select">Select</option>
            <option value="human-resources"
                <?php echo isset($edit_data[0]['category']) && $edit_data[0]['category'] == 'human-resources' ? 'selected' : ''; ?>>
                Human Resources</option>
            <option value="financial"
                <?php echo isset($edit_data[0]['category']) && $edit_data[0]['category'] == 'financial' ? 'selected' : ''; ?>>
                Financial</option>
            <option value="bill"
                <?php echo isset($edit_data[0]['category']) && $edit_data[0]['category'] == 'bill' ? 'selected' : ''; ?>>
                Bill</option>


        </select>
        <br><br>


        <h2>Assign to Users:</h2>
        <table class="wp-list-table widefat fixed striped user-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-users" name="selected_users[]">Select All Users</th>
                    <th>User ID</th>
                    <th>User Name</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody id="user-table-body">
                <!-- Users will be populated dynamically here -->
            </tbody>
        </table><br>

        <input type="submit" class="button button-primary" name="todo-add-task" value="<?php
            echo isset($edit_data) && is_array($edit_data) && count($edit_data) > 0 ? "Update Task" : "Add Task";
            ?>" />
        <br><br>
    </form>

    <form method="post" action="">
        <a href="wp-admin/admin.php?page=todo-list-admin" id="grant-admin-access-btn">
            <button>Grant Admin Access</button>
        </a>
    </form>
    <br>
    <form method="post" action="">
        <a href="#" id="remove-admin-access-btn">
            <button>Remove Admin Access</button>
        </a>
    </form>
    <?php
        // Handle form submission to clear all tasks
        if (isset($_POST['todo_clear_all_tasks']) && $is_admin) {
            // $all_tasks = get_todo_list_tasks('', 'all', get_current_user_id(), 'administrator');
            $all_tasks = get_todo_list_tasks('', 'all', 1, 'administrator', $selected_status,$selected_urgency, $selected_due_date, $selected_deadline);
            ;

            echo '<div class="updated"><p>All tasks have been cleared.</p></div>';
            foreach ($all_tasks as $task) {
                delete_todo_task($task['id']);
            }

        }
        ?>

    <!-- Add the rest of your HTML code (Add Task form, Clear All Tasks button, etc.) here -->

    <?php
        if (isset($_GET['action'])) {
            $action = sanitize_text_field($_GET['action']);
            $task_id = absint($_GET['id']);

            if ($action === 'edit') {
                wp_redirect("?page=todo-list-admin&action=edit&id=$task_id");
                exit();
            } elseif ($action === 'delete') {
                delete_todo_task($task_id);
                echo '<div class="updated"><p>Task has been deleted.</p></div>';
            }
        }
    ?>


    <h2>Filter Tasks</h2>
    <div>
        <form method="post" action="">
            <div class="filter-options">
                <label for="todo-status-option">Status:</label>
                <select id="todo-status-option" name="todo-status-option">
                    <option value="all" <?php selected($selected_status, 'all'); ?>>All</option>
                    <option value="completed" <?php selected($selected_status, 'completed'); ?>>Completed</option>
                    <option value="incomplete" <?php selected($selected_status, 'incomplete'); ?>>Incomplete</option>
                    <option value="opt_out" <?php selected($selected_status, 'opt_out'); ?>>Opted Out</option>
                </select>

                <label for="todo-due-date-option">Due Date:</label>
                <input type="date" id="todo-due-date-option" name="todo-due-date-option"
                    value="<?php echo esc_attr($selected_due_date); ?>" />

                <label for="todo-urgency-option">Priority:</label>
                <select id="todo-urgency-option" name="todo-urgency-option">
                    <option value="" <?php selected($selected_urgency, 'all'); ?>>All</option>
                    <option value="high" <?php selected($selected_urgency, 'high'); ?>>High</option>
                    <option value="low" <?php selected($selected_urgency, 'low'); ?>>Low</option>
                    <option value="medium" <?php selected($selected_urgency, 'medium'); ?>>Medium</option>
                </select>

                <label for="todo-category-option">Task Category:</label>
                <select id="todo-category-option" name="todo-category-option">

                    <option value="select">Select</option>
                    <option value="human-resources" <?php selected( $selected_category, 'human-resources'); ?>>Human
                        Resources</option>
                    <option value="financial" <?php selected( $selected_category, 'financial'); ?>>Financial</option>
                    <option value="bill" <?php selected( $selected_category, 'bill'); ?>>Bill</option>



                </select>

            </div><br>
            <input type="submit" class="button button-primary" name="todo-filter-tasks" value="Filter Tasks" />
            <input type="submit" class="button button-danger" name="todo_clear_all_tasks" value="Clear All Tasks"
                onclick="return confirm('Are you sure you want to clear all tasks? This action cannot be undone.');" />
        </form>

    </div><br>
    <?php
        // Display existing tasks
        $tasks = get_todo_list_tasks(
            $selected_category,
            $selected_user,
            get_current_user_id(),
            'administrator',
            $selected_status,
            $selected_urgency,
            $selected_due_date,
            $selected_deadline,
			
        );

        // Display tasks in a table
        ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all-tasks"></th>
                <th><a
                        href="?page=todo-list-admin&orderby=ID&order=<?php echo ($selected_deadline === 'ID' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">ID</a>
                </th>
                <th><a
                        href="?page=todo-list-admin&orderby=Task&order=<?php echo ($selected_deadline === 'Task' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">Task</a>
                </th>
                <th><a href="<?php echo esc_url($tasks['todo_task_link']); ?>">Task Link</a>
                </th>
                <!-- <th>
                        <?php if (!empty($tasks['todo_task_link'])): ?>
                            <a href="<?php echo esc_url($tasks['todo_task_link']); ?>">Task Link</a>
                        <?php else: ?>
                            No Link Available
                        <?php endif; ?>
                    </th> -->
                <th>Description</th>
                <th><a href="?page=todo-list-admin&orderby=user_name&order=<?php echo ($selected_deadline === 'user_name'
                        && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">User
                        name</a><?php echo ($selected_deadline === 'user_name') ? ' ' . arrow_indicator($sorting_order) : ''; ?>
                </th>
                <th><a
                        href="?page=todo-list-admin&orderby=User_Type&order=<?php echo ($selected_deadline === 'User_Type' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">User
                        Type</a><?php echo ($selected_deadline === 'User_Type') ? ' ' . arrow_indicator($sorting_order) : ''; ?>
                </th>
                <th><a
                        href="?page=todo-list-admin&orderby=Status&order=<?php echo ($selected_deadline === 'Status' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">Status</a>
                </th>
                <th><a
                        href="?page=todo-list-admin&orderby=Due_Date&order=<?php echo ($selected_deadline === 'Due_Date' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">Due
                        Date</a></th>
                <th><a
                        href="?page=todo-list-admin&orderby=Due_Time&order=<?php echo ($selected_deadline === 'Due_Time' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">Time</a>
                </th>
                <th><a
                        href="?page=todo-list-admin&orderby=Due_Time&order=<?php echo ($selected_deadline === 'Categories' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">Categories</a>
                </th>
                <th><a
                        href="?page=todo-list-admin&orderby=Urgency&order=<?php echo ($selected_urgency === 'Urgency' && $sorting_order === 'ASC') ? 'DESC' : 'ASC'; ?>">Urgency</a>
                </th>
                <!-- <th>Deadline</th> -->
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $task_users = array(); // Array to store user IDs associated with tasks
                foreach ($tasks as $task) {
                    $task_users[] = get_post_meta($task['id'], 'todo_assigned_user', true);
                }
                ?>
            <?php

                foreach ($tasks as $task) {
                    $task_array = array($task);
                    $encoded_task = urlencode(base64_encode(serialize($task_array)));
                    echo '<tr>';
                    echo '<td><input type="checkbox" class="task-checkbox" name="selected_tasks[]" value="' . esc_attr($task['id']) . '"></td>';
                    echo '<td>' . esc_html($task['id']) . '</td>';
                    echo '<td>' . esc_html($task['name']) . '</td>';
                    echo '<td><a href="' . esc_url($task['todo_task_link']) . '">' . esc_html($task['todo_task_link']) . '</a></td>';
                    echo '<td>' . esc_html($task['description']) . '</td>';
                    echo '<td>' . esc_html($task['user_name']) . '</td>';
                    echo '<td>' . esc_html($task['user_type']) . '</td>';
                    echo '<td>' . esc_html($task['status']) . '</td>';
                    echo '<td>' . esc_html($task['due_date']) . '</td>';
                    echo '<td>' . esc_html($task['due_time']) . '</td>';
                    echo '<td>' . esc_html($task['category']) . '</td>';
                    echo '<td>' . esc_html($task['urgency']) . '</td>';
                    // echo '<td>' . esc_html($task['deadline']) . '</td>';
					
                    echo '<td>';
                    // echo '<a href="?page=todo-list-admin&action=edit&id=' . esc_attr($task['id']) . '">Edit</a>';
                    echo '<a href="?page=todo-list-admin&action=edit&task=' . $encoded_task . '">Edit</a>';

                    echo ' | ';
                    echo '<a href="?page=todo-list-admin&action=delete&id=' . esc_attr($task['id']) . '" onclick="return confirm(\'Are you sure you want to delete this task?\');">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
        </tbody>
    </table>

    <script>
    jQuery(document).ready(function($) {
        $('#select-all-tasks').on('change', function() {
            $('.task-checkbox').prop('checked', $(this).prop('checked'));
        });

        $('#todo-task-user-type').on('change', function() {
            var selectedUserType = $(this).val();

            // Fetch users based on selected user type using AJAX
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'get_users_by_user_type',
                    user_type: selectedUserType,
                    nonce: '<?php echo wp_create_nonce("todo-nonce"); ?>',
                },
                success: function(response) {
                    // Populate the user table with fetched users
                    $('#user-table-body').html(response);
                },
            });
        });

        // Select/deselect all users
        $('#select-all-users').on('change', function() {
            $('.user-checkbox').prop('checked', $(this).prop('checked'));
        });
        // Handle form submission for adding a task
        $('form[name="todo-admin-form"]').on('submit', function(event) {
            var selectedDueTime = $('#todo-task-due-time').val();

            // Add the selectedDueTime to the form data
            $(this).append('<input type="hidden" name="todo-task-due-time" value="' + selectedDueTime +
                '">');
        });
        // Mark Complete button click
        $('.mark-complete-btn').on('click', function() {
            var taskId = $(this).data('task-id');
            markComplete(taskId);
        });
        // Opt Out button click
        $('.opt-out-btn').on('click', function() {
            var taskId = $(this).data('task-id');
            optOut(taskId);
        });
        // Function to handle Mark Complete
        function markComplete(taskId) {
            // Make an AJAX request to mark the task as complete
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mark_task_complete',
                    task_id: taskId,
                },
                success: function(response) {
                    // Update the UI or handle any additional logic
                    console.log('Task marked as complete');
                },
                error: function(error) {
                    console.error('Error marking task as complete', error);
                }
            });
        }
        // Function to handle Opt Out
        function optOut(taskId) {
            // Make an AJAX request to opt out of the task
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'opt_out_task',
                    task_id: taskId,
                },
                success: function(response) {
                    // Update the UI or handle any additional logic
                    console.log('Task opted out');
                },
                error: function(error) {
                    console.error('Error opting out of the task', error);
                }
            });
        }
        // Click event for the "Grant Admin Access" button
        $('#grant-admin-access-btn').on('click', function() {
            var selectedUserIds = $('.user-checkbox:checked').map(function() {
                return this.value;
            }).get();

            // Update the hidden input field with selected user IDs (unchanged)
            $('input[name="selected_users[]"]').val(selectedUserIds.join(','));

            // Make an AJAX request to grant admin access
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'grant_admin_access',
                    selected_users: selectedUserIds.join(','),
                    nonce: '<?php echo wp_create_nonce("todo-nonce"); ?>',
                },
                success: function(response) {
                    // Handle success response, e.g., display a success message
                    console.log(response);
                    // You can also update the UI to show that the users have been granted admin access (e.g., remove them from the user selection list)
                },
                error: function(error) {
                    // Handle error response, e.g., display an error message
                    console.error('Error granting admin access', error);
                },
            });
        });
        $('#remove-admin-access-btn').on('click', function() {
            var selectedUserIds = $('.user-checkbox:checked').map(function() {
                return this.value;
            }).get();

            // Make an AJAX request to remove admin access
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'remove_admin_access',
                    selected_users: selectedUserIds.join(','),
                    nonce: '<?php echo wp_create_nonce("todo-nonce"); ?>',
                },
                success: function(response) {
                    // Handle success response, e.g., display a success message
                    console.log(response);
                    // Update the UI to reflect the removed admin access (e.g., re-enable the removed users in the user selection list)
                },
                error: function(error) {
                    // Handle error response, e.g., display an error message
                    console.error('Error removing admin access', error);
                },
            });
        });
    });
    </script>

    <?php

}
/* ADMIN SIDE END  */


/* USER SIDE START */
function todo_list_shortcode($atts)
{
    // Get the current user ID
    $user_id = get_current_user_id();

    // Get the current user's role
    $user_role = reset(wp_get_current_user()->roles);

    // Get the selected options from the admin panel
    $selected_category = isset($_POST['todo-category-option']) ? sanitize_text_field($_POST['todo-category-option']) : '';
    $selected_user = isset($_POST['todo-user-option']) ? $_POST['todo-user-option'] : 'all';
    $selected_status = isset($_POST['todo-status-option']) ? sanitize_text_field($_POST['todo-status-option']) : 'all';
    $selected_due_date = isset($_POST['todo-due-date-option']) ? sanitize_text_field($_POST['todo-due-date-option']) : '';
    $selected_deadline = isset($_POST['todo-deadline-option']) ? sanitize_text_field($_POST['todo-deadline-option']) : '';
	$selected_urgency = isset($_POST['todo-urgency-option']) ? sanitize_text_field($_POST['todo-urgency-option']) : 'all';

    // Handle marking a task as complete
    if (isset($_POST['todo_mark_complete_task']) && !$is_shortcode_admin) {
        // Process the form submission for marking a task as complete
        $task_id_to_complete = absint($_POST['todo_mark_complete_task']);
        update_post_meta($task_id_to_complete, 'todo_completed', true);
    }
    if (isset($_POST['todo_opt_out_task']) && !$is_shortcode_admin) {
        // Process the form submission for marking a task as complete
        $task_id_to_complete = absint($_POST['todo_opt_out_task']);
        update_post_meta($task_id_to_complete, 'todo_completed', true);
    }
    $task_id_to_opt_out = absint($_POST['todo_opt_out_task']);

    update_post_meta($task_id_to_opt_out, 'todo_opt_out', true);

    update_post_meta($task_id_to_opt_out, 'todo_completed', false); // Make sure to set completed to false

    // Output the form in User Page
    ob_start();
    ?>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Todo List</title>
        <style>
        /* Styles for filter options */
        .filter-options {
            margin-bottom: 20px;
        }

        .filter-options label {
            margin-right: 10px;
        }

        .filter-options input[type="date"],
        .filter-options select {
            margin-right: 10px;
            width: 150px;
            height: 30px;
        }

        .filter-buttons {
            margin-top: 10px;
        }

        /* Styles for table */
        .wp-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .wp-list-table th,
        .wp-list-table td {
            padding: 10px;
            text-align: left;
        }

        .wp-list-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .wp-list-table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }

        .wp-list-table tbody tr:hover {
            background-color: #e5e5e5;
        }

        /* Button styles */
        .button {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            border: none;
            border-radius: 4px;
        }

        .button-primary {
            background-color: #0073aa;
            color: #fff;
        }

        .mark-complete-button {
            background-color: #4caf50;
            color: #fff;
        }

        .opt-out-button {
            background-color: #4caf50;
            color: #fff;
        }

        /* Styles for completed tasks */
        .completed-tasks {
            margin-top: 20px;
        }

        .completed-tasks h2 {
            margin-bottom: 10px;
        }
        </style>
    </head>

    <body>
        <div class="filter-options">
            <form method="post" action="">
                <label for="todo-due-date-option" style="display: inline-block; margin-right: 10px;">Due Date:</label>
                <input type="date" id="todo-due-date-option" name="todo-due-date-option"
                    style="display: inline-block; margin-right: 10px;" />

                <!-- <label for="todo-deadline-option" style="display: inline-block; margin-right: 10px;">Urgency:</label>
                <select id="todo-deadline-option" name="todo-deadline-option"
                    style="display: inline-block; margin-right: 10px;">
                    <option value="">Select</option>
                    <option value="ascending">Ascending</option>
                    <option value="descending">Descending</option>
                </select> -->

                <label for="todo-urgency-option" style="display: inline-block; margin-right: 10px;">Urgency:</label>
                <select id="todo-urgency-option" name="todo-urgency-option"
                    style="display: inline-block; margin-right: 10px;">
                    <option value="" <?php selected($selected_urgency, 'all'); ?>>All</option>
                    <option value="high" <?php selected($selected_urgency, 'high'); ?>>High</option>
                    <option value="low" <?php selected($selected_urgency, 'low'); ?>>Low</option>
                    <option value="medium" <?php selected($selected_urgency, 'medium'); ?>>Medium</option>
                </select>
              
                <div class="filter-buttons" style="display: inline-block;">
                    <input type="submit" class="button button-primary" name="todo-filter-tasks" value="Filter Tasks" />
                    <?php if (current_user_can('administrator')): ?>
                    <button id="admin-button" class="button">
                        <a class="wp-block-pages-list__item__link wp-block-navigation-item__content"
                            href="http://localhost/wordpress/todo-staff/"> Todo Manager</a>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="incomplete-tasks">
            <h2>Incomplete Tasks</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th> </th>
                        <th style="text-align: center;">Task</th>
                        <th style="text-align: center;">Description</th>
                        <th style="text-align: center;">Urgency</th>
                        <th style="text-align: center;"> Deadline</th>

                    </tr>
                </thead>
                <tbody>
                    <?php
                        // Fetch and display the incomplete tasks based on selected options and filters
                        $incomplete_tasks = get_todo_list_tasks($selected_category, $selected_user, 
                        get_current_user_id(), $user_role,'incomplete',$selected_urgency ,$selected_due_date, $selected_deadline);
                        foreach ($incomplete_tasks as $task):
                            ?>
                    <tr>

                        <td style="display:flex; align-items: center; ">
                            <?php if ($task['status'] == 'Incomplete'): ?>
                            <form method="post" action="">
                                <div class="checkbox-container" style="display: flex; align-items: center;">
                                    <input type="checkbox" class="mark-complete-checkbox" name="todo_mark_complete_task"
                                        value="<?= esc_attr($task['id']); ?>" onchange="this.form.submit()">

                                </div>
                            </form>
                            <br><br>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><a href="<?php echo esc_url($task['todo_task_link']); ?>"
                                onclick="return confirmCompletionReminder('<?php echo esc_js($task['timeframe']); ?>');">
                                <?php echo esc_html($task['name']); ?>
                            </a>
                        </td>
                        <td style="text-align: center;"><?php echo esc_html($task['description']); ?></td>

                        <?php
                            $urgency = esc_html($task['urgency']);
                            $color = '';
                        // echo '<pre>';
                        //  print_r($task);
                            switch ($urgency) {
                                case 'high':
                                    $color = 'red';
                                    break;
                                case 'medium':
                                    $color = 'green';
                                    break;
                                case 'low':
                                    $color = '#FFC700';
                                    break;
                            }
                            ?>

                        <td style="color: <?php echo $color; ?>; text-align: center;"><?php echo $urgency; ?></td>
                        </td>
                        <td style="text-align: center;">
                            <?php echo esc_html($task['deadline']); ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                    <script>
                    function confirmCompletionReminder(taskName) {
                        alert("Complete the task '" + taskName + "' within Time");
                        return true; // Return true to allow the link to proceed
                    }
                    </script>
                </tbody>
            </table>
        </div>

        <div class="completed-tasks">
            <h2>Completed Tasks</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="text-align: center;">Task</th>
                        <th style="text-align: center;">Description</th>
                        <th style="text-align: center;">Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        // Fetch and display the completed tasks based on selected options and filters
                        $completed_tasks = 
                        get_todo_list_tasks($selected_category, $selected_user, get_current_user_id(), 
                        $user_role, 'completed',  $selected_urgency,$selected_due_date, $selected_deadline);
                        foreach ($completed_tasks as $task):
                            ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php echo esc_html($task['name']); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo esc_html($task['description']); ?>
                        </td>
                        <td style="text-align: center;"><?php echo esc_html($task['due_date']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>

    </html>


    <?php
        return ob_get_clean();
}
