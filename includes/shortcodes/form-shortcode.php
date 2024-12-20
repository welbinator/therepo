<?php

namespace TheRepo\Shortcode\Form;


function handle_plugin_repo_submission() {
    if (!isset($_POST['plugin_repo_nonce']) || !wp_verify_nonce($_POST['plugin_repo_nonce'], 'plugin_repo_submission')) {
        wp_die('Error: Invalid form submission.');
    }

    if (!is_user_logged_in()) {
        wp_die('Error: You must be logged in to submit a plugin or theme.');
    }

    // Gather input values
    $type = sanitize_text_field($_POST['type']);
    $name = sanitize_text_field($_POST['name']);
    $hosted_on_github = sanitize_text_field($_POST['hosted_on_github']);
    $github_username = sanitize_text_field($_POST['github_username']);
    $github_repo = sanitize_text_field($_POST['github_repo']);
    $download_url = esc_url_raw($_POST['download_url']);
    $description = sanitize_textarea_field($_POST['description']);
    $categories = isset($_POST['categories']) ? array_map('sanitize_text_field', $_POST['categories']) : array();
    $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array();

    // General field validation
    if (empty($type) || empty($name) || empty($description)) {
        wp_die('Error: All fields are required.');
    }

    // Conditional validation based on "Hosted on GitHub?"
    if ($hosted_on_github === 'yes') {
        if (empty($github_username) || empty($github_repo)) {
            wp_die('Error: GitHub Username and Repository are required when hosted on GitHub.');
        }
        // Construct the GitHub Latest Release API URL
        $latest_release_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/releases/latest";
    } else {
        if (empty($download_url)) {
            wp_die('Error: Download URL is required when not hosted on GitHub.');
        }
        $latest_release_url = $download_url; // Use download URL instead
    }

    // Handle file upload for featured image
    $featured_image_id = null;
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = wp_handle_upload($_FILES['featured_image'], array('test_form' => false));

        if ($upload && !isset($upload['error'])) {
            $filetype = wp_check_filetype($upload['file']);
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name($_FILES['featured_image']['name']),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $featured_image_id = wp_insert_attachment($attachment, $upload['file']);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($featured_image_id, $upload['file']);
            wp_update_attachment_metadata($featured_image_id, $attach_data);
        } else {
            wp_die('Error uploading featured image: ' . $upload['error']);
        }
    }

    // Handle file upload for cover image
    $cover_image_url = null;
    if (!empty($_FILES['cover_image_url']['name'])) {
        $upload = wp_handle_upload($_FILES['cover_image_url'], array('test_form' => false));

        if ($upload && !isset($upload['error'])) {
            $cover_image_url = $upload['url'];
        } else {
            wp_die('Error uploading cover image: ' . $upload['error']);
        }
    }

    // Determine post type and taxonomy
    $post_type = $type === 'plugin' ? 'plugin' : 'theme_repo';
    $taxonomy = $type === 'plugin' ? 'plugin-category' : 'theme-category';
    $tags_taxonomy = $type === 'plugin' ? 'plugin-tag' : 'theme-tag';

    // Create the post
    $post_id = wp_insert_post(array(
        'post_title'   => $name,
        'post_content' => $description,
        'post_excerpt' => wp_trim_words($description, 55),
        'post_type'    => $post_type,
        'post_status'  => 'pending',
        'post_author'  => get_current_user_id(),
    ));

    if ($post_id) {
        // Save meta fields
        update_post_meta($post_id, 'latest_release_url', $latest_release_url);
        update_post_meta($post_id, 'hosted_on_github', $hosted_on_github);
        if ($hosted_on_github === 'yes') {
            update_post_meta($post_id, 'github_username', $github_username);
            update_post_meta($post_id, 'github_repo', $github_repo);
        } else {
            update_post_meta($post_id, 'download_url', $download_url);
        }

        // Assign categories to the post
        if (!empty($categories)) {
            wp_set_object_terms($post_id, $categories, $taxonomy);
        }

        // Assign tags to the post
        if (!empty($tags)) {
            wp_set_object_terms($post_id, $tags, $tags_taxonomy);
        }

        // Save cover image URL
        if ($cover_image_url) {
            update_post_meta($post_id, 'cover_image_url', $cover_image_url);
        }

        // Set the featured image
        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        }

        // Redirect after success
        wp_redirect(add_query_arg('submission', 'success', home_url()));
        exit;
    } else {
        wp_die('Error: Unable to create the submission.');
    }
}




add_action('admin_post_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');
add_action('admin_post_nopriv_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission'); // For non-logged-in users

// Form shortcode
// Form shortcode
function plugin_repo_submission_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to submit a plugin or theme. <a href="' . wp_login_url(get_permalink()) . '">Log in</a> or <a href="' . wp_registration_url() . '">Register</a>.</p>';
    }
    ob_start(); ?>
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-2xl mx-auto">
                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="space-y-6" id="repo_submission_form">
                    <input type="hidden" name="action" value="plugin_repo_submission">
                    <?php wp_nonce_field('plugin_repo_submission', 'plugin_repo_nonce'); ?>
                    
                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" id="type" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="plugin">Plugin</option>
                            <option value="theme">Theme</option>
                        </select>
                    </div>

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Plugin/Theme Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            placeholder="Enter name" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Hosted on GitHub? -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hosted on GitHub?</label>
                        <div class="mt-1">
                            <label>
                                <input type="radio" name="hosted_on_github" value="yes" checked> Yes
                            </label>
                            <label class="ml-4">
                                <input type="radio" name="hosted_on_github" value="no"> No
                            </label>
                        </div>
                    </div>

                    <!-- GitHub Username and Repo -->
                    <div id="github-fields" class="github-wrapper flex gap-5">
                        <div class="github-column">
                            <label for="github_username" class="block text-sm font-medium text-gray-700">GitHub Username</label>
                            <input 
                                type="text" 
                                name="github_username" 
                                id="github_username" 
                                placeholder="GitHub username" 
                                required 
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div class="github-column">
                            <label for="github_repo" class="block text-sm font-medium text-gray-700">GitHub Repo</label>
                            <input 
                                type="text" 
                                name="github_repo" 
                                id="github_repo" 
                                placeholder="GitHub repository name" 
                                required 
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Download URL -->
                    <div id="download-url-field" style="display: none;">
                        <label for="download_url" class="block text-sm font-medium text-gray-700">Download URL</label>
                        <input 
                            type="url" 
                            name="download_url" 
                            id="download_url" 
                            placeholder="Enter download URL" 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p class="text-sm text-gray-500 mt-1">Provide the direct URL to download your plugin/theme.</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea 
                            name="description" 
                            id="description" 
                            placeholder="Enter description" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        ></textarea>
                    </div>

                    <!-- Categories -->
                    <div>
                        <label for="categories" class="block text-sm font-medium text-gray-700">Categories</label>
                        <select name="categories[]" id="categories" multiple="multiple" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <!-- Dynamic options will be loaded via Select2 -->
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Start typing to search for existing categories or add new ones.</p>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label for="tags" class="block text-sm font-medium text-gray-700">Tags</label>
                        <select name="tags[]" id="tags" multiple="multiple" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <!-- Dynamic options will be loaded via Select2 -->
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Start typing to search for existing tags or add new ones.</p>
                    </div>

                    <!-- Featured Image -->
                    <div>
                        <label for="featured_image" class="block text-sm font-medium text-gray-700">Featured Image</label>
                        <input 
                            type="file" 
                            name="featured_image" 
                            id="featured_image" 
                            accept="image/*" 
                            required 
                            class="mt-1 block w-full text-sm text-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Cover Image -->
                    <div>
                        <label for="cover_image_url" class="block text-sm font-medium text-gray-700">Cover Image</label>
                        <input 
                            type="file" 
                            name="cover_image_url" 
                            id="cover_image_url" 
                            accept="image/jpg,image/jpeg,image/png" 
                            class="mt-1 block w-full text-sm text-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Submit Plugin/Theme
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- JavaScript -->
    <script>
        // JavaScript logic for toggling fields remains unchanged
    </script>
    <?php
    return ob_get_clean();
}



add_shortcode('plugin_repo_form', __NAMESPACE__ . '\\plugin_repo_submission_form_shortcode');

// Display success message
add_action('wp_footer', function () {
    if (isset($_GET['submission']) && $_GET['submission'] === 'success') {
        echo '<div class="notice success">Thank you! Your submission has been received and is pending approval.</div>';
    }
});